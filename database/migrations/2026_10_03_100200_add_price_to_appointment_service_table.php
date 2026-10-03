<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prezzo realmente applicato a quel servizio in quell'appuntamento (copia del listino, modificabile).
        Schema::table('appointment_service', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_service', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
