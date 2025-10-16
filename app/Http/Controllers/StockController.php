<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    /**
     * Display a listing of stocks.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Stock::with('product');
        
        // Filter by low stock
        if ($request->has('low_stock') && $request->low_stock) {
            $query->whereRaw('quantity <= low_stock_threshold');
        }
        
        // Filter by in stock
        if ($request->has('in_stock')) {
            if ($request->in_stock) {
                $query->where('quantity', '>', 0);
            } else {
                $query->where('quantity', 0);
            }
        }
        
        $stocks = $query->paginate($request->per_page ?? 10);
        
        return response()->json([
            'status' => true,
            'message' => 'Stocks retrieved successfully',
            'data' => $stocks
        ], 200);
    }

    /**
     * Update the stock quantity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $stock = Stock::find($id);
        
        if (!$stock) {
            return response()->json([
                'status' => false,
                'message' => 'Stock not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
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

        $stock->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Stock updated successfully',
            'data' => $stock
        ], 200);
    }

    /**
     * Get low stock products.
     *
     * @return \Illuminate\Http\Response
     */
    public function lowStock()
    {
        $lowStocks = Stock::with('product')
            ->whereRaw('quantity <= low_stock_threshold')
            ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Low stock products retrieved successfully',
            'data' => $lowStocks
        ], 200);
    }

    /**
     * Adjust stock quantity (increment/decrement).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function adjust(Request $request, $id)
    {
        $stock = Stock::find($id);
        
        if (!$stock) {
            return response()->json([
                'status' => false,
                'message' => 'Stock not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'adjustment' => 'required|integer',
            'reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure stock doesn't go below zero
        $newQuantity = $stock->quantity + $request->adjustment;
        if ($newQuantity < 0) {
            return response()->json([
                'status' => false,
                'message' => 'Stock cannot be reduced below zero',
                'current_stock' => $stock->quantity,
                'requested_adjustment' => $request->adjustment
            ], 422);
        }

        $stock->quantity = $newQuantity;
        $stock->save();

        return response()->json([
            'status' => true,
            'message' => 'Stock adjusted successfully',
            'data' => [
                'stock' => $stock,
                'adjustment' => $request->adjustment,
                'reason' => $request->reason
            ]
        ], 200);
    }
}