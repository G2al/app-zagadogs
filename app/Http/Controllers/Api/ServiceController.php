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
    private const RELATIONS = ['category', 'staff'];

    /**
     * Filtri facoltativi:
     * - ?category_id= servizi di quella categoria e delle sue sottocategorie;
     * - ?staff_id= servizi che quell'operatrice può eseguire: quelli collegati a lei e quelli senza collegamenti (tutte).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Service::query()->with(self::RELATIONS)->orderBy('name');

        if ($request->filled('category_id')) {
            $category = ServiceCategory::findOrFail((int) $request->query('category_id'));
            $query->whereIn('category_id', $category->selfAndChildrenIds());
        }

        if ($request->filled('staff_id')) {
            $staffId = (int) $request->query('staff_id');

            $query->where(fn ($inner) => $inner
                ->whereHas('staff', fn ($staff) => $staff->whereKey($staffId))
                ->orDoesntHave('staff'));
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
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer', 'exists:staff,id'],
        ]);

        $service = Service::create(collect($data)->except('staff_ids')->all());
        $service->staff()->sync($data['staff_ids'] ?? []);

        return (new ServiceResource($service->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($service->load(self::RELATIONS));
    }

    /** Con `staff_ids` le operatrici collegate vengono sostituite; senza il campo restano quelle che erano. */
    public function update(Request $request, Service $service): ServiceResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:720'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:service_categories,id'],
            'staff_ids' => ['sometimes', 'nullable', 'array'],
            'staff_ids.*' => ['integer', 'exists:staff,id'],
        ]);

        $service->update(collect($data)->except('staff_ids')->all());

        if (array_key_exists('staff_ids', $data)) {
            $service->staff()->sync($data['staff_ids'] ?? []);
        }

        return new ServiceResource($service->load(self::RELATIONS));
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(null, 204);
    }
}
