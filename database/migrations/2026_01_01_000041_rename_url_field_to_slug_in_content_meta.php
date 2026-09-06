<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('content_meta')
            ->where('field_name', 'url')
            ->update(['field_name' => 'slug']);
    }

    public function down(): void
    {
        DB::table('content_meta')
            ->where('field_name', 'slug')
            ->update(['field_name' => 'url']);
    }
};
