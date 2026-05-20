<?php

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Create a new sales order with line items.
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $data['customer_id'],
                'total_amount' => 0, // Calculated below
                'status' => 'draft',
            ]);

            $totalAmount = 0;

            foreach ($data['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $totalAmount += $itemTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $itemTotal,
                ]);
            }

            $order->update(['total_amount' => $totalAmount]);

            return $order;
        });
    }

    /**
     * Confirm the order and transition to 'confirmed' status.
     */
    public function confirmOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'confirmed',
                'ordered_at' => now(),
            ]);

            // Placeholder for Inventory deduction logic
            // InventoryService::deductStock($order->items);

            return $order;
        });
    }

    /**
     * Generate a unique order number.
     */
    protected function generateOrderNumber(): string
    {
        return 'SO-' . strtoupper(Str::random(8));
    }
}
