<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendar extends FullCalendarWidget
{
    protected static ?string $heading = 'Calendario Appuntamenti';

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
                ->getOptionLabelFromRecordUsing(
                    fn (Client $record): string => trim($record->last_name . ' ' . $record->first_name)
                )
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set('dog_id', null))
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

            Forms\Components\Select::make('dog_id')
                ->label('Cane')
                ->relationship(
                    'dog',
                    'name',
                    modifyQueryUsing: fn (Builder $query, callable $get) => $query->where('client_id', $get('client_id'))
                )
                ->getOptionLabelFromRecordUsing(function (Dog $record): string {
                    $name = trim((string) ($record->name ?? ''));
                    if ($name !== '') {
                        return $name;
                    }

                    $breed = trim((string) ($record->breed ?? ''));
                    if ($breed !== '') {
                        return $breed;
                    }

                    return 'Senza nome';
                })
                ->searchable()
                ->preload()
                ->required()
                ->disabled(fn (callable $get) => blank($get('client_id')))
                ->reactive()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome cane')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('breed')
                        ->label('Razza')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('details')
                        ->label('Dettagli particolari')
                        ->columnSpanFull(),
                ])
                ->createOptionUsing(function (array $data, callable $get): int {
                    $data['client_id'] = $get('client_id');

                    return Dog::query()->create($data)->getKey();
                }),

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
                ->afterFormFilled(function () use ($action): void {
                    $action->extraModalFooterActions([
                        $action->makeModalSubmitAction('createAndWhatsapp', arguments: ['send_whatsapp' => true])
                            ->label('Invia WhatsApp')
                            ->color('success')
                            ->icon('heroicon-o-paper-airplane'),
                    ]);
                })
                ->after(function (array $arguments, Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $livewire->refreshRecords();

                    $sendWhatsApp = (bool) ($arguments['send_whatsapp'] ?? false);
                    if ($sendWhatsApp && filled($record->scheduled_at)) {
                        $record->update(['whatsapp_sent' => true]);

                        $url = $whatsAppService->sendAppointmentConfirmation($record);
                        $encodedUrl = json_encode($url);

                        $livewire->js("window.location.href = {$encodedUrl};");
                    }
                });

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
            ->with(['client', 'dog', 'services'])
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
                    $dogName = trim((string) ($appointment->dog?->name ?? ''));
                    $dogBreed = trim((string) ($appointment->dog?->breed ?? ''));
                    $clientName = $dogName !== '' ? $dogName : $dogBreed;
                }

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

                $stackKey = $appointment->scheduled_at->format('Y-m-d H:i');
                $stackIndex = $stackCursor[$stackKey] ?? 0;
                $stackCursor[$stackKey] = $stackIndex + 1;
                return [
                    'id'    => $appointment->id,
                    'title' => $clientName,
                    'start' => $appointment->scheduled_at->toIso8601String(),
                    'end' => $appointment->scheduled_at->copy()->addMinutes(30)->toIso8601String(),
                    'displayTime' => $appointment->scheduled_at->format('H:i'),
                    'backgroundColor' => $serviceColor ?: '#16a34a',
                    'borderColor' => $serviceColor ?: '#16a34a',
                    'serviceLabel' => $serviceLabel,
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
                const serviceLine = serviceLabel
                    ? `<div style="font-size:12px;opacity:.95;margin-top:2px;">${serviceLabel}</div>`
                    : '';
                const timeLine = displayTime
                    ? `<div style="font-size:11px;opacity:.85;margin-top:2px;">${displayTime}</div>`
                    : '';

                return {
                    html: `<div style="line-height:1.15;">
                        <div style="font-weight:700;font-size:13px;">${title}</div>
                        ${serviceLine}
                        ${timeLine}
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
                const bg = info.event.backgroundColor || '#16a34a';
                el.style.backgroundColor = bg;
                el.style.borderColor = bg;
                el.style.color = '#ffffff';
                el.style.borderRadius = '8px';
                el.style.padding = '2px 6px';
                el.style.boxShadow = '0 1px 2px rgba(0,0,0,0.2)';

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
                    const fullHeight = el.offsetHeight || 0;
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
                        el.style.height = `${height}px`;
                        el.style.maxHeight = `${height}px`;
                        el.style.transform = `translateY(${offset}px)`;
                    } else {
                        el.style.height = '';
                        el.style.maxHeight = '';
                        el.style.transform = '';
                    }
                };

                requestAnimationFrame(applyStacking);
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
