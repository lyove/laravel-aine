<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('version')->nullable();
            $table->string('admin_path', 60)->default('admin');
            $table->integer('media_max_upload_size')->nullable();
            $table->boolean('media_enable_chunk_upload')->default(false);
            $table->string('media_thumbnail_sizes')->nullable();
            $table->string('media_storage_driver', 10)->default('local');
            $table->text('media_storage_config')->nullable();
            $table->string('mail_driver', 20)->default('smtp');
            $table->string('mail_host')->nullable();
            $table->integer('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->string('mail_password')->nullable();
            $table->string('mail_encryption')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
