<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private function uploadsDisk(): string
    {
        return (string) (config('filesystems.uploads_disk') ?: 'public');
    }

    private function buildPublicImageUrl(int $productId, string $filename): string
    {
        return url('/api/products/' . $productId . '/images/' . $filename);
    }

    private function normalizeImageValue($value, int $productId): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $v = trim($value);
        if ($v === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $v)) {
            $parsed = parse_url($v);
            $path = $parsed['path'] ?? '';
            if (is_string($path) && $path !== '') {
                $v = $path;
            }
        }

        $apiPrefix = '/api/products/' . $productId . '/images/';
        if (strpos($v, $apiPrefix) === 0) {
            $filename = ltrim(substr($v, strlen($apiPrefix)), '/');
            if ($filename !== '' && strpos($filename, '/') === false) {
                return $this->buildPublicImageUrl($productId, $filename);
            }
        }

        $storagePrefix = '/storage/products/' . $productId . '/';
        if (strpos($v, $storagePrefix) === 0) {
            $filename = ltrim(substr($v, strlen($storagePrefix)), '/');
            if ($filename !== '' && strpos($filename, '/') === false) {
                return $this->buildPublicImageUrl($productId, $filename);
            }
        }

        $relativePrefix = 'products/' . $productId . '/';
        if (strpos($v, $relativePrefix) === 0) {
            $filename = ltrim(substr($v, strlen($relativePrefix)), '/');
            if ($filename !== '' && strpos($filename, '/') === false) {
                return $this->buildPublicImageUrl($productId, $filename);
            }
        }

        return $value;
    }

    private function normalizeProductMedia($product)
    {
        if (!$product || !isset($product->id)) {
            return $product;
        }

        $productId = (int) $product->id;
        $product->image = $this->normalizeImageValue($product->image ?? null, $productId);

        $gallery = $product->gallery ?? null;
        if (is_array($gallery)) {
            $product->gallery = array_values(array_filter(array_map(function ($item) use ($productId) {
                return $this->normalizeImageValue($item, $productId);
            }, $gallery)));
        } elseif (is_string($gallery) && trim($gallery) !== '') {
            $product->gallery = array_values(array_filter([
                $this->normalizeImageValue($gallery, $productId),
            ]));
        }

        return $product;
    }

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

        $products->getCollection()->transform(function ($p) {
            return $this->normalizeProductMedia($p);
        });
        
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
                'product' => $this->normalizeProductMedia($product),
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
            'data' => $this->normalizeProductMedia($product)
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
            'data' => $this->normalizeProductMedia($product)
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

    public function uploadImages(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validated = $request->validate([
            'images' => 'required',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $files = $request->file('images');
        if (!$files) {
            $single = $request->file('image');
            $files = $single ? [$single] : [];
        }

        $urls = [];
        $paths = [];
        $disk = $this->uploadsDisk();

        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $ext = 'jpg';
            }

            $filename = Str::uuid()->toString() . '.' . $ext;
            $path = $file->storeAs('products/' . $product->id, $filename, $disk);
            $paths[] = $path;
            $urls[] = $this->buildPublicImageUrl((int) $product->id, $filename);
        }

        return response()->json([
            'status' => true,
            'message' => 'Images uploaded successfully',
            'data' => [
                'urls' => $urls,
                'paths' => $paths,
            ]
        ], 200);
    }

    public function deleteImage(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validated = $request->validate([
            'url' => 'required|string',
        ]);

        $url = (string) $validated['url'];

        $path = $url;
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $path = $parsed['path'] ?? '';
        }

        if (!is_string($path) || $path === '') {
            return response()->json([
                'status' => false,
                'message' => 'Invalid image url'
            ], 422);
        }

        $relative = null;

        $apiPrefix = '/api/products/' . $product->id . '/images/';
        if (strpos($path, $apiPrefix) === 0) {
            $filename = ltrim(substr($path, strlen($apiPrefix)), '/');
            if ($filename !== '' && strpos($filename, '/') === false) {
                $relative = 'products/' . $product->id . '/' . $filename;
            }
        }

        $storagePrefix = '/storage/products/' . $product->id . '/';
        if ($relative === null && strpos($path, $storagePrefix) === 0) {
            $filename = ltrim(substr($path, strlen($storagePrefix)), '/');
            if ($filename !== '' && strpos($filename, '/') === false) {
                $relative = 'products/' . $product->id . '/' . $filename;
            }
        }

        if ($relative === null) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid image url'
            ], 422);
        }

        $deleted = Storage::disk($this->uploadsDisk())->delete($relative);

        return response()->json([
            'status' => true,
            'message' => $deleted ? 'Image deleted' : 'Image not found'
        ], 200);
    }

    /**
     * Serve a product image file directly
     *
     * @param  int  $id
     * @param  string  $filename
     * @return \Illuminate\Http\Response
     */
    public function serveImage($id, $filename)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $disk = $this->uploadsDisk();
        $path = 'products/' . $product->id . '/' . $filename;
        $fs = Storage::disk($disk);

        if (!$fs->exists($path)) {
            return response()->json([
                'status' => false,
                'message' => 'Image not found'
            ], 404);
        }

        $mime = $fs->mimeType($path) ?: 'application/octet-stream';
        $driver = (string) (config('filesystems.disks.' . $disk . '.driver') ?: '');
        if ($driver === 'local') {
            return response()->file($fs->path($path), [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        if (method_exists($fs, 'temporaryUrl')) {
            try {
                $tmp = $fs->temporaryUrl($path, now()->addMinutes(10));
                return redirect()->away($tmp);
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($fs, 'url')) {
            try {
                $url = $fs->url($path);
                if (is_string($url) && preg_match('#^https?://#i', $url)) {
                    return redirect()->away($url);
                }
            } catch (\Throwable $e) {
            }
        }

        $stream = $fs->readStream($path);
        if (!is_resource($stream)) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to read image'
            ], 500);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
