<?php

namespace Database\Seeders;

use App\Aine\ProjectTemplates;
use App\Models\Collection;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Media;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\ProjectUser;
use App\Models\Setting;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Spatie\Permission\Models\Role;

/**
 * Demo preset data for both project templates:
 *
 * Usage:  php artisan migrate:fresh --seed --seeder=DemoProjectsSeeder
 */
class DemoProjectsSeeder extends Seeder
{
    public function run()
    {
        $this->call(AdminTranslationsSeeder::class);

        $this->seedBaseData();
        $this->seedCmsProject();
        $this->seedDirectoryProject();
        $this->seedSpeechProject();
    }

    /* ------------------------------------------------------------------ */
    /* Base data (admin user, role, settings)                              */
    /* ------------------------------------------------------------------ */

    protected function seedBaseData(): void
    {
        $skipAdmin = config('installer.seed_demo_skip_admin', false);
        $hasSuperAdmin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->exists();

        if (! $skipAdmin && ! $hasSuperAdmin) {
            $user = User::firstOrCreate(
                ['email' => 'admin@admin.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('admin'),
                ]
            );

            $role = Role::firstOrCreate(['name' => 'super_admin']);
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        Setting::firstOrCreate(
            ['id' => 1],
            [
                'name' => config('app.name', 'Aine'),
                'description' => 'CMS Template + Business Directory Template + Speech Template',
                'version' => env('APP_VERSION', '2.0.0'),
            ]
        );

        Role::firstOrCreate(['name' => 'user']);
    }

    /* ------------------------------------------------------------------ */
    /* Shared helpers                                                      */
    /* ------------------------------------------------------------------ */

    protected function createProject(array $data): Project
    {
        $previous = Project::where('slug', $data['slug'])->first();
        if ($previous) {
            $this->removeProject($previous);
        }

        $data['owner_id'] ??= User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->value('id');

        $project = Project::create($data);

        ProjectUser::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $project->owner_id],
            ['role' => ProjectUser::ROLE_OWNER]
        );

