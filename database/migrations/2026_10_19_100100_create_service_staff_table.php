<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quota minima di una categoria per considerare un'operatrice "titolare" di quella categoria
     * (serve solo a collegare i servizi che non sono mai stati usati).
     */
    private const MAIN_STAFF_SHARE = 0.2;

    public function up(): void
    {
        Schema::create('service_staff', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $table->unique(['service_id', 'staff_id']);
        });

        $this->linkFromHistory();
        $this->linkUnusedServicesByCategory();
    }

    public function down(): void
    {
        Schema::dropIfExists('service_staff');
    }

    /** Ogni servizio viene collegato alle operatrici che lo hanno già eseguito. */
    private function linkFromHistory(): void
    {
        $pairs = DB::table('appointment_service as aps')
            ->join('appointments as a', 'a.id', '=', 'aps.appointment_id')
            ->whereNotNull('a.staff_id')
            ->select('aps.service_id', 'a.staff_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            DB::table('service_staff')->insertOrIgnore([
                'service_id' => $pair->service_id,
                'staff_id' => $pair->staff_id,
            ]);
        }
    }

    /**
     * I servizi mai usati (senza storico) con una categoria vengono collegati alle operatrici
     * che di quella categoria fanno la parte principale del lavoro; senza categoria restano liberi (tutte).
     */
    private function linkUnusedServicesByCategory(): void
    {
        $lines = DB::table('appointment_service as aps')
            ->join('appointments as a', 'a.id', '=', 'aps.appointment_id')
            ->join('services as s', 's.id', '=', 'aps.service_id')
            ->whereNotNull('a.staff_id')
            ->whereNotNull('s.category_id')
            ->groupBy('s.category_id', 'a.staff_id')
            ->selectRaw('s.category_id as category_id, a.staff_id as staff_id, count(*) as n')
            ->get()
            ->groupBy('category_id');

        $unusedServices = DB::table('services')
            ->whereNotNull('category_id')
            ->whereNotIn('id', DB::table('service_staff')->select('service_id'))
            ->get(['id', 'category_id']);

        foreach ($unusedServices as $service) {
            $categoryLines = $lines->get($service->category_id);

            if (! $categoryLines) {
                continue;
            }

            $total = $categoryLines->sum('n');

            foreach ($categoryLines as $row) {
                if ($row->n / $total >= self::MAIN_STAFF_SHARE) {
                    DB::table('service_staff')->insertOrIgnore([
                        'service_id' => $service->id,
                        'staff_id' => $row->staff_id,
                    ]);
                }
            }
        }
    }
};
