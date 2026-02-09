<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppointmentStatsBottom extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $pendingCount = Appointment::query()->pending()->count();
        $confirmedCount = Appointment::query()->confirmed()->count();

        return [
            Stat::make('Da programmare', $pendingCount)
                ->description('In attesa')
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('Confermate', $confirmedCount)
                ->description('Confermate')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}