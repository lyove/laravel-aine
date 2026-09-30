<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebhooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('url');
            $table->string('secret')->nullable();

            $table->json('collection_ids');
            $table->json('events');
            $table->json('sources');

            $table->boolean('payload')->default(true);
            $table->boolean('status')->default(true);

            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('webhooks');
    }
}
