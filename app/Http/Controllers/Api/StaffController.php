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
    public function index(): AnonymousResourceCollection
    {
        return StaffResource::collection(Staff::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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
            'name' => ['required', 'string', 'max:255'],
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
