@php
    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div
            class="mb-4 flex gap-3 overflow-x-auto pb-1"
            x-data="{ isDayView: false, calendarDay: '', breedStats: [] }"
            x-show="isDayView"
            x-cloak
            @zaga-calendar-breed-stats.window="
                isDayView = $event.detail.isDayView;
                calendarDay = $event.detail.calendarDay;
                breedStats = $event.detail.breedStats;
            "
        >
            <template x-for="breedStat in breedStats" :key="breedStat.breed">
                <div class="min-w-44 shrink-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="truncate text-sm font-medium text-gray-500 dark:text-gray-400" x-text="breedStat.breed"></div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" x-text="breedStat.count"></div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Appuntamenti</div>
                </div>
            </template>

            <template x-if="isDayView && breedStats.length === 0">
                <div class="min-w-72 shrink-0 rounded-lg border border-dashed border-gray-300 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Nessuna razza</div>
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Non ci sono appuntamenti confermati in questo giorno.</div>
                </div>
            </template>
        </div>

        <div class="flex justify-end flex-1 mb-4">
            <x-filament-actions::actions :actions="$this->getCachedHeaderActions()" class="shrink-0" />
        </div>

        <div class="filament-fullcalendar" wire:ignore x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-fullcalendar-alpine', 'saade/filament-fullcalendar') }}"
            ax-load-css="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('filament-fullcalendar-styles', 'saade/filament-fullcalendar') }}"
            x-ignore x-data="fullcalendar({
                locale: @js($plugin->getLocale()),
                plugins: @js($plugin->getPlugins()),
                schedulerLicenseKey: @js($plugin->getSchedulerLicenseKey()),
                timeZone: @js($plugin->getTimezone()),
                config: @js($this->getConfig()),
                editable: @json($plugin->isEditable()),
                selectable: @json($plugin->isSelectable()),
                eventClassNames: {!! htmlspecialchars($this->eventClassNames(), ENT_COMPAT) !!},
                eventContent: {!! htmlspecialchars($this->eventContent(), ENT_COMPAT) !!},
                eventDidMount: {!! htmlspecialchars($this->eventDidMount(), ENT_COMPAT) !!},
                eventWillUnmount: {!! htmlspecialchars($this->eventWillUnmount(), ENT_COMPAT) !!},
            })">
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
