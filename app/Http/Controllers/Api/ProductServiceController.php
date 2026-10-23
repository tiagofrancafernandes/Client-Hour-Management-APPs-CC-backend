<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('product_service.view_any');

        $query = ProductService::query();

        $search = $request->input('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $type = $request->input('type');

        if ($type) {
            $query->where('type', $type);
        }

        $isActive = $request->input('is_active');

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = (string) $request->input('sort_by', 'name');
        $sortDirection = strtolower((string) $request->input('sort_direction', 'asc'));

        $allowedSortFields = [
            'name',
            'type',
            'unit',
            'default_price',
            'default_quantity',
            'is_active',
            'created_at',
        ];

        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'name';
        }

        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'asc';
        }

        $query->orderBy($sortBy, $sortDirection);

        if ($request->boolean('all')) {
            $productsServices = $query->get();

            return response()->json(['data' => $productsServices]);
        }

        $perPage = (int) $request->input('per_page', 20);

        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $productsServices = $query->paginate($perPage);

        return response()->json($productsServices);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('product_service.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:product,service',
            'unit' => 'nullable|string|max:50',
            'default_price' => 'required|numeric|min:0',
            'default_quantity' => 'nullable|numeric|min:0.01',
            'is_active' => 'nullable|boolean',
        ]);

        $productService = ProductService::create($validated);

        return response()->json($productService, 201);
    }

    public function show(ProductService $productService): JsonResponse
    {
        Gate::authorize('product_service.view_any');

        return response()->json($productService);
    }

    public function update(Request $request, ProductService $productService): JsonResponse
    {
        Gate::authorize('product_service.update');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:product,service',
            'unit' => 'nullable|string|max:50',
            'default_price' => 'sometimes|numeric|min:0',
            'default_quantity' => 'nullable|numeric|min:0.01',
            'is_active' => 'nullable|boolean',
        ]);

        $productService->update($validated);

        return response()->json($productService);
    }

    public function destroy(ProductService $productService): JsonResponse
    {
        Gate::authorize('product_service.delete');

        $productService->delete();

        return response()->json(['message' => 'Product/Service deleted successfully']);
    }
}
