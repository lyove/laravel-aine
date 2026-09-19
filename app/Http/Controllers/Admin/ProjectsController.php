<?php

namespace App\Http\Controllers\Admin;

use App\Aine\AuditLogger;
use App\Aine\ProjectTemplates;
use App\Aine\PublicCache;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

class ProjectsController extends Controller
{

    /**
     * Get projects
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Project
     */
    public function index(Request $request){
        /** @var User $user */
        $user = Auth::user();

        $projects = Project::query();
        if (! $user->isSuperAdmin()) {
            $projects = $projects->forUser($user);
        }
        $projects = $projects->withCount(['collections', 'members'])
            ->when($request->get('search'), function($q)use($request){
                $searchItem = $request->get('search');
                return $q->where('name', 'LIKE', "%$searchItem%");
            });

        $projects = $projects->orderBy('created_at', 'ASC')->get();

        $projects->each(function (Project $project) use ($user) {
            $project->presentFor($user);
        });

        return response($projects, 200);
    }

    /**
     * Check if slug exists
     *
     * @param string $slug
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function checkSlug($slug, Request $request){
        $query = Project::where('slug', $slug);
        
        $excludeId = $request->get('exclude_id');
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        $exists = $query->exists();
        
        return response([
            'available' => !$exists
        ], 200);
    }

    /**
     * Create a new project
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Project
     */
    public function store(Request $request){

        // Any authenticated user may create a project; they become its owner.
        $this->authorize('create', Project::class);

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[#$%^&*()+=\-\[\]\';,\/{}|":<>?~\\\\]/'],
            'slug' => 'nullable|regex:/^[a-z0-9-]+$/|max:60|unique:projects,slug',
            'default_locale' => 'required|max:255',
        ],[
            'slug.regex' => __('Slug can only contain lowercase letters, numbers, and hyphens'),
            'slug.unique' => __('Slug already exists')
        ]);

        $slug = $request->get('slug');
        if (empty($slug)) {
            $slug = Str::slug($request->get('name'));
        }

        /** @var User $user */
        $user = Auth::user();

        $project = Project::create([
        	'owner_id' => $user->id,
        	'name' => $request->get('name'),
        	'slug' => $slug,
        	'description' => $request->get('description'),
        	'default_locale' => $request->get('default_locale'),
        	'locales' => $request->get('default_locale'),
        	'timezone' => $request->get('timezone', 'UTC'),
        ]);

