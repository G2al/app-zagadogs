<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppointmentStatsTop extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $now = now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $todayCount = Appointment::query()
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', $now)
            ->count();

        $tomorrowCount = Appointment::query()
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', $now->copy()->addDay())
            ->count();

        $weekCount = Appointment::query()
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$startOfWeek, $endOfWeek])
            ->count();

        return [
            Stat::make('Oggi', $todayCount)
                ->description('Appuntamenti')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Domani', $tomorrowCount)
                ->description('Appuntamenti')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Settimana', $weekCount)
                ->description('Totali')
                ->icon('heroicon-o-calendar'),
        ];
    }
}