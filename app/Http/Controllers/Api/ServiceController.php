<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    /** Con ?category_id= restituisce i servizi di quella categoria (e delle sue sottocategorie). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Service::query()->with('category')->orderBy('name');

        if ($request->filled('category_id')) {
            $category = ServiceCategory::findOrFail((int) $request->query('category_id'));
            $query->whereIn('category_id', $category->selfAndChildrenIds());
        }

        return ServiceResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:720'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
        ]);

        return (new ServiceResource(Service::create($data)->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($service->load('category'));
    }

    public function update(Request $request, Service $service): ServiceResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:720'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:service_categories,id'],
        ]);

        $service->update($data);

        return new ServiceResource($service->load('category'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(null, 204);
    }
}
