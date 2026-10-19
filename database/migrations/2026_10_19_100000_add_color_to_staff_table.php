<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stessa tavolozza di App\Models\Staff::COLORS (la migration non deve dipendere dal model). */
    private const PALETTE = [
        '#2563eb', '#16a34a', '#f59e0b', '#db2777', '#7c3aed',
        '#0ea5e9', '#ea580c', '#14b8a6', '#dc2626', '#84cc16',
    ];

    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Colore dell'operatrice: è quello con cui compaiono i suoi appuntamenti nel calendario.
            $table->string('color', 20)->nullable()->after('name');
        });

        // Ogni operatrice già presente riceve un colore diverso (si può cambiare dopo).
        $position = 0;

        foreach (DB::table('staff')->orderBy('id')->pluck('id') as $id) {
            DB::table('staff')->where('id', $id)->update(['color' => self::PALETTE[$position % count(self::PALETTE)]]);
            $position++;
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
