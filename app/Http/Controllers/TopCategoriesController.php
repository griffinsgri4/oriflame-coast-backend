<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TopCategoriesController extends Controller
{
    /**
     * GET /api/analytics/top-categories
     * Returns categories sorted by sales_count with optional period and limit.
     * Query params: period=30d|90d|all, limit=10
     */
    public function index(Request $request)
    {
        $period = $request->query('period', '90d');
        $limit = (int) $request->query('limit', 10);
        $startDate = null;
        if ($period !== 'all' && preg_match('/^(\d+)d$/', $period, $m)) {
            $startDate = Carbon::now()->subDays((int) $m[1]);
        }
        $cacheKey = 'analytics:top-categories:' . ($period ?: 'all') . ':' . $limit;
        $categories = Cache::remember($cacheKey, 60, function () use ($startDate, $limit) {
            $salesQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.payment_status', 'paid')
                ->selectRaw("LOWER(REPLACE(COALESCE(products.category, 'Uncategorized'), ' ', '-')) as slug")
                ->selectRaw('SUM(order_items.quantity) as sales_count')
                ->groupBy('slug')
                ->orderByDesc('sales_count');

            if ($startDate) {
                $salesQuery->where('orders.created_at', '>=', $startDate);
            }

            $productCounts = DB::table('products')
                ->selectRaw("LOWER(REPLACE(COALESCE(category, 'Uncategorized'), ' ', '-')) as slug")
                ->selectRaw('COUNT(*) as product_count')
                ->groupBy('slug');

            return DB::table('categories')
                ->rightJoinSub($salesQuery, 'sc', 'categories.slug', '=', 'sc.slug')
                ->leftJoinSub($productCounts, 'pc', 'sc.slug', '=', 'pc.slug')
                ->selectRaw('COALESCE(categories.name, sc.slug) as name')
                ->selectRaw('sc.slug as slug')
                ->selectRaw('COALESCE(categories.thumbnail_url, NULL) as thumbnail_url')
                ->selectRaw('COALESCE(pc.product_count, 0) as product_count')
                ->selectRaw('sc.sales_count as sales_count')
                ->orderByDesc('sales_count')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    $row->name = $row->name ?: Str::title(str_replace('-', ' ', $row->slug));
                    return $row;
                });
        });

        $etag = sha1(json_encode($categories));
        return response()->json(['data' => $categories])
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=60');
    }
}