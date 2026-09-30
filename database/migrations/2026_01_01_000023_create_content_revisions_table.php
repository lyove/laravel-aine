<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('project_id')->index();
            $table->integer('collection_id')->index();
            $table->integer('content_id')->index();
            $table->string('locale', 10)->nullable();
            $table->string('action', 32)->default('updated')->index();
            $table->longText('data');
            $table->string('note', 255)->nullable();
            $table->string('label', 120)->nullable()->index();
            $table->json('meta')->nullable();
            $table->integer('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['content_id', 'created_at'], 'revisions_content_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};
