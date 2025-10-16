<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'orderItems.product']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        // Sort orders
        $sortField = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortField, $sortOrder);
        
        $orders = $query->paginate($request->per_page ?? 10);
        
        return response()->json([
            'status' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders
        ], 200);
    }

    /**
     * Store a newly created order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'shipping_address' => 'required|string',
            'payment_method' => 'required|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check stock availability and calculate total
        $total = 0;
        $items = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            $stock = Stock::where('product_id', $item['product_id'])->first();
            
            if (!$product || !$stock) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found',
                    'product_id' => $item['product_id']
                ], 404);
            }
            
            if ($stock->quantity < $item['quantity']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient stock',
                    'product_id' => $item['product_id'],
                    'requested' => $item['quantity'],
                    'available' => $stock->quantity
                ], 422);
            }
            
            $itemTotal = $product->price * $item['quantity'];
            $total += $itemTotal;
            
            $items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $product->price
            ];
        }

        // Start transaction
        DB::beginTransaction();
        
        try {
            // Create order
            $order = Order::create([
                'user_id' => $request->user_id,
                'total' => $total,
                'status' => 'pending',
                'shipping_address' => $request->shipping_address,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'tracking_number' => 'TRK' . strtoupper(substr(md5(uniqid()), 0, 10))
            ]);
            
            // Create order items and update stock
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
                
                // Reduce stock
                $stock = Stock::where('product_id', $item['product_id'])->first();
                $stock->quantity -= $item['quantity'];
                $stock->save();
            }
            
            DB::commit();
            
            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => Order::with(['orderItems.product'])->find($order->id)
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order = Order::with(['user', 'orderItems.product'])->find($id);
        
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order
        ], 200);
    }

    /**
     * Update the specified order status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'in:pending,paid,failed,refunded'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // If order is being cancelled, restore stock
        if ($request->status === 'cancelled' && $order->status !== 'cancelled') {
            DB::beginTransaction();
            
            try {
                $orderItems = OrderItem::where('order_id', $order->id)->get();
                
                foreach ($orderItems as $item) {
                    $stock = Stock::where('product_id', $item->product_id)->first();
                    if ($stock) {
                        $stock->quantity += $item->quantity;
                        $stock->save();
                    }
                }
                
                $order->status = 'cancelled';
                if ($request->has('payment_status')) {
                    $order->payment_status = $request->payment_status;
                }
                $order->save();
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to cancel order',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            $order->status = $request->status;
            if ($request->has('payment_status')) {
                $order->payment_status = $request->payment_status;
            }
            $order->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Order status updated successfully',
            'data' => $order
        ], 200);
    }

    /**
     * Get orders for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function myOrders(Request $request)
    {
        $user = $request->user();
        
        $query = Order::with(['orderItems.product'])
            ->where('user_id', $user->id);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Sort orders
        $sortField = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortField, $sortOrder);
        
        $orders = $query->paginate($request->per_page ?? 10);
        
        return response()->json([
            'status' => true,
            'message' => 'User orders retrieved successfully',
            'data' => $orders
        ], 200);
    }
}