<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Product::with('stock');
        
        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        // Filter by featured
        if ($request->has('featured')) {
            $featured = filter_var($request->featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($featured !== null) {
                $query->where('featured', $featured);
            }
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Enhanced search functionality
        if ($request->has('search')) {
            $searchTerm = trim((string) $request->search);
            if ($searchTerm !== '') {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('sku', 'like', '%' . $searchTerm . '%')
                      ->orWhere('description', 'like', '%' . $searchTerm . '%');
                });
            }
        }
        
        // Search by specific SKU
        if ($request->has('sku')) {
            $query->where('sku', 'like', '%' . $request->sku . '%');
        }
        
        // Search by specific attribute
        if ($request->has('attribute_key') && $request->has('attribute_value')) {
            $query->whereJsonContains('attributes->' . $request->attribute_key, $request->attribute_value);
        }
        
        // Sort products
        $sortField = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortField, $sortOrder);
        
        $products = $query->paginate($request->per_page ?? 10);
        
        return response()->json([
            'status' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products
        ], 200);
    }

    /**
     * Store a newly created product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'gallery' => 'nullable|array',
            'category' => 'required|string',
            'brand' => 'nullable|string',
            'tags' => 'nullable|array',
            'attributes' => 'nullable|array',
            'short_description' => 'nullable|string',
            'how_to_use' => 'nullable|string',
            'ingredients' => 'nullable|string',
            'weight' => 'nullable|string',
            'featured' => 'boolean',
            'status' => 'in:active,inactive',
            'quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create([
            'name' => $request->name,
            'sku' => $request->sku,
            'description' => $request->description,
            'price' => $request->price,
            'original_price' => $request->original_price,
            'sale_price' => $request->sale_price,
            'image' => $request->image,
            'gallery' => $request->gallery,
            'category' => $request->category,
            'brand' => $request->brand,
            'tags' => $request->tags,
            'attributes' => $request->attributes,
            'short_description' => $request->short_description,
            'how_to_use' => $request->how_to_use,
            'ingredients' => $request->ingredients,
            'weight' => $request->weight,
            'featured' => $request->featured ?? false,
            'status' => $request->status ?? 'active'
        ]);

        // Create stock for the product
        $stock = Stock::create([
            'product_id' => $product->id,
            'quantity' => $request->quantity,
            'low_stock_threshold' => $request->low_stock_threshold ?? 5
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Product created successfully',
            'data' => [
                'product' => $product,
                'stock' => $stock
            ]
        ], 201);
    }

    /**
     * Display the specified product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $product = Product::with('stock')->find($id);
        
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Product retrieved successfully',
            'data' => $product
        ], 200);
    }

    /**
     * Update the specified product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'sku' => 'string|max:100|unique:products,sku,' . $id,
            'description' => 'string',
            'price' => 'numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'gallery' => 'nullable|array',
            'category' => 'string',
            'brand' => 'nullable|string',
            'tags' => 'nullable|array',
            'attributes' => 'nullable|array',
            'short_description' => 'nullable|string',
            'how_to_use' => 'nullable|string',
            'ingredients' => 'nullable|string',
            'weight' => 'nullable|string',
            'featured' => 'boolean',
            'status' => 'in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ], 200);
    }

    /**
     * Remove the specified product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ], 200);
    }
}
