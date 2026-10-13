<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Durata dell'appuntamento in minuti. Null = non specificata (la fine coincide con l'inizio).
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('scheduled_at');
        });

        // Gli appuntamenti già segnati mantengono la durata che avevano finora (somma di quelle dei servizi):
        // da qui in avanti non cambiano più se si modifica la durata di un servizio.
        $totals = DB::table('appointment_service as aps')
            ->join('services as s', 's.id', '=', 'aps.service_id')
            ->whereNotNull('s.duration_minutes')
            ->groupBy('aps.appointment_id')
            ->selectRaw('aps.appointment_id as appointment_id, sum(s.duration_minutes) as total')
            ->get();

        foreach ($totals as $row) {
            if ((int) $row->total > 0) {
                DB::table('appointments')
                    ->where('id', $row->appointment_id)
                    ->update(['duration_minutes' => min((int) $row->total, 720)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
