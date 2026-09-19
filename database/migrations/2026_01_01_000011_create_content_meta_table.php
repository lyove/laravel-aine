<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContentMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content_meta', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->integer('collection_id');
            $table->integer('content_id');
            $table->string('field_name');
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->softdeletes();

            $table->index(['project_id', 'collection_id', 'content_id'], 'idx_content_meta_project_collection_content');
            $table->index(['project_id', 'collection_id', 'field_name'], 'idx_content_meta_project_collection_field');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('content_meta');
    }
}
