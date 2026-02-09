<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });

        Schema::table('dogs', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('clients')->whereNull('first_name')->update(['first_name' => '']);
        DB::table('clients')->whereNull('last_name')->update(['last_name' => '']);
        DB::table('dogs')->whereNull('name')->update(['name' => '']);

        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });

        Schema::table('dogs', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};