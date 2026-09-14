<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PosApiController extends Controller
{
    /**
     * Search products by barcode (SKU) or keyword for POS Cashier Terminal.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->input('query', '');

        $products = Product::active()
            ->with(['primaryImage', 'variants'])
            ->where(function ($q) use ($query) {
                $q->where('sku', $query)
                  ->orWhere('name', 'like', "%{$query}%")
                  ->orWhereHas('variants', function ($vq) use ($query) {
                      $vq->where('sku', $query);
                  });
            })
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'products' => $products->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => $p->effective_price,
                    'stock' => $p->stock,
                    'image' => $p->primaryImage?->path,
                    'has_variants' => $p->variants->isNotEmpty(),
                    'variants' => $p->variants->map(function ($v) {
                        return [
                            'id' => $v->id,
                            'sku' => $v->sku,
                            'price' => $v->effective_price,
                            'stock' => $v->stock,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * POS Quick Checkout & Instant Receipt Generation.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,card,mfs,split',
            'amount_paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $unitPrice = $product->effective_price;
                $lineTotal = $unitPrice * $item['quantity'];
                $subtotal += $lineTotal;

                $orderItemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineTotal,
                ];

                // Deduct inventory
                if ($product->manage_stock) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            $discount = (float) ($validated['discount'] ?? 0);
            $tax = (float) ($validated['tax'] ?? 0);
            $total = max(0, ($subtotal - $discount) + $tax);

            $order = Order::create([
                'user_id' => $validated['customer_id'] ?? null,
                'order_number' => 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                'status' => 'delivered', // POS items are immediately handed over
                'payment_status' => 'paid',
                'currency' => 'USD',
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'shipping_amount' => 0,
                'tax_amount' => $tax,
                'total' => $total,
                'shipping_method' => 'In-Store POS Pickup',
                'customer_note' => $validated['notes'] ?? 'In-Store Walk-in Customer',
                'admin_note' => 'Processed via POS Terminal by Cashier #' . (Auth::id() ?? '1'),
                'paid_at' => now(),
                'delivered_at' => now(),
            ]);

            foreach ($orderItemsData as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                    'total' => $line['subtotal'],
                ]);
            }

            // Record transaction
            Payment::create([
                'order_id' => $order->id,
                'user_id' => $validated['customer_id'] ?? null,
                'method' => $validated['payment_method'],
                'amount' => $validated['amount_paid'],
                'currency' => 'USD',
                'status' => 'completed',
                'transaction_id' => 'TXN-POS-' . uniqid(),
                'paid_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'delivered',
                'comment' => 'Completed via Point of Sale (POS) cashier counter.',
                'changed_by' => Auth::id() ?? 1,
            ]);

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'POS order processed successfully!',
            'order' => [
                'order_number' => $order->order_number,
                'total' => $order->total,
                'amount_paid' => $validated['amount_paid'],
                'change' => max(0, $validated['amount_paid'] - $order->total),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
