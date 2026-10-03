<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('color')
                ->constrained('service_categories')
                ->nullOnDelete();

            // Prezzo di listino: il prezzo reale si può cambiare su ogni appuntamento.
            $table->decimal('price', 8, 2)->nullable()->after('category_id');
        });

        // La durata diventa facoltativa.
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('services')->whereNull('duration_minutes')->update(['duration_minutes' => 30]);

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable(false)->default(30)->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('price');
        });
    }
};
