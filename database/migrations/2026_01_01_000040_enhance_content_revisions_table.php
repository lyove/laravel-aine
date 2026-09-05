<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_revisions', function (Blueprint $table) {
            // Version chain: points to the previous revision of the same content
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->index('parent_id');

            // Action type: created / updated / published / unpublished /
            // draft_updated / restored / imported / deleted
            $table->string('action', 32)->default('updated')->after('locale');
            $table->index('action');

            // Optional user-defined label (e.g. "v1.0 release", "Approved")
            $table->string('label', 120)->nullable()->after('note');
            $table->index('label');

            // JSON metadata: change summary, field diff stats, etc.
            $table->json('meta')->nullable()->after('label');

            // Composite index for fast "latest revisions of a content" queries
            $table->index(['content_id', 'created_at'], 'revisions_content_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('content_revisions', function (Blueprint $table) {
            $table->dropIndex('revisions_content_created_idx');
            $table->dropColumn(['parent_id', 'action', 'label', 'meta']);
        });
    }
};
