<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = now();

        $scheduled = fn () => Appointment::query()->whereNotNull('scheduled_at');

        return response()->json([
            'today' => $scheduled()->whereDate('scheduled_at', $now)->count(),
            'tomorrow' => $scheduled()->whereDate('scheduled_at', $now->copy()->addDay())->count(),
            'week' => $scheduled()->whereBetween('scheduled_at', [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ])->count(),
            'to_schedule' => Appointment::query()->pending()->count(),
            'confirmed' => Appointment::query()->confirmed()->count(),
        ]);
    }
}
