<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Categorie dei servizi su due livelli: categoria (es. Estetica) e sottocategoria (es. Viso).
 */
class ServiceCategoryController extends Controller
{
    /** Albero completo: categorie principali con sottocategorie e servizi, pronto per il selettore. */
    public function index(): AnonymousResourceCollection
    {
        $roots = ServiceCategory::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->with(['services.category', 'children.services.category'])
            ->get();

        return ServiceCategoryResource::collection($roots);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:service_categories,id'],
        ]);

        $this->assertValidParent($data['parent_id'] ?? null);

        return (new ServiceCategoryResource(ServiceCategory::create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        return new ServiceCategoryResource($serviceCategory->load(['services.category', 'children.services.category']));
    }

    public function update(Request $request, ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::notIn([$serviceCategory->id]), 'exists:service_categories,id'],
        ]);

        if (! empty($data['parent_id'])) {
            if ($serviceCategory->children()->exists()) {
                throw ValidationException::withMessages(['parent_id' => ['Una categoria che ha sottocategorie non può diventare una sottocategoria.']]);
            }

            $this->assertValidParent($data['parent_id']);
        }

        $serviceCategory->update($data);

        return new ServiceCategoryResource($serviceCategory->load(['services.category', 'children.services.category']));
    }

    /** I servizi della categoria restano, senza categoria; le sottocategorie diventano principali. */
    public function destroy(ServiceCategory $serviceCategory): JsonResponse
    {
        $serviceCategory->delete();

        return response()->json(null, 204);
    }

    /** Solo due livelli: il "padre" deve essere una categoria principale. */
    private function assertValidParent(?int $parentId): void
    {
        if ($parentId !== null && ServiceCategory::whereKey($parentId)->whereNotNull('parent_id')->exists()) {
            throw ValidationException::withMessages(['parent_id' => ['Le sottocategorie possono stare solo sotto una categoria principale.']]);
        }
    }
}
