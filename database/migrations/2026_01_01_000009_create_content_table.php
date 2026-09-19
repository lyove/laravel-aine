<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContentTable extends Migration
{
    public function up()
    {
        Schema::create('content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draft_parent_id')->nullable()->index('idx_content_draft_parent');
            $table->integer('project_id');
            $table->integer('collection_id');
            $table->string('locale', 10)->nullable();
            $table->integer('form_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('published_by')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->uuid('preview_token')->nullable();
            $table->timestamp('preview_expires_at')->nullable();
            $table->string('workflow_state', 20)->default('draft');
            $table->string('reviewer_comment', 1000)->nullable();
            $table->softdeletes();

            $table->index(['project_id', 'collection_id', 'published_at'], 'idx_content_project_collection_published');
            $table->index(['project_id', 'collection_id', 'locale'], 'idx_content_project_collection_locale');
            $table->index('preview_token', 'idx_content_preview_token');
            $table->index('workflow_state', 'idx_content_workflow_state');
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->integer('project_id')->index();
            $table->unsignedBigInteger('content_id')->index();
            $table->timestamps();

            $table->unique(['user_id', 'project_id', 'content_id'], 'uq_favorites_user_project_content');
        });

        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->integer('project_id')->index();
            $table->unsignedBigInteger('content_id')->index();
            $table->timestamps();

            $table->unique(['user_id', 'project_id', 'content_id'], 'uq_likes_user_project_content');
        });
    }

    public function down()
    {
        Schema::dropIfExists('likes');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('content');
    }
}
