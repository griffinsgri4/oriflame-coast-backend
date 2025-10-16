<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     * Returns categories with product_count and sales_count aggregated.
     * Optional query: period (e.g., 30d, 90d, all) for sales window.
     */
    public function index(Request $request)
    {
        $period = $request->query('period', '90d');
        $startDate = null;
        if ($period !== 'all') {
            if (preg_match('/^(\d+)d$/', $period, $m)) {
                $days = (int) $m[1];
                $startDate = Carbon::now()->subDays($days);
            }
        }

        $cacheKey = 'categories:' . ($period ?: 'all');
        $categories = Cache::remember($cacheKey, 60, function () use ($startDate) {
            // Product counts grouped by normalized category slug
            $productCounts = DB::table('products')
                ->selectRaw("LOWER(REPLACE(COALESCE(category, 'Uncategorized'), ' ', '-')) as slug")
                ->selectRaw('COUNT(*) as product_count')
                ->groupBy('slug');

            // Sales counts grouped by normalized category slug
            $salesQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.payment_status', 'paid')
                ->selectRaw("LOWER(REPLACE(COALESCE(products.category, 'Uncategorized'), ' ', '-')) as slug")
                ->selectRaw('SUM(order_items.quantity) as sales_count')
                ->groupBy('slug');

            if ($startDate) {
                $salesQuery->where('orders.created_at', '>=', $startDate);
            }

            return DB::table('categories')
                ->leftJoinSub($productCounts, 'pc', 'categories.slug', '=', 'pc.slug')
                ->leftJoinSub($salesQuery, 'sc', 'categories.slug', '=', 'sc.slug')
                ->select('categories.*')
                ->selectRaw('COALESCE(pc.product_count, 0) as product_count')
                ->selectRaw('COALESCE(sc.sales_count, 0) as sales_count')
                ->orderBy('categories.order', 'asc')
                ->orderBy('categories.name', 'asc')
                ->get();
        });

        if ($categories->isEmpty()) {
            // Fallback: derive categories from products if no categories exist yet
            $pc = DB::table('products')
                ->selectRaw("LOWER(REPLACE(COALESCE(category, 'Uncategorized'), ' ', '-')) as slug")
                ->selectRaw('COUNT(*) as product_count')
                ->groupBy('slug')
                ->get()
                ->keyBy('slug');
            $salesQ = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.payment_status', 'paid')
                ->selectRaw("LOWER(REPLACE(COALESCE(products.category, 'Uncategorized'), ' ', '-')) as slug")
                ->selectRaw('SUM(order_items.quantity) as sales_count')
                ->groupBy('slug');
            if ($startDate) {
                $salesQ->where('orders.created_at', '>=', $startDate);
            }
            $sc = $salesQ->get()->keyBy('slug');

            $derived = [];
            foreach ($pc as $slug => $row) {
                $name = Str::title(str_replace('-', ' ', $slug));
                $derived[] = [
                    'id' => null,
                    'name' => $name,
                    'slug' => $slug,
                    'order' => 0,
                    'thumbnail_url' => null,
                    'meta' => null,
                    'created_at' => null,
                    'updated_at' => null,
                    'product_count' => (int) ($row->product_count ?? 0),
                    'sales_count' => (int) ($sc[$slug]->sales_count ?? 0),
                ];
            }
            return response()->json(['data' => array_values($derived)]);
        }

        // Add simple caching headers
        $etag = sha1(json_encode($categories));
        return response()->json(['data' => $categories])
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=60');
    }

    /** Store a new category (admin) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'order' => 'nullable|integer',
            'thumbnail_url' => 'nullable|string|max:2048',
            'meta' => 'nullable|array',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $data['order'] = $data['order'] ?? 0;

        $category = Category::create($data);
        $this->forgetCategoryCaches();
        return response()->json(['data' => $category], 201);
    }

    /** Update an existing category (admin) */
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:categories,slug,' . $category->id,
            'order' => 'sometimes|nullable|integer',
            'thumbnail_url' => 'sometimes|nullable|string|max:2048',
            'meta' => 'sometimes|nullable|array',
        ]);

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);
        $this->forgetCategoryCaches();
        return response()->json(['data' => $category]);
    }

    /** Delete a category (admin) */
    public function destroy(Category $category)
    {
        $category->delete();
        $this->forgetCategoryCaches();
        return response()->json(['message' => 'Category deleted']);
    }

    /** Upload/replace category thumbnail (admin) */
    public function uploadThumbnail(Request $request, Category $category)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $file = $request->file('image');
        $slug = $category->slug ?: Str::slug($category->name);
        $ext = $file->getClientOriginalExtension();
        $filename = $slug . '-' . time() . '.' . $ext;

        // Store in public disk under categories
        $path = $file->storeAs('categories', $filename, 'public');
        $url = asset('storage/' . $path);

        $category->thumbnail_url = $url;
        $category->save();
        $this->forgetCategoryCaches();

        return response()->json(['data' => $category]);
    }

    private function forgetCategoryCaches(): void
    {
        foreach (['categories:all', 'categories:30d', 'categories:90d'] as $key) {
            Cache::forget($key);
        }
    }
}