<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    private const RELATIONS = ['client', 'staff', 'services'];

    private const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];

    /**
     * Lista appuntamenti. Di default solo quelli con data; con `include_undated=1` anche quelli senza.
     * Filtri: from, to (su scheduled_at), status, staff_id, client_id, q (nome/cognome/telefono del cliente).
     * Senza `per_page` restituisce tutto (comodo per il calendario).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'staff_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'include_undated' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Appointment::query()
            ->with(self::RELATIONS)
            ->orderByRaw('scheduled_at is null desc')
            ->orderBy('scheduled_at');

        if (! $request->boolean('include_undated')) {
            $query->whereNotNull('scheduled_at');
        }

        if (! empty($filters['from'])) {
            $query->where('scheduled_at', '>=', $this->toAppTimezone($filters['from']));
        }

        if (! empty($filters['to'])) {
            $query->where('scheduled_at', '<', $this->toAppTimezone($filters['to']));
        }

        foreach (array_filter(preg_split('/\s+/', trim($filters['q'] ?? ''))) as $term) {
            $like = '%' . $term . '%';

            $query->whereHas('client', fn ($client) => $client->where(fn ($inner) => $inner
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like)));
        }

        foreach (['status', 'staff_id', 'client_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return AppointmentResource::collection(
            isset($filters['per_page']) ? $query->paginate($filters['per_page']) : $query->get()
        );
    }

    /**
     * Appuntamenti "da programmare": senza data, in stato pending.
     */
    public function toSchedule(): AnonymousResourceCollection
    {
        return AppointmentResource::collection(
            Appointment::query()->pending()->with(self::RELATIONS)->latest()->get()
        );
    }

    /**
     * Crea un appuntamento. Il cliente si passa con `client_id` oppure con
     * `client` {phone, first_name, last_name}: se il telefono esiste viene recuperato, altrimenti creato.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required_without:client', 'nullable', 'integer', 'exists:clients,id'],
            'client' => ['required_without:client_id', 'nullable', 'array'],
            'client.phone' => ['required_with:client', 'string', 'max:255'],
            'client.first_name' => ['nullable', 'string', 'max:255'],
            'client.last_name' => ['nullable', 'string', 'max:255'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $appointment = DB::transaction(function () use ($data) {
            $clientId = $data['client_id'] ?? Client::firstOrCreate(
                ['phone' => $data['client']['phone']],
                [
                    'first_name' => $data['client']['first_name'] ?? null,
                    'last_name' => $data['client']['last_name'] ?? null,
                ]
            )->id;

            $scheduledAt = $this->toAppTimezone($data['scheduled_at'] ?? null);

            $appointment = Appointment::create([
                'client_id' => $clientId,
                'staff_id' => $data['staff_id'],
                'scheduled_at' => $scheduledAt,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? ($scheduledAt ? 'confirmed' : 'pending'),
            ]);

            $appointment->services()->sync($data['service_ids'] ?? []);

            return $appointment;
        });

        return (new AppointmentResource($appointment->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->load(self::RELATIONS));
    }

    public function update(Request $request, Appointment $appointment): AppointmentResource
    {
        $data = $request->validate([
            'client_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
            'staff_id' => ['sometimes', 'required', 'integer', 'exists:staff,id'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        DB::transaction(function () use ($data, $appointment): void {
            $attributes = collect($data)->only(['client_id', 'staff_id', 'notes', 'status'])->all();

            if (array_key_exists('scheduled_at', $data)) {
                $attributes['scheduled_at'] = $this->toAppTimezone($data['scheduled_at']);

                // Come nel gestionale: dare una data a un pending lo conferma.
                if ($attributes['scheduled_at'] && ! isset($data['status']) && $appointment->status === 'pending') {
                    $attributes['status'] = 'confirmed';
                }
            }

            $appointment->update($attributes);

            if (array_key_exists('service_ids', $data)) {
                $appointment->services()->sync($data['service_ids']);
            }
        });

        return new AppointmentResource($appointment->load(self::RELATIONS));
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(null, 204);
    }

    /**
     * Link wa.me pronto da aprire. `type`: confirmation | reminder.
     * La conferma segna l'appuntamento come whatsapp_sent.
     */
    public function whatsapp(Appointment $appointment, string $type, WhatsAppService $whatsApp): JsonResponse
    {
        abort_unless(in_array($type, ['confirmation', 'reminder'], true), 404);

        if ($type === 'confirmation') {
            $appointment->update(['whatsapp_sent' => true]);
        }

        $url = $type === 'confirmation'
            ? $whatsApp->sendAppointmentConfirmation($appointment)
            : $whatsApp->sendAppointmentReminder($appointment);

        return response()->json(['url' => $url]);
    }

    private function toAppTimezone(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
    }
}
