<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Lista clienti, con ricerca `q` su nome, cognome, telefono.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::query()->orderBy('last_name')->orderBy('first_name');

        $terms = array_filter(preg_split('/\s+/', trim((string) $request->query('q', ''))));

        foreach ($terms as $term) {
            $like = '%' . $term . '%';

            $query->where(fn ($inner) => $inner
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like));
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        return ClientResource::collection($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', 'unique:clients,phone'],
            'notes' => ['nullable', 'string'],
        ]);

        return (new ClientResource(Client::create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client);
    }

    public function update(Request $request, Client $client): ClientResource
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('clients', 'phone')->ignore($client->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $client->update($data);

        return new ClientResource($client);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(null, 204);
    }
}
