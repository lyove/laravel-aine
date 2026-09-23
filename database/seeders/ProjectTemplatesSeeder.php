<?php

namespace Database\Seeders;

use App\Aine\ProjectTemplates;
use App\Models\ProjectTemplate;
use Illuminate\Database\Seeder;

class ProjectTemplatesSeeder extends Seeder
{
    public function run()
    {
        $meta = [
            ProjectTemplates::CMS => [
                'slug'        => 'cms',
                'description' => 'Content Management System (Pages, Articles, Categories, Authors, Tags, Comments, Globals)',
                'icon'        => 'fa-newspaper',
                'order'       => 1,
            ],
            ProjectTemplates::BUSINESS_DIRECTORY => [
                'slug'        => 'directory',
                'description' => 'Business Directory (Listings, Categories, Tags, Locations, Reviews, Globals)',
                'icon'        => 'fa-store',
                'order'       => 2,
            ],
            ProjectTemplates::NOTE => [
                'slug'        => 'note',
                'description' => 'Note (Pages, Posts, Categories, Tags, Globals)',
                'icon'        => 'fa-sticky-note',
                'order'       => 3,
            ],
        ];

        foreach (ProjectTemplates::all() as $type => $def) {
            ProjectTemplate::updateOrCreate(
                ['slug' => $meta[$type]['slug']],
                [
                    'name'        => $def['name'],
                    'description' => $meta[$type]['description'],
                    'icon'        => $meta[$type]['icon'],
                    'collections' => $def['collections'],
                    'order'       => $meta[$type]['order'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
