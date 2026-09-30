<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('author')->nullable();
            $table->string('icon')->nullable();          // FontAwesome icon class, e.g. "fa-newspaper"
            $table->string('preview_image')->nullable();  // URL or path to preview screenshot
            $table->string('asset_path');                 // Relative path under resources/js/admin/views/Project.Content/
            $table->json('design_tokens')->nullable();    // CSS custom properties as JSON
            $table->json('view_overrides')->nullable();   // Explicit view mapping (route_name => component path)
            $table->boolean('is_active')->default(true);  // Whether this theme is available for selection
            $table->timestamps();
        });

        // Add theme_id foreign key to projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('theme_id')->nullable();
            $table->foreign('theme_id')->references('id')->on('project_themes')->nullOnDelete();
            $table->json('theme_config')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['theme_id']);
            $table->dropColumn(['theme_id', 'theme_config']);
        });

        Schema::dropIfExists('project_themes');
    }
};