        return $project;
    }

    /**
     * Hard-delete a seeded project together with every related row and its
     * media files on disk.
     */
    protected function removeProject(Project $project): void
    {
        $project->content()->withTrashed()->forceDelete();
        $project->meta()->withTrashed()->forceDelete();
        $project->fields()->delete();
        $project->collections()->delete();
        $project->media()->delete();
        $project->webhooks()->delete();
        $project->webhook_logs()->delete();
        $project->forms()->delete();
        ProjectUser::where('project_id', $project->id)->delete();
        DB::table('project_translations')->where('project_id', $project->id)->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', Project::class)
            ->where('tokenable_id', $project->id)
            ->delete();

        Storage::disk('local')->deleteDirectory('public/'.$project->uuid);
        $project->forceDelete();
    }

    /**
     * Create one content row + its meta rows, exactly like the admin
     * ContentController@store persists them (values are plain strings;
     * media/relation/enumeration-multi are comma-joined).
     */
    protected function addContent(Project $project, Collection $collection, array $data, bool $published = false, bool $trashed = false, int $daysAgo = 0, string $locale = 'en'): Content
    {
        $created = now()->subDays($daysAgo);

        $content = Content::create([
            'project_id' => $project->id,
            'collection_id' => $collection->id,
            'locale' => $locale,
            'created_at' => $created,
            'created_by' => 1,
            'updated_at' => $created,
            'updated_by' => 1,
            'published_at' => $published ? $created->copy()->addMinutes(5) : null,
            'published_by' => $published ? 1 : null,
        ]);

        foreach ($data as $fieldName => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            ContentMeta::create([
                'project_id' => $project->id,
                'collection_id' => $collection->id,
                'content_id' => $content->id,
                'field_name' => $fieldName,
                'value' => (string) $value,
            ]);
        }

        if ($trashed) {
            $content->delete();
        }

        return $content;
    }

    /**
     * Simple placeholder image (gradient + centered label) so media is visible.
     */
    protected function makeGradientJpeg(string $path, int $w, int $h, array $from, array $to, string $label, bool $png = false): void
    {
        @mkdir(dirname($path), 0775, true);

        $img = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y++) {
            $t = $y / max($h - 1, 1);
            $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
            $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
            $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
        }

        $white = imagecolorallocatealpha($img, 255, 255, 255, 60);
        imagefilledellipse($img, (int) ($w * 0.85), (int) ($h * 0.2), (int) ($w * 0.45), (int) ($w * 0.45), $white);
        $white2 = imagecolorallocatealpha($img, 255, 255, 255, 40);
        imagefilledellipse($img, (int) ($w * 0.12), (int) ($h * 0.9), (int) ($w * 0.35), (int) ($w * 0.35), $white2);

        $textColor = imagecolorallocate($img, 255, 255, 255);
        $font = 5;
        $tw = imagefontwidth($font) * strlen($label);
        imagestring($img, $font, (int) (($w - $tw) / 2), (int) (($h - imagefontheight($font)) / 2), $label, $textColor);

        if ($png) {
            imagepng($img, $path, 8);
        } else {
            imagejpeg($img, $path, 85);
        }

        imagedestroy($img);
    }

    /**
     * Generate a set of images on disk + Media records for a project.
     * Returns name => media id.
     */
    protected function seedMedia(Project $project, array $images): array
    {
        $uuid = $project->uuid;
        $base = 'public/'.$uuid;
        $manager = new ImageManager(new GdDriver());
        $ids = [];

        foreach ($images as [$name, $label, $from, $to, $w, $h, $png]) {
            $path = storage_path('app/'.$base.'/'.$name);
            $this->makeGradientJpeg($path, $w, $h, $from, $to, $label, $png);

            $thumb = $manager->read($path)->scale(height: 600)->encodeByExtension($png ? 'png' : 'jpg');
            Storage::disk('local')->put($base.'/thumbnails/'.$name, $thumb);

            $thumbDir = storage_path('app/'.$base.'/thumbnails');
            @chmod($thumbDir, 0775);
            foreach ((glob($thumbDir.'/*') ?: []) as $thumbFile) {
                if (is_file($thumbFile)) {
                    @chmod($thumbFile, 0664);
                }
            }

            $media = Media::create([
                'project_id' => $project->id,
                'name' => $name,
                'type' => $png ? 'png' : 'jpg',
                'size' => filesize($path),
                'width' => $w,
                'height' => $h,
                'caption' => $label,
                'disk' => 'local',
            ]);
            $ids[$name] = $media->id;
        }

        return $ids;
    }

    /**
     * API token + webhook for a project.
     */
    protected function seedProjectFeatures(Project $project, Collection $triggerCollection): void
    {
        $token = $project->createToken($project->name.' Token', ['read', 'write', 'delete']);
        $this->command?->info($project->slug.' API token: '.$token->plainTextToken);

        $webhook = Webhook::create([
            'project_id' => $project->id,
            'name' => $triggerCollection->name.' events',
            'description' => 'Fires when '.$triggerCollection->slug.' are created, updated, published, unpublished or deleted from the Admin UI or the API.',
            'url' => 'https://example.com/hooks/aine-demo',
            'secret' => 'demo-secret-12345',
            'collection_ids' => [$triggerCollection->id],
            'events' => ['content.created', 'content.updated', 'content.published', 'content.unpublished', 'content.trashed', 'content.deleted'],
            'sources' => ['User', 'API'],
            'payload' => true,
            'status' => true,
            'created_by' => 1,
        ]);
        $webhook->collections()->sync([$triggerCollection->id]);
    }

    /**
     * Default Chinese translations of the project's structure labels.
     */
    protected function seedProjectTranslations(Project $project, array $map): void
    {
        $baseLocale = $project->default_locale ?? 'en';
        $locales = $project->locales ? explode(',', $project->locales) : [$baseLocale];

        foreach ($locales as $locale) {
            if ($locale === $baseLocale) {
                continue;
            }
            foreach ($map as $source => $value) {
                ProjectTranslation::updateOrCreate(
                    ['project_id' => $project->id, 'locale' => $locale, 'source' => $source],
                    ['value' => $value]
                );
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Demo CMS (CMS Template)                                             */
    /* ------------------------------------------------------------------ */

    protected function seedCmsProject(): void
    {
        $project = $this->createProject([
            'name' => 'CMS',
            'slug' => 'cms',
            'description' => 'A complete Content Management System — articles, categories, tags, comments and pages.',
            'default_locale' => 'en',
            'locales' => 'en,zh',
            'disk' => 'local',
            'public_api' => true,
            'domain_whitelist' => [
                config('app.url'),
                'http://localhost:3000',
                'http://localhost:5173',
            ],
        ]);

        $c = ProjectTemplates::apply($project, ProjectTemplates::get(ProjectTemplates::CMS));

        $media = $this->seedMedia($project, [
            ['cover-1.jpg', 'Getting Started with Aine', [37, 99, 235], [99, 102, 241], 1200, 630, false],
            ['cover-2.jpg', 'Building with Vue 3', [16, 185, 129], [5, 150, 105], 1200, 630, false],
            ['cover-3.jpg', 'Aine 2.0 Release', [217, 119, 6], [239, 68, 68], 1200, 630, false],
            ['cover-4.jpg', 'Laravel Packages', [124, 58, 237], [236, 72, 153], 1200, 630, false],
        ]);

        /* --- Pages --- */
        foreach ([
            ['title' => 'Home', 'slug' => 'home', 'content' => '<h1>Welcome to Content Management System</h1><p>Articles, categories, tags, comments and pages — every field type and content state is covered.</p>'],
            ['title' => 'About Us', 'slug' => 'about', 'content' => '<h2>About Content Management System</h2><p>This site is powered by <a href="https://github.com/kothing/laravel-aine">Aine CMS</a>, a self-hosted headless content management framework built with Laravel and Vue.js.</p>'],
            ['title' => 'Contact', 'slug' => 'contact', 'content' => '<h2>Contact Us</h2><p>Reach the team at <strong>hello@cms.example</strong>.</p>'],
        ] as $i => $data) {
            $this->addContent($project, $c['pages'], $data + ['author' => 'Admin'], published: true, daysAgo: 20 - $i);
        }

        /* --- Categories / Tags --- */
        $categories = [];
        foreach ([
            ['News', 'news', 'The latest announcements, product updates and community news from the CMS ecosystem.'],
            ['Tutorials', 'tutorials', 'Step-by-step guides and practical walkthroughs covering Laravel, Vue and headless CMS development.'],
            ['Reviews', 'reviews', 'In-depth reviews of developer tools, editors, packages and services we use every day.'],
        ] as $i => [$title, $url, $description]) {
            $categories[$title] = $this->addContent($project, $c['categories'], ['title' => $title, 'slug' => $url, 'description' => $description], published: true, daysAgo: 19 - $i)->id;
        }

        $tags = [];
        foreach (['Laravel', 'Vue', 'PHP', 'CMS', 'Security', 'Testing', 'Design'] as $i => $tag) {
            $tags[$tag] = $this->addContent($project, $c['tags'], ['tag' => $tag], published: true, daysAgo: 17 - $i)->id;
        }

        /* --- Articles --- */
        $articleData = [
            [
                'title' => 'Getting Started with Aine CMS', 'slug' => 'getting-started-with-aine-cms', 'excerpt' => 'A gentle introduction to Aine: install it, create your first project from the CMS Template and serve content through the REST API.',
                'content' => '<h2>What is Aine?</h2><p>Aine is a <strong>self-hosted headless CMS</strong> built with Laravel and Vue.js.</p><h3>Key concepts</h3><ol><li><strong>Projects</strong> — isolated content spaces.</li><li><strong>Collections</strong> — your content models (Articles, Categories, Tags...).</li><li><strong>Fields</strong> — 15 field types from simple text to media and relations.</li></ol>',
                'featured-image' => $media['cover-1.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$tags['CMS'], 'slider' => 1, 'featured' => 1,
            ],
            [
                'title' => 'Building a Blog Frontend with Vue 3', 'slug' => 'building-a-blog-frontend-with-vue-3', 'excerpt' => 'Consume the Aine content API from a Vue 3 single page app using the domain-whitelist authentication method.',
                'content' => '<h2>Frontend setup</h2><p>Point your Vue router at the project and fetch content from <code>/api/project/cms/articles</code>.</p><p>The <em>featured-image</em> media field, <em>category</em> and <em>author</em> relations are all resolved by the API.</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Jane Doe',
                'tags' => (string) $tags['Vue'], 'slider' => 1, 'recommended' => 1,
            ],
            [
                'title' => 'Aine 2.0 Release Notes: What\u2019s New', 'slug' => 'aine-2-0-release-notes-what-s-new', 'excerpt' => 'A quick tour of the 2.0 release: multi-locale projects, domain whitelist API, webhooks with signed payloads and more.',
                'content' => '<h2>Release highlights</h2><ul><li>Multi-locale content in every project.</li><li>Domain whitelist authentication for pure frontend apps.</li><li>Signed webhooks on every content event.</li></ul>',
                'featured-image' => $media['cover-3.jpg'], 'category' => $categories['News'], 'author' => 'John Smith',
                'tags' => $tags['PHP'].','.$tags['CMS'], 'slider' => 1, 'featured' => 1,
            ],
            [
                'title' => 'Top 10 Laravel Packages for 2026', 'slug' => 'top-10-laravel-packages-for-2026', 'excerpt' => 'Our hand-picked list of the most useful Laravel packages this year, from permissions to webhook delivery.',
                'content' => '<h2>The list</h2><ol><li>Spatie Laravel Permission</li><li>Spatie Laravel Webhook Server</li><li>Laravel Sanctum</li><li>Intervention Image</li></ol>',
                'featured-image' => $media['cover-4.jpg'], 'category' => $categories['Reviews'], 'author' => 'John Smith',
                'tags' => $tags['Laravel'].','.$tags['PHP'], 'recommended' => 1,
            ],
            [
                'title' => 'Anatomy of a Headless CMS (draft)', 'slug' => 'anatomy-of-a-headless-cms-draft', 'excerpt' => 'Draft article: not published yet and therefore hidden from the public API.',
                'content' => '<h2>Under construction</h2><p>This article is a <strong>draft</strong> — it has no <em>published_at</em> timestamp.</p>',
                'featured-image' => null, 'category' => $categories['News'], 'author' => 'Jane Doe',
                'tags' => (string) $tags['CMS'],
            ],
            [
                'title' => 'Hello World (trashed)', 'slug' => 'hello-world-trashed', 'excerpt' => 'This article lives in the recycle bin — restore it from the admin panel.',
                'content' => '<p>A very old article that has been moved to trash.</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $categories['Reviews'], 'author' => 'John Smith',
                'tags' => (string) $tags['Laravel'],
            ],
            [
                'title' => 'Mastering Eloquent Relationships', 'slug' => 'mastering-eloquent-relationships', 'excerpt' => 'Has-many, belongs-to, morphs — a practical tour of Eloquent relationship patterns with real-world examples.',
                'content' => '<h2>Relationships that scale</h2><p>Eloquent makes related data a joy. We cover <strong>hasMany</strong>, <strong>belongsTo</strong> and polymorphic relations, plus common pitfalls like N+1 queries.</p><pre>Article::with(\'author\', \'tags\')->get();</pre>',
                'featured-image' => $media['cover-3.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$tags['PHP'], 'featured' => 1,
            ],
            [
                'title' => 'Vue 3 Composables in Practice', 'slug' => 'vue-3-composables-in-practice', 'excerpt' => 'Extract and reuse stateful logic with Vue 3 composables — from a simple useFetch to a full feature module.',
                'content' => '<h2>Composables everywhere</h2><p>Composables are the heart of Vue 3. Learn how to structure <code>useFetch</code>, <code>useLocalStorage</code> and feature-level composables that keep components clean.</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Alex Rivera',
                'tags' => (string) $tags['Vue'], 'slider' => 1,
            ],
            [
                'title' => 'Community Spotlight: Aine in Production', 'slug' => 'community-spotlight-aine-in-production', 'excerpt' => 'Three teams share how they run Aine in production — traffic, multi-project setups and lessons learned.',
                'content' => '<h2>Real deployments</h2><p>From a news portal to a SaaS documentation site, hear how teams keep hundreds of collections fast and reliable.</p>',
                'featured-image' => $media['cover-1.jpg'], 'category' => $categories['News'], 'author' => 'John Smith',
                'tags' => (string) $tags['CMS'], 'featured' => 1,
            ],
            [
                'title' => 'Security Best Practices for Headless CMS', 'slug' => 'security-best-practices-for-headless-cms', 'excerpt' => 'Tokens, CORS, whitelists and rate limiting — a checklist for exposing your content API safely.',
                'content' => '<h2>Lock it down</h2><ul><li>Rotate API tokens regularly.</li><li>Use domain whitelists for browser clients.</li><li>Never log secrets.</li></ul>',
                'featured-image' => $media['cover-4.jpg'], 'category' => $categories['News'], 'author' => 'Jane Doe',
                'tags' => $tags['PHP'].','.$tags['Security'], 'recommended' => 1,
            ],
            [
                'title' => 'Review: The Best Code Editors of 2026', 'slug' => 'review-best-code-editors-2026', 'excerpt' => 'We put five editors through a month of daily PHP and Vue work. One clear winner emerged.',
                'content' => '<h2>The contenders</h2><ol><li>VS Code</li><li>PhpStorm</li><li>Neovim</li><li>Sublime Text</li><li>Zed</li></ol><p>Spoiler: the winner is the one you already know.</p>',
                'featured-image' => $media['cover-3.jpg'], 'category' => $categories['Reviews'], 'author' => 'John Smith',
                'tags' => $tags['PHP'].','.$tags['Design'], 'recommended' => 1,
            ],
            [
                'title' => 'Designing Content Models that Scale', 'slug' => 'designing-content-models-that-scale', 'excerpt' => 'Collections, fields and relations are your schema. Design them like a database — because they are one.',
                'content' => '<h2>Model-first thinking</h2><p>Start with the content, not the UI. Use relations instead of repeating fields, and keep one source of truth for every concept.</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Alex Rivera',
                'tags' => $tags['CMS'].','.$tags['Design'], 'slider' => 1,
            ],
            [
                'title' => 'Testing Laravel Applications', 'slug' => 'testing-laravel-applications', 'excerpt' => 'Feature tests, Pest or PHPUnit, factories and a CI pipeline that actually catches regressions.',
                'content' => '<h2>Ship with confidence</h2><p>Write tests for the behaviour your users depend on. We show a feature test for the content API from request to response.</p>',
                'featured-image' => $media['cover-4.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$tags['Testing'], 'featured' => 1,
            ],
            [
                'title' => 'What\u2019s New in PHP 8.5', 'slug' => 'what-s-new-in-php-8-5', 'excerpt' => 'Property hooks are here — plus the smaller quality-of-life improvements that make PHP 8.5 the best version yet.',
                'content' => '<h2>PHP keeps getting better</h2><p>Property hooks, <code>new</code> without parentheses in more places, and a faster engine. Upgrade guides included.</p>',
                'featured-image' => $media['cover-1.jpg'], 'category' => $categories['News'], 'author' => 'John Smith',
                'tags' => (string) $tags['PHP'], 'slider' => 1,
            ],
            [
                'title' => 'Review: Vue DevTools Essentials', 'slug' => 'review-vue-devtools-essentials', 'excerpt' => 'The timeline, component inspector and pinia panel — the DevTools features you are not using yet.',
                'content' => '<h2>Debug faster</h2><p>DevTools is more than a component tree. Master the timeline tab and the router panel to halve your debugging time.</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $categories['Reviews'], 'author' => 'Alex Rivera',
                'tags' => (string) $tags['Vue'], 'recommended' => 1,
            ],
            [
                'title' => 'A Practical Guide to API Design', 'slug' => 'a-practical-guide-to-api-design', 'excerpt' => 'REST, filtering, pagination and versioning — design an API your frontend team will thank you for.',
                'content' => '<h2>Design for consumers</h2><p>Consistent errors, cursor pagination, sparse fieldsets and meaningful status codes: the boring details that matter most.</p>',
                'featured-image' => $media['cover-3.jpg'], 'category' => $categories['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$tags['Security'], 'featured' => 1,
            ],
        ];

        $articleIds = [];
        foreach ($articleData as $i => $data) {
            $published = $i !== 4 && $i !== 5;
            $trashed = $i === 5;
            $articleIds[] = $this->addContent($project, $c['articles'], $data, published: $published, trashed: $trashed, daysAgo: max(10 - $i * 2, 1))->id;
        }

        /* --- Chinese structure (categories + translated tags) --- */
        $zhCategories = [];
        foreach ([
            ['新闻', 'news-zh', '来自 CMS 生态的最新公告、产品更新与社区动态。'],
            ['教程', 'tutorials-zh', '覆盖 Laravel、Vue 与无头 CMS 开发的循序渐进指南与实战教程。'],
            ['评测', 'reviews-zh', '对我们日常使用的开发工具、编辑器、扩展包与服务的深度评测。'],
        ] as $i => [$zhTitle, $zhUrl, $zhDescription]) {
            $zhCategories[$zhTitle] = $this->addContent($project, $c['categories'], ['title' => $zhTitle, 'slug' => $zhUrl, 'description' => $zhDescription], published: true, daysAgo: 19 - $i, locale: 'zh')->id;
        }
        $zhCategoryByEn = [
            'News' => $zhCategories['新闻'], 'Tutorials' => $zhCategories['教程'], 'Reviews' => $zhCategories['评测'],
        ];

        // Brand tags (Laravel/Vue/PHP/CMS) stay shared; translated tags get zh rows.
        $zhTags = [];
        foreach (['安全' => 'Security', '测试' => 'Testing', '设计' => 'Design'] as $zhTag => $enTag) {
            $zhTags[$zhTag] = $this->addContent($project, $c['tags'], ['tag' => $zhTag], published: true, daysAgo: 16, locale: 'zh')->id;
        }
        $zhTagByEn = ['Security' => $zhTags['安全'], 'Testing' => $zhTags['测试'], 'Design' => $zhTags['设计']];

        /* --- Chinese articles (10) --- */
        foreach ([
            [
                'title' => 'Aine CMS 入门指南', 'slug' => 'aine-cms-getting-started-zh',
                'excerpt' => '本文介绍如何安装 Aine、用 CMS 模板创建第一个项目，并通过 REST API 提供内容。',
                'content' => '<h2>什么是 Aine？</h2><p>Aine 是一个用 Laravel 和 Vue.js 构建的<strong>自托管无头 CMS</strong>。</p><h3>核心概念</h3><ol><li><strong>项目</strong>——相互隔离的内容空间。</li><li><strong>集合</strong>——内容模型（文章、分类、作者……）。</li><li><strong>字段</strong>——从文本到媒体、关联共 15 种字段类型。</li></ol><p>本文是<em>中文版</em>内容，通过切换前台语言即可看到。</p>',
                'featured-image' => $media['cover-1.jpg'], 'category' => $zhCategoryByEn['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$tags['CMS'], 'slider' => 1, 'featured' => 1,
            ],
            [
                'title' => '2026 年十大 Laravel 扩展包', 'slug' => 'top-10-laravel-packages-2026-zh',
                'excerpt' => '我们精选了今年最实用的 Laravel 扩展包：从权限管理到 Webhook 投递，一应俱全。',
                'content' => '<h2>榜单</h2><ol><li>Spatie Laravel Permission（权限管理）</li><li>Spatie Laravel Webhook Server（Webhook 投递）</li><li>Laravel Sanctum（API 认证）</li><li>Intervention Image（图片处理）</li></ol><p>一篇<em>评测</em>分类的中文文章，配有封面图。</p>',
                'featured-image' => $media['cover-4.jpg'], 'category' => $zhCategoryByEn['Reviews'], 'author' => 'John Smith',
                'tags' => $tags['Laravel'].','.$tags['PHP'], 'recommended' => 1,
            ],
            [
                'title' => '精通 Eloquent 关联关系', 'slug' => 'mastering-eloquent-relationships-zh',
                'excerpt' => '一对多、反向关联与多态关联——带你实战 Eloquent 关联模式。',
                'content' => '<h2>可扩展的关联</h2><p>Eloquent 让关联数据变得轻松。本文介绍 <strong>hasMany</strong>、<strong>belongsTo</strong> 和多态关联，以及 N+1 查询等常见陷阱。</p><pre>Article::with(\'author\', \'tags\')->get();</pre>',
                'featured-image' => $media['cover-3.jpg'], 'category' => $zhCategoryByEn['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$tags['PHP'], 'featured' => 1,
            ],
            [
                'title' => 'PHP 8.5 新特性速览', 'slug' => 'what-s-new-in-php-8-5-zh',
                'excerpt' => '属性钩子来了——还有让 PHP 8.5 成为史上最好版本的诸多改进。',
                'content' => '<h2>PHP 越来越好</h2><p>属性钩子（property hooks）、更多场景的 <code>new</code> 简写，以及更快的引擎。</p>',
                'featured-image' => $media['cover-1.jpg'], 'category' => $zhCategoryByEn['News'], 'author' => 'John Smith',
                'tags' => (string) $tags['PHP'], 'slider' => 1,
            ],
            [
                'title' => 'Vue 3 组合式函数实战', 'slug' => 'vue-3-composables-in-practice-zh',
                'excerpt' => '用 Vue 3 组合式函数提取和复用有状态的逻辑——从 useFetch 到完整的功能模块。',
                'content' => '<h2>组合式函数无处不在</h2><p>组合式函数是 Vue 3 的核心。学习如何编写 <code>useFetch</code>、<code>useLocalStorage</code> 以及让组件保持简洁的特性级组合式函数。</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $zhCategoryByEn['Tutorials'], 'author' => 'Alex Rivera',
                'tags' => (string) $tags['Vue'], 'slider' => 1,
            ],
            [
                'title' => '社区聚焦：Aine 在生产环境', 'slug' => 'community-spotlight-aine-in-production-zh',
                'excerpt' => '三个团队分享他们如何在生产环境运行 Aine——流量、多项目架构与经验教训。',
                'content' => '<h2>真实部署案例</h2><p>从新闻门户到 SaaS 文档站，了解团队如何让数百个集合保持快速与稳定。</p>',
                'featured-image' => $media['cover-1.jpg'], 'category' => $zhCategoryByEn['News'], 'author' => 'John Smith',
                'tags' => (string) $tags['CMS'], 'featured' => 1,
            ],
            [
                'title' => '无头 CMS 安全最佳实践', 'slug' => 'security-best-practices-headless-cms-zh',
                'excerpt' => '令牌、CORS、白名单与限流——安全暴露内容 API 的检查清单。',
                'content' => '<h2>加固你的系统</h2><ul><li>定期轮换 API 令牌。</li><li>为浏览器客户端使用域名白名单。</li><li>绝不记录密钥。</li></ul>',
                'featured-image' => $media['cover-4.jpg'], 'category' => $zhCategoryByEn['News'], 'author' => 'Jane Doe',
                'tags' => $tags['PHP'].','.$zhTagByEn['Security'], 'recommended' => 1,
            ],
            [
                'title' => '设计可扩展的内容模型', 'slug' => 'designing-content-models-that-scale-zh',
                'excerpt' => '集合、字段与关联就是你的数据结构——像设计数据库一样设计它们。',
                'content' => '<h2>模型优先的思考</h2><p>从内容出发而非界面。用关联代替重复字段，让每个概念只有一个事实来源。</p>',
                'featured-image' => $media['cover-2.jpg'], 'category' => $zhCategoryByEn['Tutorials'], 'author' => 'Alex Rivera',
                'tags' => $tags['CMS'].','.$zhTagByEn['Design'], 'slider' => 1,
            ],
            [
                'title' => 'Laravel 应用测试', 'slug' => 'testing-laravel-applications-zh',
                'excerpt' => '功能测试、Pest 或 PHPUnit、工厂与真正能捕获回归的 CI 流水线。',
                'content' => '<h2>放心交付</h2><p>为用户依赖的行为编写测试。我们展示从请求到响应的内容 API 功能测试。</p>',
                'featured-image' => $media['cover-4.jpg'], 'category' => $zhCategoryByEn['Tutorials'], 'author' => 'Jane Doe',
                'tags' => $tags['Laravel'].','.$zhTagByEn['Testing'], 'featured' => 1,
            ],
            [
                'title' => '评测：2026 年最佳代码编辑器', 'slug' => 'review-best-code-editors-2026-zh',
                'excerpt' => '我们让五款编辑器经历了一个月的日常 PHP 与 Vue 开发，最终胜者非常明确。',
                'content' => '<h2>参赛选手</h2><ol><li>VS Code</li><li>PhpStorm</li><li>Neovim</li><li>Sublime Text</li><li>Zed</li></ol><p>剧透：胜者就是你最熟悉的那款。</p>',
                'featured-image' => $media['cover-3.jpg'], 'category' => $zhCategoryByEn['Reviews'], 'author' => 'John Smith',
                'tags' => $tags['PHP'].','.$zhTagByEn['Design'], 'recommended' => 1,
            ],
        ] as $i => $data) {
            $this->addContent($project, $c['articles'], $data, published: true, daysAgo: max(8 - $i, 1), locale: 'zh');
        }

        /* --- Comments (relation to articles) --- */
        $comments = [
            ['name' => 'Alice Brown', 'e-mail' => 'alice@example.com', 'comment' => 'Great article! The installation steps worked perfectly on my machine.', 'article' => $articleIds[0]],
            ['name' => 'Bob Wilson', 'e-mail' => 'bob@example.com', 'comment' => 'Looking forward to the next part. Could you cover API tokens in more detail?', 'article' => $articleIds[0]],
            ['name' => 'Carol Davis', 'e-mail' => 'carol@example.com', 'comment' => 'The Vue 3 example rendered beautifully. Thanks for the code snippet!', 'article' => $articleIds[1]],
            ['name' => 'Dave Miller', 'e-mail' => 'dave@example.com', 'comment' => 'Thanks for the package list — Spatie Permission is a lifesaver.', 'article' => $articleIds[3]],
        ];
        $comments[] = ['name' => 'Eva Lindqvist', 'e-mail' => 'eva@example.com', 'comment' => 'The Eloquent guide finally made relationships click for me. Thank you!', 'article' => $articleIds[6]];
        $comments[] = ['name' => 'Omar Farouk', 'e-mail' => 'omar@example.com', 'comment' => 'Composables article is gold — my components got so much cleaner.', 'article' => $articleIds[7]];
        $comments[] = ['name' => 'Grace Liu', 'e-mail' => 'grace@example.com', 'comment' => 'The security checklist caught two things we were doing wrong. Highly recommended.', 'article' => $articleIds[9]];
        $comments[] = ['name' => 'Peter Novak', 'e-mail' => 'peter@example.com', 'comment' => 'PHP 8.5 property hooks are a game changer for value objects.', 'article' => $articleIds[13]];
        $comments[] = ['name' => 'Mia Tanaka', 'e-mail' => 'mia@example.com', 'comment' => 'Loved the DevTools review — the timeline tab is now my favourite.', 'article' => $articleIds[14]];
        $comments[] = ['name' => 'Leo Martins', 'e-mail' => 'leo@example.com', 'comment' => 'The API design guide should be required reading for every backend team.', 'article' => $articleIds[15]];

        foreach ($comments as $i => $data) {
            $this->addContent($project, $c['comments'], $data, published: true, daysAgo: max(8 - $i, 1));
        }

        /* --- Globals --- */
        foreach ([
            ['label' => 'site-name', 'value' => 'CMS'],
            ['label' => 'site-description', 'value' => 'A complete Content Management System powered by Aine CMS'],
            ['label' => 'footer-text', 'value' => '© 2026 Content Management System — built with Aine CMS'],
            ['label' => 'social-twitter', 'value' => '@ainecms'],
        ] as $i => $data) {
            $this->addContent($project, $c['globals'], $data, published: true, daysAgo: 15 - $i);
        }

        /* --- Project translations (zh) --- */
        $this->seedProjectTranslations($project, [
            'Pages' => '页面', 'Articles' => '文章', 'Categories' => '分类', 'Author' => '作者',
            'Tags' => '标签', 'Comments' => '评论', 'Globals' => '全局',
            'Title' => '标题', 'Path' => '路径', 'Content' => '内容', 'Description' => '描述',
            'Excerpt' => '摘要', 'Featured Image' => '特色图片', 'Category' => '分类', 'Author' => '作者',
            'Slider' => '幻灯片', 'Featured' => '精选', 'Recommended' => '推荐',
            'Name' => '姓名', 'Info' => '简介', 'Avatar' => '头像',
            'Facebook' => '脸书', 'Instagram' => 'Instagram', 'Twitter' => '推特', 'Linkedin' => '领英',
            'Tag' => '标签', 'E-mail' => '电子邮箱', 'Comment' => '评论', 'Article' => '文章',
            'Label' => '标签名', 'Value' => '值',
        ]);

        $this->seedProjectFeatures($project, $c['articles']);
    }

    /* ------------------------------------------------------------------ */
    /* Demo Directory (Business Directory template)                        */
    /* ------------------------------------------------------------------ */

    protected function seedDirectoryProject(): void
    {
        $project = $this->createProject([
            'name' => 'Business Directory',
            'slug' => 'business-directory',
            'description' => 'A complete Business Directory — listings with categories, tags, locations, reviews, logos and galleries.',
            'default_locale' => 'en',
            'locales' => 'en,zh',
            'disk' => 'local',
            'public_api' => true,
            'domain_whitelist' => [
                config('app.url'),
                'http://localhost:3000',
                'http://localhost:5173',
            ],
        ]);

        $c = ProjectTemplates::apply($project, ProjectTemplates::get(ProjectTemplates::BUSINESS_DIRECTORY));

        $media = $this->seedMedia($project, [
            ['logo-1.png', 'GS', [220, 38, 38], [249, 115, 22], 400, 400, true],
            ['logo-2.png', 'CL', [147, 51, 234], [79, 70, 229], 400, 400, true],
            ['logo-3.png', 'HV', [2, 132, 199], [14, 165, 233], 400, 400, true],
            ['logo-4.png', 'CF', [22, 163, 74], [132, 204, 22], 400, 400, true],
            ['gallery-1.jpg', 'Restaurant', [249, 115, 22], [234, 88, 12], 1200, 630, false],
            ['gallery-2.jpg', 'Cafe', [168, 85, 247], [99, 102, 241], 1200, 630, false],
            ['gallery-3.jpg', 'Hotel', [14, 165, 233], [6, 95, 70], 1200, 630, false],
            ['gallery-4.jpg', 'Shop', [236, 72, 153], [124, 58, 237], 1200, 630, false],
        ]);

        /* --- Categories / Tags / Locations --- */
        $categories = [];
        foreach ([
            ['Restaurants', 'restaurants', 'From fine dining to casual bistros — places worth booking a table for.'],
            ['Cafes', 'cafes', 'Coffee, pastries and cosy corners for work, meetings or a slow morning.'],
            ['Hotels', 'hotels', 'Boutique stays, grand classics and everything in between for your next trip.'],
            ['Shopping', 'shopping', 'Independent stores and local favourites for books, plants, electronics and more.'],
            ['Services', 'services', 'Gyms, repairs, tours and every service that keeps the city running.'],
            ['Health & Beauty', 'health-beauty', 'Spas, salons and studios to help you relax, refresh and feel your best.'],
            ['Automotive', 'automotive', 'Garages, showrooms and care for every kind of vehicle.'],
        ] as $i => [$title, $url, $description]) {
            $categories[$title] = $this->addContent($project, $c['categories'], ['title' => $title, 'slug' => $url, 'description' => $description], published: true, daysAgo: 30 - $i)->id;
        }

        $tags = [];
        foreach (['Delivery', 'Pet-Friendly', 'Outdoor Seating', 'Free WiFi', 'Parking', '24-Hours', 'Family-Friendly', 'Budget', 'Luxury', 'Takeaway', 'Gluten-Free', 'Kids Welcome', 'Senior Discount'] as $i => $tag) {
            $tags[$tag] = $this->addContent($project, $c['tags'], ['tag' => $tag], published: true, daysAgo: 29 - $i)->id;
        }

        $locations = [];
        foreach ([['Downtown', 'downtown'], ['Riverside', 'riverside'], ['Old Town', 'old-town'], ['Northside', 'northside'], ['West End', 'west-end'], ['Airport District', 'airport-district']] as $i => [$name, $url]) {
            $locations[$name] = $this->addContent($project, $c['locations'], ['name' => $name, 'slug' => $url], published: true, daysAgo: 28 - $i)->id;
        }

        /* --- Listings --- */
        $listingData = [
            ['The Golden Spoon', 'the-golden-spoon', 'Award-winning fine dining restaurant known for its seasonal tasting menus.', 'Restaurants', 'Downtown', ['Luxury', 'Outdoor Seating'], 'logo-1.png', ['gallery-1.jpg', 'gallery-4.jpg'], '+1-555-0101', 'hello@thegoldenspoon.example', 'https://thegoldenspoon.example', '12 Main Street, Downtown', 'Mon–Sun 11:00 – 23:00', '$$$', 1],
            ['Café Lumière', 'cafe-lumiere', 'Cozy neighbourhood café with artisan coffee and homemade pastries.', 'Cafes', 'Downtown', ['Free WiFi', 'Outdoor Seating'], 'logo-2.png', ['gallery-2.jpg'], '+1-555-0102', 'hello@cafelumiere.example', 'https://cafelumiere.example', '45 Market Lane, Downtown', 'Mon–Sat 07:30 – 18:00', '$$', 1],
            ['Riverside Bistro', 'riverside-bistro', 'Casual bistro by the river with a seasonal menu and local wines.', 'Restaurants', 'Riverside', ['Outdoor Seating', 'Family-Friendly'], 'logo-1.png', ['gallery-1.jpg'], '+1-555-0103', 'info@riversidebistro.example', 'https://riversidebistro.example', '3 Riverside Walk', 'Tue–Sun 12:00 – 22:00', '$$$', 0],
            ['Harbor View Hotel', 'harbor-view-hotel', 'Boutique hotel with stunning harbor views, spa and rooftop bar.', 'Hotels', 'Riverside', ['Luxury', 'Parking', 'Free WiFi'], 'logo-3.png', ['gallery-3.jpg', 'gallery-1.jpg'], '+1-555-0104', 'stay@harborview.example', 'https://harborview.example', '1 Harbor Promenade', 'Open 24 hours', '$$$$', 1],
            ['Old Town Books', 'old-town-books', 'Independent bookstore with rare finds, readings and a tiny café corner.', 'Shopping', 'Old Town', ['Free WiFi', 'Budget'], 'logo-4.png', ['gallery-4.jpg'], '+1-555-0105', 'books@oldtown.example', 'https://oldtownbooks.example', '88 Heritage Street, Old Town', 'Mon–Sun 10:00 – 19:00', '$', 0],
            ['Bloom & Basil', 'bloom-and-basil', 'Plant shop and café — grab a flat white among the greenery.', 'Cafes', 'Old Town', ['Pet-Friendly', 'Free WiFi'], 'logo-2.png', ['gallery-2.jpg', 'gallery-4.jpg'], '+1-555-0106', 'hi@bloombasil.example', 'https://bloombasil.example', '21 Garden Court, Old Town', 'Mon–Sat 09:00 – 17:00', '$$', 0],
            ['City Fitness Gym', 'city-fitness-gym', '24-hour gym with modern equipment, classes and personal training.', 'Services', 'Northside', ['24-Hours', 'Parking'], 'logo-4.png', ['gallery-4.jpg'], '+1-555-0107', 'team@cityfitness.example', 'https://cityfitness.example', '500 North Avenue, Northside', 'Open 24 hours', '$$', 1],
            ['Quick Fix Repairs', 'quick-fix-repairs', 'Same-day electronics and appliance repair service with pickup.', 'Services', 'Northside', ['Delivery', 'Budget'], 'logo-4.png', ['gallery-4.jpg'], '+1-555-0108', 'fix@quickfix.example', 'https://quickfix.example', '77 Industrial Road, Northside', 'Mon–Fri 08:00 – 18:00', '$', 0],
            ['The Grand Hotel', 'the-grand-hotel', 'Classic grand hotel in the heart of Downtown with ballrooms and concierge.', 'Hotels', 'Downtown', ['Luxury', 'Parking', 'Family-Friendly'], 'logo-3.png', ['gallery-3.jpg'], '+1-555-0109', 'reserve@grandhotel.example', 'https://grandhotel.example', '1 Grand Boulevard, Downtown', 'Open 24 hours', '$$$$', 1],
            ['Sunrise Bakery', 'sunrise-bakery', 'Family bakery — fresh bread, cakes and pastries from 6am.', 'Cafes', 'Northside', ['Budget', 'Delivery', 'Family-Friendly'], 'logo-2.png', ['gallery-2.jpg'], '+1-555-0110', 'hello@sunrisebakery.example', 'https://sunrisebakery.example', '9 Dawn Street, Northside', 'Mon–Sun 06:00 – 16:00', '$', 0],
            ['The Olive Tree', 'the-olive-tree', 'Mediterranean kitchen in West End — wood-fired oven, mezze and long summer evenings.', 'Restaurants', 'West End', ['Family-Friendly', 'Outdoor Seating'], 'logo-1.png', ['gallery-1.jpg', 'gallery-4.jpg'], '+1-555-0111', 'hello@olivetree.example', 'https://theolivetree.example', '14 Vine Street, West End', 'Tue–Sun 17:00 – 23:00', '$$', 0],
            ['Sushi Zen', 'sushi-zen', 'Omakase-focused sushi bar with a daily market catch and a curated sake list.', 'Restaurants', 'Downtown', ['Delivery', 'Luxury'], 'logo-2.png', ['gallery-1.jpg'], '+1-555-0112', 'reserve@sushizen.example', 'https://sushizen.example', '22 Sakura Lane, Downtown', 'Mon–Sat 12:00 – 22:00', '$$$', 0],
            ['Brew & Bean Roastery', 'brew-and-bean-roastery', 'Small-batch coffee roastery with tasting flights and beans to take home.', 'Cafes', 'West End', ['Takeaway', 'Free WiFi'], 'logo-4.png', ['gallery-2.jpg'], '+1-555-0113', 'brew@brewbean.example', 'https://brewbean.example', '5 Roastery Row, West End', 'Mon–Sun 08:00 – 17:00', '$', 0],
            ['The Velvet Room', 'the-velvet-room', 'Boutique hotel in a restored Old Town mansion — jazz bar and a hidden courtyard.', 'Hotels', 'Old Town', ['Luxury', 'Parking'], 'logo-3.png', ['gallery-3.jpg', 'gallery-1.jpg'], '+1-555-0114', 'stay@velvetroom.example', 'https://velvetroom.example', '2 Mansion Court, Old Town', 'Open 24 hours', '$$$$', 1],
            ['Coastal Spa & Wellness', 'coastal-spa-wellness', 'Seaside spa with thermal pools, massage suites and a calm tea lounge.', 'Health & Beauty', 'Riverside', ['Parking', 'Family-Friendly'], 'logo-4.png', ['gallery-4.jpg'], '+1-555-0115', 'hello@coastalspa.example', 'https://coastalspa.example', '11 Shoreline Drive, Riverside', 'Mon–Sun 10:00 – 20:00', '$$$', 1],
            ['Luxe Nails Studio', 'luxe-nails-studio', 'Premium manicure and pedicure studio with vegan products by appointment.', 'Health & Beauty', 'Downtown', ['Free WiFi', 'Senior Discount'], 'logo-2.png', ['gallery-4.jpg'], '+1-555-0116', 'book@luxenails.example', 'https://luxenails.example', '33 Boutique Arcade, Downtown', 'Tue–Sat 10:00 – 19:00', '$$', 0],
            ['Apex Auto Care', 'apex-auto-care', 'Full-service garage — diagnostics, brakes and same-day oil changes.', 'Automotive', 'Northside', ['Parking', '24-Hours'], 'logo-4.png', ['gallery-4.jpg'], '+1-555-0117', 'service@apexauto.example', 'https://apexauto.example', '88 Motorway Row, Northside', 'Mon–Sat 08:00 – 20:00', '$$$', 0],
            ['Downtown Auto Gallery', 'downtown-auto-gallery', 'Pre-owned luxury and classic cars with a showroom in the old tram depot.', 'Automotive', 'Downtown', ['Luxury', 'Parking'], 'logo-3.png', ['gallery-3.jpg', 'gallery-4.jpg'], '+1-555-0118', 'sales@autogallery.example', 'https://autogallery.example', '1 Tram Depot Square, Downtown', 'Mon–Sun 10:00 – 19:00', '$$$$', 1],
            ['Green Leaf Market', 'green-leaf-market', 'Organic produce, pantry staples and a zero-waste refill station.', 'Shopping', 'West End', ['Budget', 'Kids Welcome'], 'logo-4.png', ['gallery-4.jpg'], '+1-555-0119', 'hello@greenleaf.example', 'https://greenleaf.example', '17 Orchard Lane, West End', 'Mon–Sun 09:00 – 21:00', '$', 0],
            ['TechHouse Electronics', 'techhouse-electronics', 'Wide range of consumer electronics with expert in-store advice.', 'Shopping', 'Airport District', ['Free WiFi', 'Parking'], 'logo-1.png', ['gallery-4.jpg', 'gallery-3.jpg'], '+1-555-0120', 'store@techhouse.example', 'https://techhouse.example', '44 Terminal Boulevard, Airport District', 'Mon–Sun 10:00 – 20:00', '$$$', 0],
            ['The Daily Grind', 'the-daily-grind', 'Coffee, toasties and all-day breakfast for travellers — open from 5am.', 'Cafes', 'Airport District', ['Takeaway', 'Gluten-Free'], 'logo-2.png', ['gallery-2.jpg'], '+1-555-0121', 'grind@dailygrind.example', 'https://dailygrind.example', '2 Departure Way, Airport District', 'Mon–Sun 05:00 – 22:00', '$', 1],
            ['Harbor Marina Tours', 'harbor-marina-tours', 'Boat tours, sunset cruises and kayak rentals from the riverside marina.', 'Services', 'Riverside', ['Family-Friendly', 'Parking'], 'logo-3.png', ['gallery-1.jpg', 'gallery-3.jpg'], '+1-555-0122', 'tours@harbormarina.example', 'https://harbormarina.example', '3 Marina Pier, Riverside', 'Daily 09:00 – 18:00', '$$$', 0],
        ];

        $listingIds = [];
        foreach ($listingData as $i => [$title, $url, $desc, $cat, $loc, $tagList, $logo, $gallery, $phone, $email, $website, $address, $hours, $price, $featured]) {
            $tagIds = [];
            foreach ($tagList as $tagName => $_) {
                if (isset($tags[$tagName])) {
                    $tagIds[] = $tags[$tagName];
                }
            }
            $galleryIds = array_map(fn ($g) => $media[$g], $gallery);

            $listingIds[] = $this->addContent($project, $c['listings'], [
                'title' => $title, 'slug' => $url, 'description' => $desc,
                'category' => $categories[$cat], 'location' => $locations[$loc],
                'tags' => implode(',', $tagIds),
                'logo' => $media[$logo], 'gallery' => implode(',', $galleryIds),
                'phone' => $phone, 'email' => $email, 'website' => $website,
                'address' => $address, 'opening-hours' => $hours, 'price-range' => $price,
                'featured' => $featured,
            ], published: true, daysAgo: max(20 - $i, 1))->id;
        }

        /* --- Reviews (relation to listings) --- */
        $reviews = [
            ['name' => 'Emily Watson', 'rating' => 5, 'review' => 'Outstanding food and impeccable service. The tasting menu is unforgettable!', 'listing' => $listingIds[0]],
            ['name' => 'Michael Chen', 'rating' => 4, 'review' => 'Great ambiance and coffee. A bit crowded on weekends.', 'listing' => $listingIds[1]],
            ['name' => 'Sarah Kim', 'rating' => 5, 'review' => 'Beautiful rooms and the harbor view is worth every penny.', 'listing' => $listingIds[3]],
            ['name' => 'Tom Alvarez', 'rating' => 4, 'review' => 'Cozy little bookshop with a wonderful selection.', 'listing' => $listingIds[4]],
            ['name' => 'Linda Grey', 'rating' => 5, 'review' => 'The staff remembered my order after the first visit. Love this place!', 'listing' => $listingIds[5]],
            ['name' => 'James Wright', 'rating' => 4, 'review' => 'Well-equipped gym open 24/7. Clean facilities.', 'listing' => $listingIds[6]],
            ['name' => 'Anna Novak', 'rating' => 5, 'review' => 'Fixed my laptop the same day. Fair prices and friendly service.', 'listing' => $listingIds[7]],
            ['name' => 'Robert Hale', 'rating' => 5, 'review' => 'A true classic — the afternoon tea is a must.', 'listing' => $listingIds[8]],
        ];
        $reviews[] = ['name' => 'Chloe Bennett', 'rating' => 5, 'review' => 'The tasting flights are fantastic and the staff know their beans.', 'listing' => $listingIds[10]];
        $reviews[] = ['name' => 'David Osei', 'rating' => 4, 'review' => 'Fresh omakase at a fair price. Book ahead — it fills up fast.', 'listing' => $listingIds[11]];
        $reviews[] = ['name' => 'Nina Petrova', 'rating' => 5, 'review' => 'The courtyard is magical at night. Rooms are spotless.', 'listing' => $listingIds[13]];
        $reviews[] = ['name' => 'Hannah Lee', 'rating' => 5, 'review' => 'Best massage I have had in years. The thermal pools are a bonus.', 'listing' => $listingIds[14]];
        $reviews[] = ['name' => 'Kevin Park', 'rating' => 4, 'review' => 'Great selection of pre-owned cars and no pushy sales tactics.', 'listing' => $listingIds[17]];
        $reviews[] = ['name' => 'Sofia Reyes', 'rating' => 5, 'review' => 'Perfect for a quick breakfast before a flight. Gluten-free toasties are great.', 'listing' => $listingIds[20]];
        $reviews[] = ['name' => 'Martin Koch', 'rating' => 4, 'review' => 'Sunset cruise was beautiful. Bring a jacket — it gets windy on the water.', 'listing' => $listingIds[21]];
        $reviews[] = ['name' => 'Yuki Sato', 'rating' => 5, 'review' => 'My nails have never looked better. The studio is spotless and calm.', 'listing' => $listingIds[15]];
        $reviews[] = ['name' => 'Oliver Gray', 'rating' => 4, 'review' => 'Honest mechanics who explain everything before they touch the car.', 'listing' => $listingIds[16]];
        $reviews[] = ['name' => 'Priya Sharma', 'rating' => 5, 'review' => 'The refill station is genius. Fresh produce every time.', 'listing' => $listingIds[18]];
        $reviews[] = ['name' => 'Jake Thompson', 'rating' => 4, 'review' => 'Good electronics range, helpful staff, decent prices.', 'listing' => $listingIds[19]];
        $reviews[] = ['name' => 'Emma Clarke', 'rating' => 5, 'review' => 'The mezze platter is huge and the garden seating is lovely in summer.', 'listing' => $listingIds[10]];

        foreach ($reviews as $i => $data) {
            $this->addContent($project, $c['reviews'], $data, published: true, daysAgo: max(12 - $i, 1));
        }

        /* --- Globals --- */
        foreach ([
            ['label' => 'site-name', 'value' => 'Business Directory'],
            ['label' => 'site-description', 'value' => 'Find the best local businesses around town'],
            ['label' => 'footer-text', 'value' => '© 2026 Business Directory — built with Aine CMS'],
            ['label' => 'support-email', 'value' => 'support@business-directory.example'],
        ] as $i => $data) {
            $this->addContent($project, $c['globals'], $data, published: true, daysAgo: 15 - $i);
        }

        /* --- Chinese structure: categories, tags, locations --- */
        $zhCategories = [];
        foreach ([
            ['餐厅', 'restaurants-zh', '从高档餐厅到休闲小馆——值得订位的去处。'],
            ['咖啡馆', 'cafes-zh', '咖啡、糕点与舒适角落，适合工作、会友或悠闲的早晨。'],
            ['酒店', 'hotels-zh', '精品旅居、经典大饭店，下一次出行的一切选择。'],
            ['购物', 'shopping-zh', '独立店铺与本地好店：图书、植物、电子产品等一应俱全。'],
            ['服务', 'services-zh', '健身房、维修、游船——让城市运转起来的各种服务。'],
            ['健康美容', 'health-beauty-zh', '水疗、沙龙与工作室，让你放松、焕新、状态满分。'],
            ['汽车', 'automotive-zh', '维修厂、展厅与各类车辆的养护服务。'],
        ] as $i => [$zhTitle, $zhUrl, $zhDescription]) {
            $zhCategories[$zhTitle] = $this->addContent($project, $c['categories'], ['title' => $zhTitle, 'slug' => $zhUrl, 'description' => $zhDescription], published: true, daysAgo: 30 - $i, locale: 'zh')->id;
        }
        $zhCategoryByEn = [
            'Restaurants' => $zhCategories['餐厅'], 'Cafes' => $zhCategories['咖啡馆'], 'Hotels' => $zhCategories['酒店'],
            'Shopping' => $zhCategories['购物'], 'Services' => $zhCategories['服务'],
            'Health & Beauty' => $zhCategories['健康美容'], 'Automotive' => $zhCategories['汽车'],
        ];

        $zhTags = [];
        foreach (['外卖', '宠物友好', '户外座位', '免费WiFi', '停车', '24小时', '亲子', '实惠', '奢华', '外带', '无麸质', '欢迎儿童', '长者优惠'] as $i => $zhTag) {
            $zhTags[$zhTag] = $this->addContent($project, $c['tags'], ['tag' => $zhTag], published: true, daysAgo: 29 - $i, locale: 'zh')->id;
        }
        $zhTagByEn = [
            'Delivery' => $zhTags['外卖'], 'Pet-Friendly' => $zhTags['宠物友好'], 'Outdoor Seating' => $zhTags['户外座位'],
            'Free WiFi' => $zhTags['免费WiFi'], 'Parking' => $zhTags['停车'], '24-Hours' => $zhTags['24小时'],
            'Family-Friendly' => $zhTags['亲子'], 'Budget' => $zhTags['实惠'], 'Luxury' => $zhTags['奢华'],
            'Takeaway' => $zhTags['外带'], 'Gluten-Free' => $zhTags['无麸质'], 'Kids Welcome' => $zhTags['欢迎儿童'],
            'Senior Discount' => $zhTags['长者优惠'],
        ];

        $zhLocations = [];
        foreach ([
            ['市中心', 'downtown-zh'], ['河畔', 'riverside-zh'], ['老城区', 'old-town-zh'], ['北区', 'northside-zh'],
            ['西区', 'west-end-zh'], ['机场区', 'airport-district-zh'],
        ] as $i => [$zhName, $zhUrl]) {
            $zhLocations[$zhName] = $this->addContent($project, $c['locations'], ['name' => $zhName, 'slug' => $zhUrl], published: true, daysAgo: 28 - $i, locale: 'zh')->id;
        }
        $zhLocationByEn = [
            'Downtown' => $zhLocations['市中心'], 'Riverside' => $zhLocations['河畔'], 'Old Town' => $zhLocations['老城区'],
            'Northside' => $zhLocations['北区'], 'West End' => $zhLocations['西区'], 'Airport District' => $zhLocations['机场区'],
        ];

        /* --- Chinese listings (22) --- */
        $zhListingData = [
            ['金勺子餐厅', 'the-golden-spoon-zh', '屡获殊荣的高档餐厅，以季节性品尝菜单闻名。', 'Restaurants', 'Downtown', ['奢华', '户外座位'], '+1-555-0101', 'hello@thegoldenspoon.example', 'https://thegoldenspoon.example', '市中心主街 12 号', '周一至周日 11:00 – 23:00', '$$$', 1],
            ['卢米埃尔咖啡馆', 'cafe-lumiere-zh', '社区里的温馨咖啡馆，提供手工咖啡与自制糕点。', 'Cafes', 'Downtown', ['免费WiFi', '户外座位'], '+1-555-0102', 'hello@cafelumiere.example', 'https://cafelumiere.example', '市中心市场巷 45 号', '周一至周六 07:30 – 18:00', '$$', 1],
            ['河畔小酒馆', 'riverside-bistro-zh', '河边的休闲小酒馆，时令菜单配本地葡萄酒。', 'Restaurants', 'Riverside', ['户外座位', '亲子'], '+1-555-0103', 'info@riversidebistro.example', 'https://riversidebistro.example', '河畔漫步道 3 号', '周二至周日 12:00 – 22:00', '$$$', 0],
            ['海港景观酒店', 'harbor-view-hotel-zh', '精品酒店，拥有壮丽海港景观、水疗与屋顶酒吧。', 'Hotels', 'Riverside', ['奢华', '停车', '免费WiFi'], '+1-555-0104', 'stay@harborview.example', 'https://harborview.example', '海港长廊 1 号', '24 小时营业', '$$$$', 1],
            ['老城书店', 'old-town-books-zh', '独立书店，稀有藏书、读书会与小小咖啡角。', 'Shopping', 'Old Town', ['免费WiFi', '实惠'], '+1-555-0105', 'books@oldtown.example', 'https://oldtownbooks.example', '老城区遗产街 88 号', '周一至周日 10:00 – 19:00', '$', 0],
            ['布鲁姆与罗勒', 'bloom-and-basil-zh', '植物店兼咖啡馆——在绿意中享用一杯白咖啡。', 'Cafes', 'Old Town', ['宠物友好', '免费WiFi'], '+1-555-0106', 'hi@bloombasil.example', 'https://bloombasil.example', '老城区花园庭院 21 号', '周一至周六 09:00 – 17:00', '$$', 0],
            ['城市健身中心', 'city-fitness-gym-zh', '24 小时健身房，配备现代器械、团课与私教。', 'Services', 'Northside', ['24小时', '停车'], '+1-555-0107', 'team@cityfitness.example', 'https://cityfitness.example', '北区北大道 500 号', '24 小时营业', '$$', 1],
            ['快速维修', 'quick-fix-repairs-zh', '当日电子产品与家电维修服务，提供上门取送。', 'Services', 'Northside', ['外卖', '实惠'], '+1-555-0108', 'fix@quickfix.example', 'https://quickfix.example', '北区工业路 77 号', '周一至周五 08:00 – 18:00', '$', 0],
            ['大饭店', 'the-grand-hotel-zh', '市中心经典大饭店，宴会厅与礼宾服务。', 'Hotels', 'Downtown', ['奢华', '停车', '亲子'], '+1-555-0109', 'reserve@grandhotel.example', 'https://grandhotel.example', '市中心大道 1 号', '24 小时营业', '$$$$', 1],
            ['日出面包房', 'sunrise-bakery-zh', '家庭面包房——清晨 6 点起供应新鲜面包、蛋糕与点心。', 'Cafes', 'Northside', ['实惠', '外卖', '亲子'], '+1-555-0110', 'hello@sunrisebakery.example', 'https://sunrisebakery.example', '北区黎明街 9 号', '周一至周日 06:00 – 16:00', '$', 0],
            ['橄榄树餐厅', 'the-olive-tree-zh', '西区地中海厨房——木火烤炉、开胃拼盘与悠长夏夜。', 'Restaurants', 'West End', ['亲子', '户外座位'], '+1-555-0111', 'hello@olivetree.example', 'https://theolivetree.example', '西区葡萄藤街 14 号', '周二至周日 17:00 – 23:00', '$$', 0],
            ['禅寿司', 'sushi-zen-zh', '以每日市场鲜货为主的 Omakase 寿司吧，精选清酒单。', 'Restaurants', 'Downtown', ['外卖', '奢华'], '+1-555-0112', 'reserve@sushizen.example', 'https://sushizen.example', '市中心樱花巷 22 号', '周一至周六 12:00 – 22:00', '$$$', 0],
            ['酿造与豆子烘焙坊', 'brew-and-bean-roastery-zh', '小批量咖啡烘焙坊，提供品鉴套餐与可外带的咖啡豆。', 'Cafes', 'West End', ['外带', '免费WiFi'], '+1-555-0113', 'brew@brewbean.example', 'https://brewbean.example', '西区烘焙坊街 5 号', '周一至周日 08:00 – 17:00', '$', 0],
            ['天鹅绒酒店', 'the-velvet-room-zh', '老城区庄园改建的精品酒店——爵士酒吧与隐秘庭院。', 'Hotels', 'Old Town', ['奢华', '停车'], '+1-555-0114', 'stay@velvetroom.example', 'https://velvetroom.example', '老城区庄园庭院 2 号', '24 小时营业', '$$$$', 1],
            ['海岸水疗中心', 'coastal-spa-wellness-zh', '海滨水疗中心，温泉泳池、按摩套房与宁静的茶室。', 'Health & Beauty', 'Riverside', ['停车', '亲子'], '+1-555-0115', 'hello@coastalspa.example', 'https://coastalspa.example', '河畔海岸大道 11 号', '周一至周日 10:00 – 20:00', '$$$', 1],
            ['奢华美甲工作室', 'luxe-nails-studio-zh', '高端美甲工作室，纯素产品，需预约。', 'Health & Beauty', 'Downtown', ['免费WiFi', '长者优惠'], '+1-555-0116', 'book@luxenails.example', 'https://luxenails.example', '市中心精品拱廊 33 号', '周二至周六 10:00 – 19:00', '$$', 0],
            ['顶点汽车养护', 'apex-auto-care-zh', '综合汽车维修厂——诊断、刹车与当日换油。', 'Automotive', 'Northside', ['停车', '24小时'], '+1-555-0117', 'service@apexauto.example', 'https://apexauto.example', '北区高速路排 88 号', '周一至周六 08:00 – 20:00', '$$$', 0],
            ['市中心汽车画廊', 'downtown-auto-gallery-zh', '前豪华车与经典车展厅，坐落于旧电车车库。', 'Automotive', 'Downtown', ['奢华', '停车'], '+1-555-0118', 'sales@autogallery.example', 'https://autogallery.example', '市中心电车仓库广场 1 号', '周一至周日 10:00 – 19:00', '$$$$', 1],
            ['绿叶市场', 'green-leaf-market-zh', '有机农产品、日用杂货与零浪费补充站。', 'Shopping', 'West End', ['实惠', '欢迎儿童'], '+1-555-0119', 'hello@greenleaf.example', 'https://greenleaf.example', '西区果园巷 17 号', '周一至周日 09:00 – 21:00', '$', 0],
            ['科技之家电子', 'techhouse-electronics-zh', '品类齐全的消费电子产品，店内专业顾问。', 'Shopping', 'Airport District', ['免费WiFi', '停车'], '+1-555-0120', 'store@techhouse.example', 'https://techhouse.example', '机场区航站楼大道 44 号', '周一至周日 10:00 – 20:00', '$$$', 0],
            ['每日研磨', 'the-daily-grind-zh', '为旅客准备的咖啡、吐司与全天早餐——清晨 5 点开始。', 'Cafes', 'Airport District', ['外带', '无麸质'], '+1-555-0121', 'grind@dailygrind.example', 'https://dailygrind.example', '机场区出发路 2 号', '周一至周日 05:00 – 22:00', '$', 1],
            ['海港码头游船', 'harbor-marina-tours-zh', '河畔码头出发的游船、日落巡游与皮划艇租赁。', 'Services', 'Riverside', ['亲子', '停车'], '+1-555-0122', 'tours@harbormarina.example', 'https://harbormarina.example', '河畔码头 3 号', '每日 09:00 – 18:00', '$$$', 0],
        ];

        $zhListingIds = [];
        foreach ($zhListingData as $i => [$title, $url, $desc, $cat, $loc, $tagNames, $phone, $email, $website, $address, $hours, $price, $featured]) {
            $tagIds = array_map(fn ($t) => $zhTags[$t], $tagNames);
            $zhListingIds[] = $this->addContent($project, $c['listings'], [
                'title' => $title, 'slug' => $url, 'description' => $desc,
                'category' => $zhCategoryByEn[$cat], 'location' => $zhLocationByEn[$loc],
                'tags' => implode(',', $tagIds),
                'logo' => $media[$i < 5 ? 'logo-'.(($i % 4) + 1).'.png' : (($i + 1) % 4 == 0 ? 'logo-4.png' : 'logo-'.((($i + 1) % 4) + 1).'.png')],
                'gallery' => implode(',', array_slice([$media['gallery-1.jpg'], $media['gallery-2.jpg'], $media['gallery-3.jpg'], $media['gallery-4.jpg']], $i % 4, $i % 3 + 1)),
                'phone' => $phone, 'email' => $email, 'website' => $website,
                'address' => $address, 'opening-hours' => $hours, 'price-range' => $price,
                'featured' => $featured,
            ], published: true, daysAgo: max(20 - $i, 1), locale: 'zh')->id;
        }

        /* --- Chinese reviews (10) --- */
        $zhReviews = [
            ['陈美玲', 5, '菜品出色，服务无可挑剔。品尝菜单令人难忘！', 0],
            ['王明', 4, '氛围和咖啡都很棒，就是周末人有点多。', 1],
            ['李娜', 5, '房间漂亮，海港景观物超所值。', 3],
            ['张伟', 4, '温馨的小书店，藏书非常棒。', 4],
            ['刘芳', 5, '第一次去就被店员记住了口味。太喜欢这里了！', 5],
            ['陈强', 4, '设备齐全的 24 小时健身房，环境干净。', 6],
            ['王芳', 5, '当天就修好了我的笔记本，价格公道服务友好。', 7],
            ['孙丽', 5, '真正的经典——下午茶必尝。', 8],
            ['周杰', 4, '咖啡品鉴套餐非常棒，店员对豆子如数家珍。', 11],
            ['吴敏', 5, '多年来最棒的一次按摩，温泉泳池是额外惊喜。', 14],
        ];
        foreach ($zhReviews as $i => [$name, $rating, $review, $listingIdx]) {
            $this->addContent($project, $c['reviews'], [
                'name' => $name, 'rating' => $rating, 'review' => $review,
                'listing' => $zhListingIds[$listingIdx],
            ], published: true, daysAgo: max(12 - $i, 1), locale: 'zh');
        }

        /* --- Project translations (zh) --- */
        $this->seedProjectTranslations($project, [
            'Listings' => '商家列表', 'Categories' => '分类', 'Tags' => '标签', 'Locations' => '位置',
            'Reviews' => '评价', 'Globals' => '全局',
            'Business Name' => '商家名称', 'Path' => '路径', 'Description' => '描述',
            'Category' => '分类', 'Location' => '位置', 'Logo' => '标志', 'Gallery' => '图库',
            'Phone' => '电话', 'Email' => '邮箱', 'Website' => '网站', 'Address' => '地址',
            'Opening Hours' => '营业时间', 'Price Range' => '价格区间', 'Featured' => '精选',
            'Title' => '标题', 'Name' => '姓名', 'Rating' => '评分', 'Review' => '评价内容',
            'Listing' => '商家', 'Label' => '标签名', 'Value' => '值', 'Tag' => '标签',
        ]);

        $this->seedProjectFeatures($project, $c['listings']);
    }

    /* ------------------------------------------------------------------ */
    /* Demo Speech (Speech Template)                                       */
    /* ------------------------------------------------------------------ */

    protected function seedSpeechProject(): void
    {
        $project = $this->createProject([
            'name' => 'Speech',
            'slug' => 'speech',
            'description' => '普通话学习文本、演讲稿、绕口令与经典语句的内容平台。',
            'default_locale' => 'zh',
            'locales' => 'zh,en',
            'disk' => 'local',
            'public_api' => true,
            'domain_whitelist' => [
                config('app.url'),
                'http://localhost:3000',
                'http://localhost:5173',
            ],
        ]);

        $c = ProjectTemplates::apply($project, ProjectTemplates::get(ProjectTemplates::SPEECH));

        $media = $this->seedMedia($project, [
            ['cover-putonghua.jpg', '普通话学习', [13, 148, 136], [14, 165, 233], 1200, 630, false],
            ['cover-yanjiang.jpg', '演讲稿', [217, 119, 6], [239, 68, 68], 1200, 630, false],
            ['cover-raokouling.jpg', '绕口令', [124, 58, 237], [236, 72, 153], 1200, 630, false],
            ['cover-jingdian.jpg', '经典语句', [37, 99, 235], [6, 182, 212], 1200, 630, false],
        ]);

        /* --- Pages --- */
        foreach ([
            ['title' => '首页', 'slug' => 'home', 'content' => '<h1>欢迎来到普通话演讲学习平台</h1><p>这里汇集<strong>普通话学习文本、演讲稿、绕口令与经典语句</strong>，助你练好发音、开口自信。</p>'],
            ['title' => '关于我们', 'slug' => 'about', 'content' => '<h2>关于普通话演讲学习平台</h2><p>本平台由 Aine 驱动，专注于普通话正音、演讲表达与语言素养提升，内容全部支持通过公共 API 接入你的网站或应用。</p>'],
            ['title' => '学习指南', 'slug' => 'guide', 'content' => '<h2>如何用好这个平台</h2><ol><li>先做<strong>声母、韵母、声调</strong>的基础练习；</li><li>再通过<strong>绕口令</strong>强化口腔控制与吐字归音；</li><li>最后在<strong>演讲稿与经典语句</strong>中体会语感与表达。</li></ol>'],
        ] as $i => $data) {
            $this->addContent($project, $c['pages'], $data, published: true, daysAgo: 20 - $i, locale: 'zh');
        }

        /* --- Categories --- */
        $categories = [];
        foreach ([
            ['普通话学习', 'putonghua-xuexi', '声母、韵母、声调与语流音变的基础知识与练习文本。'],
            ['演讲稿', 'yanjiang-gao', '励志、校园、职场等不同场合的演讲稿范文与写作方法。'],
            ['绕口令', 'raokouling', '经典绕口令，训练唇舌力度与吐字归音。'],
            ['经典语句', 'jingdian-yuju', '名言警句、诗词名句与谚语俗语，积累表达素材。'],
        ] as $i => [$title, $url, $description]) {
            $categories[$title] = $this->addContent($project, $c['categories'], ['title' => $title, 'slug' => $url, 'description' => $description], published: true, daysAgo: 19 - $i, locale: 'zh')->id;
        }

        /* --- Tags --- */
        $tags = [];
        foreach (['声母', '韵母', '声调', '轻声', '儿化', '多音字', '朗读', '演讲', '励志', '校园', '职场', '口才', '名言', '谚语'] as $i => $tag) {
            $tags[$tag] = $this->addContent($project, $c['tags'], ['tag' => $tag], published: true, daysAgo: 17 - $i, locale: 'zh')->id;
        }

        /* --- Posts --- */
        $postData = [
            // 普通话学习
            ['声母发音入门：b p m f', 'putonghua-shengmu-bpmf', '双唇音与唇齿音的发音要领，配上对比练习，帮你打好普通话基础。', '<h2>双唇音 b 与 p</h2><p><strong>b</strong> 是不送气清音，<strong>p</strong> 是送气清音。练习时把手掌放在嘴前，读 b 几乎感觉不到气流，读 p 能明显感到气流冲出。</p><h2>鼻音 m 与唇齿音 f</h2><p><strong>m</strong> 双唇闭合、气流从鼻腔通过；<strong>f</strong> 上齿轻触下唇，气流从唇齿间挤出。</p><h3>对比练习</h3><ul><li>爸爸（bà ba）—— 爬坡（pá pō）</li><li>妈妈（mā ma）—— 丰富（fēng fù）</li></ul>', '普通话学习', ['声母'], 'cover-putonghua.jpg', 0, 1, 0],
            ['韵母与声调：普通话的“四声”', 'putonghua-yunmu-shengdiao', '一声平、二声扬、三声拐弯、四声降——声调是普通话的“灵魂”。', '<h2>四个声调</h2><p>普通话有四个基本声调：阴平（55）、阳平（35）、上声（214）、去声（51）。</p><h2>声调与意义</h2><p>同一个音节，声调不同意思完全不同：mā（妈）、má（麻）、mǎ（马）、mà（骂）。</p><h3>练习方法</h3><ol><li>用“ā á ǎ à”做长音练习；</li><li>把词语按声调组合朗读：山河锦绣、光明磊落。</li></ol>', '普通话学习', ['韵母', '声调'], 'cover-putonghua.jpg', 0, 1, 0],
            ['轻声与儿化：让普通话更自然', 'putonghua-qingsheng-erhua', '轻声不是“读得轻”，儿化不是“随便加”——掌握规律，普通话说得更地道。', '<h2>什么是轻声</h2><p>轻声是一种又短又轻的变调，如“爸爸”“妈妈”的第二个音节，以及助词“的、地、得”。</p><h2>什么是儿化</h2><p>儿化韵在韵母后加上卷舌动作，如“小孩儿”“一点儿”。</p><h3>常见轻声词</h3><p>萝卜、豆腐、耳朵、事情、朋友。</p>', '普通话学习', ['轻声', '儿化'], 'cover-putonghua.jpg', 0, 0, 1],
            ['常见多音字辨析', 'putonghua-duoyinzi', '好读书不好读书，好读书不好读书——多音字一错，意思全变。', '<h2>为什么会有多音字</h2><p>多音字因意义不同而读音不同，是普通话学习中的重点难点。</p><h3>常见多音字</h3><ul><li><strong>好</strong>：hǎo（很好）/ hào（爱好）</li><li><strong>行</strong>：xíng（行走）/ háng（银行）</li><li><strong>长</strong>：cháng（长短）/ zhǎng（成长）</li><li><strong>重</strong>：zhòng（重要）/ chóng（重复）</li></ul><p>建议结合词语记忆，不要孤立背读音。</p>', '普通话学习', ['多音字'], 'cover-putonghua.jpg', 0, 1, 1],
            // 演讲稿
            ['励志演讲稿：向着光奔跑', 'yanjiang-lizhi-guang', '与其在黑暗里等待，不如向着光奔跑。一篇关于坚持与勇气的演讲。', '<h2>向着光奔跑</h2><p>尊敬的老师、亲爱的同学们：</p><p>大家好！今天我想和大家聊一个词——<strong>奔跑</strong>。</p><p>人生难免有低谷，就像夜里赶路。有人停在原地抱怨天黑，有人点亮火把继续前行。其实，光不是等来的，是跑出来的。</p><p>每一次练习、每一次失败后的重来，都是在为自己的路铺灯。请相信，当你决定出发，最难的时刻已经过去。</p><p>愿我们都能——向着光，奔跑。</p>', '演讲稿', ['演讲', '励志'], 'cover-yanjiang.jpg', 1, 1, 0],
            ['毕业致辞：致我们滚烫的青春', 'yanjiang-biye-qingchun', '当毕业钟声响起，把最美的祝愿送给每一位即将远行的你。', '<h2>致我们滚烫的青春</h2><p>尊敬的老师们、亲爱的同学们：</p><p>大家好！今天，我们站在毕业的门槛上，回望这滚烫的青春。</p><p>还记得教室里朗朗的读书声，操场上挥洒的汗水，深夜里互相打气的朋友圈。这些细碎的片段，拼成了我们最珍贵的时光。</p><p>毕业不是句号，而是新的逗号。愿我们带着勇气出发，在各自的世界里闪闪发光。</p>', '演讲稿', ['演讲', '校园'], 'cover-yanjiang.jpg', 0, 1, 0],
            ['竞聘演讲稿：以实干作答', 'yanjiang-jingpin', '不喊口号、不摆姿态，用一件件实事证明自己——竞聘演讲的务实范本。', '<h2>以实干作答</h2><p>各位领导、各位同事：</p><p>大家好！今天我竞聘的岗位是项目主管。我不打算说太多漂亮话，只想用三件事回答大家。</p><p><strong>第一，我能干活。</strong>过去一年，我牵头完成了三项跨部门协作项目，全部按期交付。</p><p><strong>第二，我肯学习。</strong>我利用业余时间完成了管理课程进修，并把它用在了团队协作上。</p><p><strong>第三，我愿担责。</strong>遇到问题不推诿，先解决问题，再复盘原因。</p><p>如果大家给我这个机会，我会用行动证明：选择我，不会错。</p>', '演讲稿', ['演讲', '职场'], 'cover-yanjiang.jpg', 0, 0, 1],
            ['即兴演讲的开场与收尾技巧', 'yanjiang-jixing-jiqiao', '好的开场三秒抓住听众，好的收尾让人记住你——即兴演讲的实用技巧。', '<h2>开场：三秒抓住听众</h2><ul><li><strong>提问式</strong>：你们有没有想过……</li><li><strong>故事式</strong>：上周我遇到一件事……</li><li><strong>数据式</strong>：据统计，……</li></ul><h2>收尾：让人记住你</h2><p>把核心观点浓缩成一句话，再用一个动作或一句祝愿收束，如“谢谢大家，愿我们都能成为想成为的人。”</p><p><em>技巧的核心：先想“听众要什么”，再想“我要说什么”。</em></p>', '演讲稿', ['演讲', '口才'], 'cover-yanjiang.jpg', 0, 1, 0],
            // 绕口令
            ['绕口令：四是四，十是十', 'raokouling-sishi-shishi', '数字绕口令的经典，专治平翘舌不分。', '<h2>四是四，十是十</h2><p>四是四，十是十，十四是十四，四十是四十。</p><p>谁要说十四是四十，就打谁四十；谁要说四十是十四，就罚谁十四。</p><p><strong>练习提示</strong>：先慢后快，把“四（sì）”与“十（shí）”的平翘舌读准。</p>', '绕口令', ['声母', '韵母'], 'cover-raokouling.jpg', 1, 0, 0],
            ['绕口令：吃葡萄不吐葡萄皮', 'raokouling-chiputao', '一句绕口令，练好 b、p 双唇音。', '<h2>吃葡萄不吐葡萄皮</h2><p>吃葡萄不吐葡萄皮，不吃葡萄倒吐葡萄皮。</p><p><strong>练习提示</strong>：重点体会 b 与 p 的送气区别，由慢到快，保持每个字清晰。</p>', '绕口令', ['声母', '朗读'], 'cover-raokouling.jpg', 1, 0, 0],
            ['绕口令：扁担长，板凳宽', 'raokouling-biandan', '长短宽窄，字字分明——经典的语音对比练习。', '<h2>扁担长，板凳宽</h2><p>扁担长，板凳宽，板凳没有扁担长，扁担没有板凳宽。</p><p>扁担要绑在板凳上，板凳偏不让扁担绑在板凳上。</p><p><strong>练习提示</strong>：注意“长（cháng）”与“宽（kuān）”的韵母归音。</p>', '绕口令', ['声母', '韵母'], 'cover-raokouling.jpg', 0, 1, 0],
            ['绕口令：打南边来了个喇嘛', 'raokouling-lama', '喇嘛与哑巴，提鳎目与吹喇叭——绕口令里的“高峰挑战”。', '<h2>打南边来了个喇嘛</h2><p>打南边来了个喇嘛，手里提拉着五斤鳎目；打北边来了个哑巴，腰里别着个喇叭。</p><p>南边提拉着鳎目的喇嘛，要拿鳎目换北边别喇叭的哑巴的喇叭；哑巴不愿意拿喇叭换提拉着鳎目的喇嘛的鳎目。</p><p><strong>练习提示</strong>：这段绕口令难度较高，建议先分段慢读，再逐步加速。</p>', '绕口令', ['朗读'], 'cover-raokouling.jpg', 0, 0, 1],
            // 经典语句
            ['论语治学名句', 'jingdian-lunyu', '学而时习之，不亦说乎——从《论语》中汲取学习的智慧。', '<h2>《论语》治学名句</h2><blockquote>学而时习之，不亦说乎？有朋自远方来，不亦乐乎？——《论语·学而》</blockquote><blockquote>学而不思则罔，思而不学则殆。——《论语·为政》</blockquote><blockquote>知之为知之，不知为不知，是知也。——《论语·为政》</blockquote><p>反复诵读，体会经典语句的韵律与含义。</p>', '经典语句', ['名言'], 'cover-jingdian.jpg', 0, 1, 0],
            ['劝学：积跬步以至千里', 'jingdian-quanxue', '不积跬步，无以至千里——荀子劝学中的坚持之道。', '<h2>荀子《劝学》名句</h2><blockquote>不积跬步，无以至千里；不积小流，无以成江海。——《荀子·劝学》</blockquote><blockquote>锲而舍之，朽木不折；锲而不舍，金石可镂。——《荀子·劝学》</blockquote><p>学习贵在积累，日拱一卒，功不唐捐。</p>', '经典语句', ['名言'], 'cover-jingdian.jpg', 0, 1, 1],
            ['中华诗词名句选', 'jingdian-shici', '从“长风破浪”到“一览众山小”，品读诗词里的豪情与境界。', '<h2>诗词名句</h2><blockquote>长风破浪会有时，直挂云帆济沧海。——李白《行路难》</blockquote><blockquote>会当凌绝顶，一览众山小。——杜甫《望岳》</blockquote><blockquote>天行健，君子以自强不息。——《周易》</blockquote>', '经典语句', ['名言'], 'cover-jingdian.jpg', 0, 0, 0],
            ['谚语俗语：生活的智慧', 'jingdian-yanyu', '一句谚语，一段生活——俗语中的朴素哲理。', '<h2>谚语俗语精选</h2><ul><li>千里之行，始于足下。——《道德经》</li><li>宝剑锋从磨砺出，梅花香自苦寒来。</li><li>冰冻三尺，非一日之寒。</li><li>人心齐，泰山移。</li></ul><p>谚语短小精悍，适合晨读与朗诵练习。</p>', '经典语句', ['谚语'], 'cover-jingdian.jpg', 0, 0, 1],
        ];

        foreach ($postData as $i => [$title, $url, $excerpt, $content, $cat, $tagNames, $cover, $slider, $featured, $recommended]) {
            $tagIds = array_map(fn ($t) => $tags[$t], $tagNames);
            $this->addContent($project, $c['posts'], [
                'title' => $title, 'slug' => $url, 'excerpt' => $excerpt, 'content' => $content,
                'featured-image' => $media[$cover], 'category' => $categories[$cat],
                'tags' => implode(',', $tagIds),
                'slider' => $slider, 'featured' => $featured, 'recommended' => $recommended,
            ], published: true, daysAgo: max(16 - $i, 1), locale: 'zh')->id;
        }

        /* --- Globals --- */
        foreach ([
            ['label' => 'site-name', 'value' => '普通话演讲学习平台'],
            ['label' => 'site-description', 'value' => '普通话学习文本、演讲稿、绕口令与经典语句'],
            ['label' => 'footer-text', 'value' => '© 2026 普通话演讲学习平台 —— 基于 Aine 构建'],
            ['label' => 'support-email', 'value' => 'support@speech.example'],
        ] as $i => $data) {
            $this->addContent($project, $c['globals'], $data, published: true, daysAgo: 15 - $i, locale: 'zh');
        }

        /* --- Project translations (zh, the base locale) --- */
        foreach ([
            'Pages' => '页面', 'Posts' => '文章', 'Categories' => '分类', 'Tags' => '标签', 'Globals' => '全局',
            'Title' => '标题', 'Path' => '路径', 'Content' => '内容', 'Image' => '图片',
            'Excerpt' => '摘要', 'Featured Image' => '特色图片', 'Category' => '分类', 'Tag' => '标签',
            'Slider' => '幻灯片', 'Featured' => '精选', 'Recommended' => '推荐',
            'Label' => '标签名', 'Value' => '值', 'Description' => '描述',
        ] as $source => $value) {
            ProjectTranslation::updateOrCreate(
                ['project_id' => $project->id, 'locale' => 'zh', 'source' => $source],
                ['value' => $value]
            );
        }

        $this->seedProjectFeatures($project, $c['posts']);
    }
}
