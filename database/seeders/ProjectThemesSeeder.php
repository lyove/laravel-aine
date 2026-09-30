<?php

namespace Database\Seeders;

use App\Aine\ThemeService;
use Illuminate\Database\Seeder;

/**
 * Seed the project_themes table by scanning the filesystem for theme packages.
 *
 * This seeder delegates to ThemeService::discoverAndSync() which reads
 * manifest.json files from resources/js/admin/themes/{slug}/.
 */
class ProjectThemesSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ThemeService::class);
        $discovered = $service->discoverAndSync();

        $this->command->info('ProjectThemesSeeder: ' . count($discovered) . ' theme(s) synced.');
    }
}
