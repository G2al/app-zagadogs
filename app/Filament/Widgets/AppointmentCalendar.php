<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Client;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Carbon;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendar extends FullCalendarWidget
{
    protected static ?string $heading = 'Calendario Appuntamenti';

    protected static string $view = 'filament.widgets.appointment-calendar';

    protected static ?int $sort = 2;

    public function getModel(): ?string
    {
        return Appointment::class;
    }

    public function config(): array
    {
        return [
            'initialView' => 'timeGridWeek',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,dayGridWeek,dayGridDay,gridWeek',
            ],
            'views' => [
                'gridWeek' => [
                    'type' => 'timeGridWeek',
                    'buttonText' => 'Griglia',
                    'eventOverlap' => false,
                    'slotEventOverlap' => false,
                ],
            ],
            'allDaySlot' => false,
            'slotMinTime' => '06:00:00',
            'slotMaxTime' => '24:00:00',
            'slotDuration' => '00:10:00',
            'slotLabelInterval' => '01:00',
            'slotLabelFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'hour12' => false,
            ],
            'nowIndicator' => true,
            'stickyHeaderDates' => true,
            'expandRows' => true,
            'eventMinHeight' => 36,
            'slotEventOverlap' => true,
            'eventOverlap' => true,
            'eventOrder' => 'start',
            'dayMaxEventRows' => false,
        ];
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('client_id')
                ->label('Cliente')
                ->relationship('client', 'last_name')
                ->getOptionLabelFromRecordUsing(function (Client $record): string {
                    $firstName = trim((string) ($record->first_name ?? ''));
                    $lastName = trim((string) ($record->last_name ?? ''));
                    $fullName = trim($lastName . ' ' . $firstName);
                    $phone = trim((string) ($record->phone ?? ''));

                    if ($fullName !== '' && $phone !== '') {
                        return $fullName . ' - ' . $phone;
                    }

                    if ($fullName !== '') {
                        return $fullName;
                    }

                    return $phone !== '' ? $phone : 'Cliente senza nome';
                })
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->createOptionForm([
                    Forms\Components\TextInput::make('first_name')
                        ->label('Nome')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('last_name')
                        ->label('Cognome')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefono')
                        ->required()
                        ->unique(table: Client::class, column: 'phone')
                        ->maxLength(255),
                ]),

            Forms\Components\Placeholder::make('client_phone')
                ->label('Telefono cliente')
                ->content(function (callable $get): string {
                    $clientId = $get('client_id');

                    if (blank($clientId)) {
                        return '-';
                    }

                    $phone = Client::query()->whereKey($clientId)->value('phone');

                    return filled($phone) ? (string) $phone : '-';
                }),

            Forms\Components\Select::make('staff_id')
                ->label('Staff')
                ->relationship('staff', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),
                ]),

            Forms\Components\Select::make('services')
                ->label('Servizi')
                ->relationship('services', 'name')
                ->multiple()
                ->preload()
                ->searchable(),

            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data e ora')
                ->minDate(now()->startOfMinute())
                ->seconds(false)
                ->reactive(),

            Forms\Components\Textarea::make('notes')
                ->label('Note')
                ->columnSpanFull(),
        ];
    }

    protected function configureAction(Action $action): void
    {
        if (! $action instanceof \Saade\FilamentFullCalendar\Actions\CreateAction) {
            if (! $action instanceof EditAction) {
                return;
            }
        }

        if ($action instanceof \Saade\FilamentFullCalendar\Actions\CreateAction) {
            $action
                ->mountUsing(function (Form $form, array $arguments): void {
                    $start = $arguments['start'] ?? null;

                    $form->fill([
                        'scheduled_at' => $start,
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $hasSchedule = filled($data['scheduled_at'] ?? null);

                    $data['scheduled_at'] = $hasSchedule ? $data['scheduled_at'] : null;
                    $data['status'] = $hasSchedule ? 'confirmed' : 'pending';

                    return $data;
                })
                ->after(fn ($livewire) => $livewire->refreshRecords());

            return;
        }

        $action->extraModalFooterActions([
            Action::make('whatsapp')
                ->label('Conferma')
                ->color('success')
                ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->action(function (Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $url = $whatsAppService->sendAppointmentConfirmation($record);

                    $livewire->js('window.location.href = ' . json_encode($url));
                }),
            Action::make('whatsapp_reminder')
                ->label('Ricorda')
                ->color('warning')
                ->icon('heroicon-o-bell-alert')
                ->action(function (Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $url = $whatsAppService->sendAppointmentReminder($record);

                    $livewire->js('window.location.href = ' . json_encode($url));
                }),
            Action::make('delete')
                ->label('Elimina')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modal()
                ->modalIcon('heroicon-o-trash')
                ->modalIconColor('danger')
                ->cancelParentActions()
                ->modalHeading('Elimina appuntamento')
                ->modalDescription('Confermi l\'eliminazione di questo appuntamento?')
                ->modalSubmitActionLabel('Elimina')
                ->action(function (Appointment $record, $livewire): void {
                    $record->delete();

                    $livewire->refreshRecords();
                }),
        ]);
    }

    /**
     * Recupera gli eventi da mostrare nel calendario
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $appointmentsCollection = Appointment::query()
            ->where('status', 'confirmed')
            ->whereNotNull('scheduled_at')
            ->with(['client', 'staff', 'services'])
            ->get()
            ->sortBy(fn (Appointment $appointment) => $appointment->scheduled_at?->timestamp ?? 0)
            ->values();

        $stackCounts = $appointmentsCollection
            ->groupBy(fn (Appointment $appointment) => $appointment->scheduled_at->format('Y-m-d H:i'))
            ->map(fn ($group) => $group->count());

        $stackGlobalMax = $stackCounts->max() ?? 1;

        $stackCursor = [];

        $appointments = $appointmentsCollection
            ->map(function (Appointment $appointment) use (&$stackCursor, $stackCounts, $stackGlobalMax) {
                $firstName = trim((string) ($appointment->client?->first_name ?? ''));
                $lastName = trim((string) ($appointment->client?->last_name ?? ''));
                $clientName = trim($lastName . ' ' . $firstName);

                if ($clientName === '') {
                    $clientName = 'Appuntamento';
                }

                $serviceColors = $appointment->services
                    ->pluck('color')
                    ->map(fn (?string $color) => trim((string) $color))
                    ->filter()
                    ->values();

                $serviceNames = $appointment->services
                    ->pluck('name')
                    ->map(fn (?string $name) => $this->abbreviateServiceName((string) ($name ?? '')))
                    ->filter()
                    ->values();

                $serviceColor = $serviceColors->first();
                $serviceLabel = $serviceNames->implode(' + ');
                $staffName = trim((string) ($appointment->staff?->name ?? ''));

                $stackKey = $appointment->scheduled_at->format('Y-m-d H:i');
                $stackIndex = $stackCursor[$stackKey] ?? 0;
                $stackCursor[$stackKey] = $stackIndex + 1;
                return [
                    'id'    => $appointment->id,
                    'title' => $clientName,
                    'start' => $appointment->scheduled_at->toIso8601String(),
                    'end' => $appointment->scheduled_at->copy()->addMinutes($appointment->durationMinutes())->toIso8601String(),
                    'displayTime' => $appointment->scheduled_at->format('H:i'),
                    'backgroundColor' => $serviceColor ?: '#16a34a',
                    'borderColor' => $serviceColor ?: '#16a34a',
                    'serviceLabel' => $serviceLabel,
                    'staffName' => $staffName,
                    'stackIndex' => $stackIndex,
                    'stackCount' => $stackCounts->get($stackKey, 1),
                    'stackGlobalMax' => $stackGlobalMax,
                ];
            })
            ->toArray();

        $backgrounds = [];
        $rangeStart = $fetchInfo['start'] ?? $fetchInfo['startStr'] ?? null;
        $rangeEnd = $fetchInfo['end'] ?? $fetchInfo['endStr'] ?? null;

        if ($rangeStart && $rangeEnd) {
            $cursor = Carbon::parse($rangeStart)->startOfDay();
            $end = Carbon::parse($rangeEnd)->startOfDay();

            while ($cursor->lt($end)) {
                $morningStart = $cursor->copy()->setTime(6, 0);
                $morningEnd = $cursor->copy()->setTime(13, 30);
                $eveningStart = $morningEnd->copy();
                $eveningEnd = $cursor->copy()->addDay()->startOfDay();

                $backgrounds[] = [
                    'id' => 'bg-mattina-' . $cursor->toDateString(),
                    'start' => $morningStart->toIso8601String(),
                    'end' => $morningEnd->toIso8601String(),
                    'display' => 'background',
                    'classNames' => ['bg-mattina'],
                ];

                $backgrounds[] = [
                    'id' => 'bg-pomeriggio-' . $cursor->toDateString(),
                    'start' => $eveningStart->toIso8601String(),
                    'end' => $eveningEnd->toIso8601String(),
                    'display' => 'background',
                    'classNames' => ['bg-pomeriggio'],
                ];

                $cursor->addDay();
            }
        }

        return array_merge($backgrounds, $appointments);
    }

    public function eventClassNames(): string
    {
        return <<<'JS'
            function() {
                return ['zaga-event'];
            }
        JS;
    }

    public function eventContent(): string
    {
        return <<<'JS'
            function(arg) {
                const title = arg.event.title || '';
                const serviceLabel = arg.event.extendedProps?.serviceLabel || '';
                const displayTime = arg.event.extendedProps?.displayTime || '';
                const isDayView = arg.view?.type === 'dayGridDay';
                const titleFontSize = isDayView ? '15.5px' : '13px';
                const detailFontSize = isDayView ? '14.5px' : '12px';
                const detailLine = (displayTime || serviceLabel)
                    ? `<div style="font-size:${detailFontSize};opacity:.95;margin-top:2px;">${displayTime} ${serviceLabel}</div>`
                    : '';

                return {
                    html: `<div style="width:100%;min-height:42px;line-height:1.15;">
                        <div style="font-weight:700;font-size:${titleFontSize};">${title}</div>
                        ${detailLine}
                    </div>`,
                };
            }
        JS;
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
            function(info) {
                const el = info.el;
                const staffName = info.event.extendedProps?.staffName || '';
                const isDayView = info.view?.type === 'dayGridDay';
                const isAppointment = info.event.display !== 'background';
                const bg = info.event.backgroundColor || '#16a34a';
                el.style.backgroundColor = bg;
                el.style.borderColor = bg;
                el.style.color = '#ffffff';
                el.style.borderRadius = '8px';
                el.style.padding = '2px 6px';
                el.style.boxShadow = '0 1px 2px rgba(0,0,0,0.2)';
                el.style.position = 'relative';

                if (isDayView && isAppointment && staffName) {
                    el.dataset.staffName = staffName;
                    el.style.paddingRight = '35%';

                    const staffBadge = document.createElement('div');
                    staffBadge.textContent = staffName;
                    staffBadge.style.position = 'absolute';
                    staffBadge.style.right = '10px';
                    staffBadge.style.bottom = '6px';
                    staffBadge.style.maxWidth = '32%';
                    staffBadge.style.overflow = 'hidden';
                    staffBadge.style.textOverflow = 'ellipsis';
                    staffBadge.style.whiteSpace = 'nowrap';
                    staffBadge.style.textAlign = 'right';
                    staffBadge.style.fontSize = '14.5px';
                    staffBadge.style.fontWeight = '700';
                    staffBadge.style.lineHeight = '1.1';
                    staffBadge.style.pointerEvents = 'none';
                    staffBadge.style.zIndex = '2';
                    el.appendChild(staffBadge);
                }

                const pushStaffStats = () => {
                    const calendarEl = el.closest('.filament-fullcalendar');
                    const staffCounts = {};

                    if (isDayView && calendarEl) {
                        calendarEl.querySelectorAll('.fc-event[data-staff-name]').forEach((eventEl) => {
                            const staff = eventEl.dataset.staffName || '';

                            if (staff) {
                                staffCounts[staff] = (staffCounts[staff] || 0) + 1;
                            }
                        });
                    }

                    const staffStats = Object.entries(staffCounts)
                        .sort(([staffA], [staffB]) => staffA.localeCompare(staffB))
                        .map(([staff, count]) => ({ staff, count }));

                    window.dispatchEvent(new CustomEvent('calendar-staff-stats', {
                        detail: {
                            isDayView,
                            calendarDay: isDayView && info.view?.currentStart
                                ? info.view.currentStart.toLocaleDateString('it-IT')
                                : '',
                            staffStats,
                        },
                    }));
                };

                requestAnimationFrame(pushStaffStats);

                if (!el.classList.contains('fc-timegrid-event')) {
                    return;
                }

                const stackIndex = Number(info.event.extendedProps?.stackIndex || 0);
                const stackCount = Number(info.event.extendedProps?.stackCount || 1);
                const harness = el.closest('.fc-timegrid-event-harness');
                if (harness) {
                    harness.style.left = '0';
                    harness.style.right = '0';
                    harness.style.width = '100%';
                    harness.style.zIndex = String(10 + stackIndex);
                }

                let attempts = 0;
                const applyStacking = () => {
                    const fullHeight = (harness?.offsetHeight || 0) || (el.offsetHeight || 0);
                    if (fullHeight < 12) {
                        if (attempts < 6) {
                            attempts += 1;
                            setTimeout(applyStacking, 40);
                        }
                        return;
                    }

                    if (stackCount > 1) {
                        const slice = fullHeight / stackCount;
                        const height = Math.max(12, Math.floor(slice) - 2);
                        const offset = slice * stackIndex;

                        if (harness) {
                            harness.style.height = `${height}px`;
                            harness.style.maxHeight = `${height}px`;
                            harness.style.transform = `translateY(${offset}px)`;
                        }

                        el.style.height = '100%';
                        el.style.maxHeight = '100%';
                        el.style.transform = '';
                    } else {
                        if (harness) {
                            harness.style.height = '';
                            harness.style.maxHeight = '';
                            harness.style.transform = '';
                        }

                        el.style.height = '';
                        el.style.maxHeight = '';
                        el.style.transform = '';
                    }
                };

                requestAnimationFrame(applyStacking);
            }
        JS;
    }

    public function eventWillUnmount(): string
    {
        return <<<'JS'
            function(info) {
                requestAnimationFrame(() => {
                    const isDayView = info.view?.type === 'dayGridDay';
                    const calendarEl = document.querySelector('.filament-fullcalendar');
                    const staffCounts = {};

                    if (isDayView && calendarEl) {
                        calendarEl.querySelectorAll('.fc-event[data-staff-name]').forEach((eventEl) => {
                            const staff = eventEl.dataset.staffName || '';

                            if (staff) {
                                staffCounts[staff] = (staffCounts[staff] || 0) + 1;
                            }
                        });
                    }

                    const staffStats = Object.entries(staffCounts)
                        .sort(([staffA], [staffB]) => staffA.localeCompare(staffB))
                        .map(([staff, count]) => ({ staff, count }));

                    window.dispatchEvent(new CustomEvent('calendar-staff-stats', {
                        detail: {
                            isDayView,
                            calendarDay: isDayView && info.view?.currentStart
                                ? info.view.currentStart.toLocaleDateString('it-IT')
                                : '',
                            staffStats,
                        },
                    }));
                });
            }
        JS;
    }

    public function onEventClick(array $event): void
    {
        if ($this->getModel()) {
            $this->record = $this->resolveRecord($event['id']);
        }

        $this->mountAction('edit', [
            'type' => 'click',
            'event' => $event,
        ]);
    }

    public function refreshRecords(): void
    {
        $this->dispatch('filament-fullcalendar--refresh');
        $this->dispatch('appointments-to-schedule--refresh');
    }

    private function abbreviateServiceName(string $name): string
    {
        $value = trim($name);
        if ($value === '') {
            return '';
        }

        $replacements = [
            'spazzolatura' => 'Spazz.',
            'toelettatura' => 'Toelett.',
        ];

        foreach ($replacements as $search => $replace) {
            $value = str_ireplace($search, $replace, $value);
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