        // The creator is the owner of the project (membership role).
        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectUser::ROLE_OWNER,
        ]);

        // Apply a preset template (CMS / Business Directory) when selected.
        $templateType = (int) $request->get('type');
        if ($templateType && $template = ProjectTemplates::get($templateType)) {
            ProjectTemplates::apply($project, $template);
        }

        AuditLogger::log('create', 'project', $project->id, $project->name, null, $project->id);

        return response($project, 200);
    }

    /**
     * Get project by id
     *
     * @param int $id
     * @return \App\Models\Project
     */
    public function show($id){
        /** @var User $user */
        $user = Auth::user();

        $project = Project::with('collections')->findOrFail($id);

        $this->authorize('view', $project);

        $project->presentFor($user);

        $project->s3 = false;

        if(config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret') && config('filesystems.disks.s3.region') && config('filesystems.disks.s3.bucket')){
            $project->s3 = true;
        }

        return $project;
    }

    /**
     * Update project
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Project
     */
    public function update($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('update', $project);

        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|regex:/^[a-z0-9-]+$/|max:60|unique:projects,slug,' . $id,
        ],[
            'name.required' => __('Project name is required'),
            'slug.required' => __('Project slug is required'),
            'slug.regex' => __('Slug can only contain lowercase letters, numbers, and hyphens'),
            'slug.unique' => __('Slug already exists')
        ]);

        $slug = $request->get('slug');
        if (empty($slug)) {
            $slug = Str::slug($request->get('name'));
        }

        $project->update([
        	'name' => $request->get('name'),
        	'slug' => $slug,
        	'description' => $request->get('description'),
        	'disk' => $request->get('disk', $project->disk),
            'status' => $request->get('status', $project->status),
            'copyright' => $request->get('copyright'),
            'filing_info' => $request->get('filing_info'),
            'timezone' => $request->get('timezone'),
            'language_direction' => $request->get('language_direction', 'ltr'),
            'logo_url' => $request->get('logo_url'),
            'favicon_url' => $request->get('favicon_url'),
            'custom_header' => $request->get('custom_header'),
            'custom_footer' => $request->get('custom_footer'),
            'seo_title' => $request->get('seo_title'),
            'seo_description' => $request->get('seo_description'),
            'seo_keywords' => $request->get('seo_keywords'),
            'analytics_script' => $request->get('analytics_script'),
            'contact_address' => $request->get('contact_address'),
            'contact_email' => $request->get('contact_email'),
            'contact_phone' => $request->get('contact_phone'),
            'contact_text' => $request->get('contact_text'),
        ]);

        AuditLogger::log('update', 'project', $project->id, $project->name, null, $project->id);

        return response($project, 200);
    }

    /**
     * Toggle the project's active/inactive status.
     *
     * @param int $id
     * @return \App\Models\Project
     */
    public function toggleStatus($id){
        $project = Project::findOrFail($id);

        $this->authorize('update', $project);

        $project->status = ! $project->isActive();
        $project->save();

        AuditLogger::log('toggle_status', 'project', $project->id, $project->name, null, $project->id);

        $project->presentFor(auth()->user());

        return response($project, 200);
    }

    /**
     * Delete a project
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id){
        $project = Project::findOrFail($id);

        $this->authorize('delete', $project);

        if (!$project->isActive()) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Please reactivate the project before deleting.',
                'data' => null,
            ], 403);
        }

        $project->collections()->delete();
        $project->fields()->delete();
        $project->content()->forceDelete();
        $project->meta()->forceDelete();

        $project->media()->delete();
        Storage::disk($project->disk)->deleteDirectory($project->uuid);

        $project->tokens()->delete();

        foreach ($project->webhooks as $webhook) {
            $webhook->collections()->detach();
        }
        $project->webhooks()->delete();
        $project->webhook_logs()->delete();
        $project->forms()->delete();

        Role::where('name', 'admin'.$id)->delete();
        Role::where('name', 'editor'.$id)->delete();

        if($project->delete()){
            AuditLogger::log('delete', 'project', $id, $project->name ?? null);
            return response([], 200);
        } else {
            return response([], 404);
        }
    }

    /**
     * Get project locales
     *
     * @param int $id
     * @return \App\Models\Project
     */
    public function locales($id){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        return $project->presentFor(auth()->user());
    }

    /**
     * Add new locale to project
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function addLocale($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $project_locales = explode(',', $project->locales);

        if(in_array($request->get('locale'), $project_locales)){
            return response([], 422);
        }

        if(!in_array($request->get('locale'), $project_locales)){
            if($project->locales === null){
                $project->locales = $request->get('locale');
            } else {
                $project->locales = $project->locales.",".$request->get('locale');
            }
        }

        $project->save();

        PublicCache::bump($project->id);
    }

    /**
     * Change default locale of the project
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function changeDefaultLocale($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $project->default_locale = $request->get('locale');
        $project->save();

        PublicCache::bump($project->id);
    }

    /**
     * Delete a locale from project
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function deleteLocale($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        if($request->get('locale') == $project->default_locale){
            return response([], 422);
        }

        $project_locales = explode(',', $project->locales);

        $localesStr = '';
        foreach ($project_locales as $locale) {
            if($locale != $request->get('locale')){
                $localesStr .= $locale.',';
            }
        }
        $project->locales = rtrim($localesStr, ',');
        $project->save();

        PublicCache::bump($project->id);
    }

    /**
     * Get users (project members grouped by role + candidate users).
     *
     * @param int $id
     * @return mixed
     */
    public function users($id){
        $project = Project::findOrFail($id);

        $this->authorize('manageMembers', $project);

        $user = auth()->user();
        $project->presentFor($user);

        $super_admins = User::whereHas('roles', function($q){ $q->where('name', 'super_admin'); })->get();

        $members = ProjectUser::where('project_id', $project->id)->get()->keyBy('user_id');

        $admins = User::whereIn('id', $members->where('role', ProjectUser::ROLE_ADMIN)->pluck('user_id'))->get();
        $editors = User::whereIn('id', $members->where('role', ProjectUser::ROLE_EDITOR)->pluck('user_id'))->get();
        $viewers = User::whereIn('id', $members->where('role', ProjectUser::ROLE_VIEWER)->pluck('user_id'))->get();

        $users = User::whereDoesntHave('roles', function($q){ $q->where('name', 'super_admin'); })
            ->whereNotIn('id', $members->pluck('user_id'))
            ->get();

        return [
            'project' => $project,
            'super_admins' => $super_admins,
            'owner' => $project->owner,
            'admins' => $admins,
            'editors' => $editors,
            'viewers' => $viewers,
            'users' => $users,
        ];
    }

    /**
     * Assign user to the project (adds / updates the membership role).
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function assignUser($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('manageMembers', $project);

        $user = User::findOrFail($request->get('user_id'));

        $role = $request->get('role', ProjectUser::ROLE_EDITOR);

        if (! in_array($role, [ProjectUser::ROLE_ADMIN, ProjectUser::ROLE_EDITOR, ProjectUser::ROLE_VIEWER], true)) {
            $role = ProjectUser::ROLE_EDITOR;
        }

        $user->assignRole(Role::firstOrCreate(['name' => 'user']));

        $existing = ProjectUser::where('project_id', $project->id)->where('user_id', $user->id)->first();

        if ($existing && $existing->role === ProjectUser::ROLE_OWNER) {
            return response([], 422);
        }

        $this->setMembershipRole($project->id, $user->id, $role);

        return response([], 200);
    }

    /**
     * Insert or update a member's role on a project.
     *
     * @param int $projectId
     * @param int $userId
     * @param string $role
     * @return void
     */
    private function setMembershipRole(int $projectId, int $userId, string $role): void
    {
        $updated = ProjectUser::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->update(['role' => $role]);

        if ($updated === 0) {
            ProjectUser::create([
                'project_id' => $projectId,
                'user_id' => $userId,
                'role' => $role,
            ]);
        }
    }

    /**
     * Remove user from project
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function removeUser($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('manageMembers', $project);

        $user = User::findOrFail($request->get('user_id'));

        if ($project->owner_id === $user->id) {
            return response(['error' => __('The project owner cannot be removed.')], 422);
        }

        ProjectUser::where('project_id', $project->id)->where('user_id', $user->id)->delete();

        return response([], 200);
    }

    /**
     * Transfer project ownership to another user. The new owner gets the
     * "owner" membership; the previous owner stays on as an admin member so
     * they keep access after handing the project over.
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function transferOwnership($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('manageMembers', $project);

        $newOwner = User::findOrFail($request->get('user_id'));

        if ($project->owner_id === $newOwner->id) {
            return response([], 200);
        }

        $previousOwner = $project->owner;

        DB::transaction(function () use ($project, $newOwner, $previousOwner) {
            $project->update(['owner_id' => $newOwner->id]);

            $newOwner->assignRole(Role::firstOrCreate(['name' => 'user']));

            $this->setMembershipRole($project->id, $newOwner->id, ProjectUser::ROLE_OWNER);

            if ($previousOwner) {
                $this->setMembershipRole($project->id, $previousOwner->id, ProjectUser::ROLE_ADMIN);
            }
        });

        return response([], 200);
    }

    /**
     * Create a new user and add them to the project.
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function newUser($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('manageMembers', $project);

        $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'password' => ['required', Password::default()],
        ]);

        $role = $request->get('role', ProjectUser::ROLE_EDITOR);

        if (! in_array($role, [ProjectUser::ROLE_ADMIN, ProjectUser::ROLE_EDITOR, ProjectUser::ROLE_VIEWER], true)) {
            $role = ProjectUser::ROLE_EDITOR;
        }

        $user = User::create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password'))
        ]);

        $user->assignRole(Role::firstOrCreate(['name' => 'user']));

        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return response($user, 200);
    }

    /**
     * Get api settings
     *
     * @param int $id
     * @return mixed
     */
    public function api($id){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $data['project'] = $project->presentFor(auth()->user());
        $data['tokens'] = $project->tokens;

        return $data;
    }

    /**
     * Crate a new API token
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return string $token->plainTextToken
     */
    public function newToken($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $request->validate([
            'name' => 'required'
        ]);

        $token = $project->createToken(
            $request->get('name'),
            $request->input('permissions', ['read'])
        );

        AuditLogger::log('create', 'token', $token->accessToken->id, $request->get('name'), [
            'abilities' => $request->input('permissions', ['read']),
        ], $project->id);

        return explode('|', $token->plainTextToken, 2)[1];
    }

    /**
     * Update a token
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function updateToken($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $token_id = $request->get('id');

        $token = PersonalAccessToken::where('tokenable_id', $project->id)
            ->where('tokenable_type', Project::class)
            ->findOrFail($token_id);

        $request->validate([
            'name' => 'required'
        ]);

        $token->update([
            'name' => $request->get('name'),
            'abilities' => $request->get('permissions'),
        ]);

        AuditLogger::log('update', 'token', $token_id, $request->get('name'), [
            'abilities' => $request->get('permissions'),
        ], $project->id);
    }

    /**
     * Delete token
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function deleteToken($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $token = PersonalAccessToken::where('tokenable_id', $project->id)
            ->where('tokenable_type', Project::class)
            ->find($request->get('id'));

        $project->tokens()->where('id', $request->get('id'))->delete();

        AuditLogger::log('delete', 'token', $request->get('id'), $token->name ?? null, null, $project->id);
    }

    /**
     * Enable Public API Access
     *
     * @param int $id
     * @return void
     */
    public function enablePublicAPIAccess($id){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $project->public_api = true;
        $project->save();
    }

    /**
     * Disable Public API Access
     *
     * @param int $id
     * @return void
     */
    public function disablePublicAPIAccess($id){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $project->public_api = false;
        $project->save();
    }

    /**
     * Update domain whitelist for client applications that may call this project's API.
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function updateDomainWhitelist($id, Request $request){
        $project = Project::findOrFail($id);

        $this->authorize('updateSettings', $project);

        $request->validate([
            'domain_whitelist' => 'array',
            'domain_whitelist.*' => 'url'
        ]);

        $oldWhitelist = (array) ($project->domain_whitelist ?? []);
        $newWhitelist = $request->get('domain_whitelist', []);

        $project->domain_whitelist = $newWhitelist;
        $project->save();

        foreach (array_merge($oldWhitelist, $newWhitelist) as $entry) {
            $host = is_string($entry) ? parse_url($entry, PHP_URL_HOST) : null;
            if ($host) {
                \App\Http\Middleware\DynamicCors::forgetCacheKey($host);
            }
        }

        return response()->json(['message' => 'Domain whitelist updated successfully']);
    }

    /**
     * Get webhook settings
     *
     * @param  int  $project_id
     * @return \App\Models\Project
     */
    public function webhooks($project_id)
    {
        $project = Project::with(['collections', 'webhooks', 'webhooks.collections'])->findOrFail($project_id);

        $this->authorize('updateSettings', $project);

        return $project->presentFor(auth()->user());
    }

    /**
     * Crate a new Webhook
     *
     * @param  int  $project_id
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Webhook $webhook
     */
    public function newWebhook($project_id, Request $request)
    {
        $project = Project::findOrFail($project_id);

        $this->authorize('updateSettings', $project);

        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|regex:/^(https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/',
            'secret' => 'string|nullable|max:255|min:12',
            'collection_ids' => 'required|array',
            'events' => 'required|array',
            'sources' => 'required|array',
        ]);

        $webhook = Webhook::create([
            'project_id' => $project->id,
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'url' => $request->get('url'),
            'secret' => $request->get('secret'),
            'collection_ids' => $request->get('collection_ids'),
            'events' => $request->get('events'),
            'sources' => $request->get('sources'),
            'payload' => $request->get('payload'),
            'status' => $request->get('status'),
            'created_by' => $user->id,
        ]);

        $webhook->collections()->sync($request->get('collection_ids'));

        return $webhook;
    }

    /**
     * Update a webhook
     *
     * @param  int  $project_id
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Webhook $webhook
     */
    public function updateWebhook($project_id, Request $request)
    {
        $project = Project::findOrFail($project_id);

        $this->authorize('updateSettings', $project);

        $webhook = Webhook::findOrFail($request->get('id'));

        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|regex:/^(https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/',
            'secret' => 'string|nullable|max:255|min:12',
            'collection_ids' => 'required|array',
            'events' => 'required|array',
            'sources' => 'required|array',
        ]);

        $webhook->update([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'url' => $request->get('url'),
            'secret' => $request->get('secret'),
            'collection_ids' => $request->get('collection_ids'),
            'events' => $request->get('events'),
            'sources' => $request->get('sources'),
            'payload' => $request->get('payload'),
            'status' => $request->get('status'),
        ]);

        $webhook->collections()->sync($request->get('collection_ids'));

        return $webhook;
    }

    /**
     * Delete webhook
     *
     * @param  int  $project_id
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function deleteWebhook($project_id, Request $request)
    {
        $project = Project::findOrFail($project_id);

        $this->authorize('updateSettings', $project);

        $webhook = Webhook::findOrFail($request->get('id'));

        $webhook->logs()->delete();
        $webhook->collections()->detach();
        $webhook->delete();

        return response([], 200);
    }

    /**
     * Get webhook logs
     *
     * @param  int  $project_id
     * @param  int  $webhook_id
     * @return mixed
     */
    public function webhookLogs($project_id, $webhook_id)
    {
        $project = Project::with(['collections'])->findOrFail($project_id);

        $this->authorize('updateSettings', $project);

        $data['project'] = $project->presentFor(auth()->user());
        $data['webhook'] = Webhook::findOrFail($webhook_id);
        $data['logs'] = WebhookLog::where('webhook_id', $webhook_id)->paginate(25);

        return $data;
    }

    /**
     * Delete webhook logs
     *
     * @param  int  $project_id
     * @param  int  $webhook_id
     * @return void
     */
    public function deleteWebhookLogs($project_id, $webhook_id)
    {
        $project = Project::with(['collections'])->findOrFail($project_id);

        $this->authorize('updateSettings', $project);

        $webhook = Webhook::findOrFail($webhook_id);
        $logs = WebhookLog::where('webhook_id', $webhook_id)->delete();

        return response([], 200);
    }
}
