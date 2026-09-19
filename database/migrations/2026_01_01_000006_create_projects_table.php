<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->string('default_locale', 10)->default('en');
            $table->string('locales')->nullable();
            $table->string('disk', 10)->default('local');
            $table->boolean('public_api')->nullable()->default(false);
            $table->json('domain_whitelist')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('workflow_enabled')->default(false);
            $table->string('copyright', 255)->nullable();
            $table->string('filing_info', 255)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('language_direction', 3)->default('ltr');
            $table->string('logo_url', 255)->nullable();
            $table->string('favicon_url', 255)->nullable();
            $table->longText('custom_header')->nullable();
            $table->longText('custom_footer')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->longText('analytics_script')->nullable();
            $table->text('contact_address')->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->longText('contact_text')->nullable();

            $table->timestamps();
            $table->softdeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
}
