<?php

namespace App\Http\Controllers\Admin;

use App\Aine\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Global user management for super admins.
 */
class UsersManagementController extends Controller
{
    private const ROLES = ['super_admin', 'user'];

    /** List users (searchable + role filter), with role counts and project memberships. */
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $role = (string) $request->get('role', 'all');
        $each = (int) $request->get('each', 15);

        $users = User::query()
            ->when($role === 'super_admin', fn ($q) => $q->role('super_admin'))
            ->when($role === 'user', fn ($q) => $q->role('user'))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->paginate($each);

        $users->getCollection()->transform(function (User $user) {
            $user->setAttribute('global_role', $user->roles->pluck('name')->first() ?? 'user');
            $user->setAttribute('two_factor_enabled', $user->twoFactorEnabled());
            $user->setAttribute('memberships', $user->projects()->get(['projects.id', 'projects.name'])->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'role' => $project->pivot->role,
                ];
            })->values()->all());

            return $user;
        });

        $data = $users->toArray();
        $data['counts'] = [
            'all' => User::count(),
            'super_admin' => User::role('super_admin')->count(),
            'user' => User::role('user')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /** Bulk action: change role or delete the selected users. */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'string', 'in:role,delete'],
            'role' => ['required_if:action,role', 'string', 'in:' . implode(',', self::ROLES)],
        ]);

        $users = User::whereIn('id', $validated['ids'])->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching users found.',
            ], 422);
        }

        // Guard: bulk actions never apply to yourself.
        if ($users->contains('id', Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk actions cannot include your own account.',
            ], 422);
        }

        $superAdmins = $users->filter->isSuperAdmin();

        if ($validated['action'] === 'delete') {
            if ($superAdmins->isNotEmpty() && $superAdmins->count() >= $this->superAdminCount()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the last super admin.',
                ], 422);
            }

            foreach ($users as $user) {
                $user->delete();
                AuditLogger::log('delete', 'user', $user->id, $user->email);
            }

            return response()->json(['success' => true, 'counts' => $this->counts()]);
        }

        // action === 'role'
        if ($validated['role'] !== 'super_admin' && $superAdmins->isNotEmpty() && $superAdmins->count() >= $this->superAdminCount()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the last super admin.',
            ], 422);
        }

        foreach ($users as $user) {
            $user->syncRoles([$validated['role']]);
            AuditLogger::log('update', 'user', $user->id, $user->email, ['role' => $validated['role']]);
        }

        return response()->json(['success' => true, 'counts' => $this->counts()]);
    }

    /** Create a platform user with a global role. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:' . implode(',', self::ROLES)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->syncRoles([$validated['role']]);

        AuditLogger::log('create', 'user', $user->id, $user->email, ['role' => $validated['role']]);

        return response()->json([
            'success' => true,
            'data' => $this->presentUser($user),
        ], 201);
    }

    /** Update name / email / global role; password optional. */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:' . implode(',', self::ROLES)],
        ]);

        // Guard: never remove the last super_admin (self or otherwise).
        if ($validated['role'] !== 'super_admin' && $user->isSuperAdmin() && $this->superAdminCount() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the last super admin.',
            ], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        if (! $user->hasRole($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        AuditLogger::log('update', 'user', $user->id, $user->email, ['role' => $validated['role']]);

        return response()->json([
            'success' => true,
            'data' => $this->presentUser($user),
        ]);
    }

    /** Delete a user. Guards: cannot delete yourself, cannot delete the last super admin. */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($user->isSuperAdmin() && $this->superAdminCount() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the last super admin.',
            ], 422);
        }

        $user->delete();

        AuditLogger::log('delete', 'user', $user->id, $user->email);

        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function superAdminCount(): int
    {
        return Role::findByName('super_admin')->users()->count();
    }

    private function counts(): array
    {
        return [
            'all' => User::count(),
            'super_admin' => User::role('super_admin')->count(),
            'user' => User::role('user')->count(),
        ];
    }

    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'global_role' => $user->roles->pluck('name')->first() ?? 'user',
        ];
    }
}
