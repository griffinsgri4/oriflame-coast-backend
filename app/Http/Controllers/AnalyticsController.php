<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get dashboard analytics data
     */
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->get('period', 30); // Default to 30 days
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();
        
        // Previous period for growth calculations
        $previousStartDate = Carbon::now()->subDays($period * 2);
        $previousEndDate = $startDate;

        try {
            // Total Revenue
            $totalRevenue = Order::where('created_at', '>=', $startDate)
                ->where('payment_status', 'paid')
                ->sum('total');
            
            $previousRevenue = Order::where('created_at', '>=', $previousStartDate)
                ->where('created_at', '<', $previousEndDate)
                ->where('payment_status', 'paid')
                ->sum('total');
            
            $revenueGrowth = $previousRevenue > 0 
                ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 
                : 0;

            // Total Orders
            $totalOrders = Order::where('created_at', '>=', $startDate)->count();
            $previousOrders = Order::where('created_at', '>=', $previousStartDate)
                ->where('created_at', '<', $previousEndDate)
                ->count();
            
            $ordersGrowth = $previousOrders > 0 
                ? (($totalOrders - $previousOrders) / $previousOrders) * 100 
                : 0;

            // Total Customers
            $totalCustomers = User::where('role', 'customer')
                ->where('created_at', '>=', $startDate)
                ->count();
            
            $previousCustomers = User::where('role', 'customer')
                ->where('created_at', '>=', $previousStartDate)
                ->where('created_at', '<', $previousEndDate)
                ->count();
            
            $customersGrowth = $previousCustomers > 0 
                ? (($totalCustomers - $previousCustomers) / $previousCustomers) * 100 
                : 0;

            // Total Products
            $totalProducts = Product::where('status', 'active')->count();
            $previousProducts = Product::where('status', 'active')
                ->where('created_at', '<', $previousEndDate)
                ->count();
            
            $productsGrowth = $previousProducts > 0 
                ? (($totalProducts - $previousProducts) / $previousProducts) * 100 
                : 0;

            // Top Products by Sales
            $topProducts = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.payment_status', 'paid')
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('SUM(order_items.quantity) as sales'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as revenue')
                )
                ->groupBy('products.id', 'products.name')
                ->orderBy('revenue', 'desc')
                ->limit(5)
                ->get();

            // Recent Orders
            $recentOrders = Order::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->order_number,
                        'customer' => $order->user->first_name . ' ' . $order->user->last_name,
                        'total' => $order->total,
                        'status' => $order->status,
                        'date' => $order->created_at->format('Y-m-d')
                    ];
                });

            // Monthly Revenue (last 12 months)
            $monthlyRevenue = [];
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
                $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
                
                $revenue = Order::where('created_at', '>=', $monthStart)
                    ->where('created_at', '<=', $monthEnd)
                    ->where('payment_status', 'paid')
                    ->sum('total');
                
                $monthlyRevenue[] = [
                    'month' => $monthStart->format('M Y'),
                    'revenue' => $revenue
                ];
            }

            // Orders by Status
            $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
                ->where('created_at', '>=', $startDate)
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    $colors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'shipped' => 'bg-purple-100 text-purple-800',
                        'delivered' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    
                    return [
                        'status' => $item->status,
                        'count' => $item->count,
                        'color' => $colors[$item->status] ?? 'bg-gray-100 text-gray-800'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'totalRevenue' => round($totalRevenue, 2),
                    'totalOrders' => $totalOrders,
                    'totalCustomers' => $totalCustomers,
                    'totalProducts' => $totalProducts,
                    'revenueGrowth' => round($revenueGrowth, 1),
                    'ordersGrowth' => round($ordersGrowth, 1),
                    'customersGrowth' => round($customersGrowth, 1),
                    'productsGrowth' => round($productsGrowth, 1),
                    'topProducts' => $topProducts,
                    'recentOrders' => $recentOrders,
                    'monthlyRevenue' => $monthlyRevenue,
                    'ordersByStatus' => $ordersByStatus
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales summary
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $period = $request->get('period', 30);
        $startDate = Carbon::now()->subDays($period);

        try {
            $salesData = Order::where('created_at', '>=', $startDate)
                ->where('payment_status', 'paid')
                ->selectRaw('
                    DATE(created_at) as date,
                    COUNT(*) as orders_count,
                    SUM(total) as total_sales
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $salesData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory summary
     */
    public function inventorySummary(): JsonResponse
    {
        try {
            $lowStockThreshold = 10;
            
            $inventoryData = [
                'totalProducts' => Product::where('status', 'active')->count(),
                'lowStockProducts' => Stock::where('quantity', '<=', $lowStockThreshold)->count(),
                'outOfStockProducts' => Stock::where('quantity', 0)->count(),
                'totalInventoryValue' => DB::table('stocks')
                    ->join('products', 'stocks.product_id', '=', 'products.id')
                    ->where('products.status', 'active')
                    ->sum(DB::raw('stocks.quantity * products.price'))
            ];

            return response()->json([
                'success' => true,
                'data' => $inventoryData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer insights
     */
    public function customerInsights(Request $request): JsonResponse
    {
        $period = $request->get('period', 30);
        $startDate = Carbon::now()->subDays($period);

        try {
            $customerData = [
                'newCustomers' => User::where('role', 'customer')
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'activeCustomers' => User::where('role', 'customer')
                    ->whereHas('orders', function ($query) use ($startDate) {
                        $query->where('created_at', '>=', $startDate);
                    })
                    ->count(),
                'topCustomers' => User::where('role', 'customer')
                    ->withSum(['orders' => function ($query) use ($startDate) {
                        $query->where('created_at', '>=', $startDate)
                              ->where('payment_status', 'paid');
                    }], 'total')
                    ->orderBy('orders_sum_total', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($customer) {
                        return [
                            'id' => $customer->id,
                            'name' => $customer->first_name . ' ' . $customer->last_name,
                            'email' => $customer->email,
                            'totalSpent' => $customer->orders_sum_total ?? 0
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $customerData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer insights',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}