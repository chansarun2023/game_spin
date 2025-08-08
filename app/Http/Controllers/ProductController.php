<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Get all products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by stock availability
        if ($request->has('in_stock')) {
            if ($request->boolean('in_stock')) {
                $query->inStock();
            } else {
                $query->where('stock', 0);
            }
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort by
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Products retrieved successfully',
        ]);
    }

    /**
     * Get a specific product
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product retrieved successfully',
        ]);
    }

    /**
     * Create a new product
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products,code',
            'description' => 'nullable|string',
            'point_cost' => 'required|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'stock' => 'integer|min:-1', // -1 means unlimited
        ]);

        $product = Product::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product created successfully',
        ], 201);
    }

    /**
     * Update a product
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:products,code,' . $id,
            'description' => 'nullable|string',
            'point_cost' => 'sometimes|required|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'stock' => 'integer|min:-1',
        ]);

        $product->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * Delete a product
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        // Check if product has any claimed rewards
        $claimedRewards = $product->rewards()->where('status', 'claimed')->count();
        if ($claimedRewards > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product with claimed rewards. Consider deactivating instead.',
            ], 400);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Get active products only
     */
    public function active(): JsonResponse
    {
        $products = Product::active()->inStock()->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Active products retrieved successfully',
        ]);
    }

    /**
     * Get products by point cost range
     */
    public function byPointRange(Request $request): JsonResponse
    {
        $request->validate([
            'min_points' => 'required|integer|min:0',
            'max_points' => 'required|integer|min:0|gte:min_points',
        ]);

        $products = Product::active()
            ->whereBetween('point_cost', [$request->min_points, $request->max_points])
            ->orderBy('point_cost', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Products in point range retrieved successfully',
        ]);
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.point_cost' => 'sometimes|integer|min:0',
            'products.*.is_active' => 'sometimes|boolean',
            'products.*.stock' => 'sometimes|integer|min:-1',
        ]);

        $updatedCount = 0;
        foreach ($request->products as $productData) {
            $product = Product::find($productData['id']);
            unset($productData['id']); // Remove ID from update data
            $product->update($productData);
            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'updated_count' => $updatedCount,
            ],
            'message' => "Successfully updated {$updatedCount} products",
        ]);
    }
}
