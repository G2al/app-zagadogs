<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffController extends Controller
{
    private const COLOR_RULE = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function index(): AnonymousResourceCollection
    {
        return StaffResource::collection(Staff::orderBy('name')->get());
    }

    /** Senza `color` l'operatrice riceve in automatico il primo colore della tavolozza non ancora usato. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', self::COLOR_RULE],
        ]);

        return (new StaffResource(Staff::create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Staff $staff): StaffResource
    {
        return new StaffResource($staff);
    }

    public function update(Request $request, Staff $staff): StaffResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', 'required', 'string', self::COLOR_RULE],
        ]);

        $staff->update($data);

        return new StaffResource($staff);
    }

    public function destroy(Staff $staff): JsonResponse
    {
        $staff->delete();

        return response()->json(null, 204);
    }
}
