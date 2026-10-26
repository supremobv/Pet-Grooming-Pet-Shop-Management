<?php

namespace App\Services;

use App\Support\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function checkout(array $v): int
    {
        return DB::transaction(function () use ($v) {
            $existing = DB::table('sales')->where('checkout_key', $v['checkout_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === auth()->id(), 403);

                return $existing->id;
            }
            $lines = [];
            $appointment = null;
            $customer = $v['customer_id'] ?? null;
            if ($customer && ! DB::table('customers')->where('active', true)->find($customer)) {
                $this->fail('customer_id', 'Choose an active customer.');
            }
            if (! empty($v['appointment_id'])) {
                $appointment = DB::table('appointments')->lockForUpdate()->find($v['appointment_id']);
                if (! $appointment || ! in_array($appointment->status, ['ready', 'completed']) || DB::table('sales')->where('appointment_id', $appointment->id)->exists()) {
                    $this->fail('appointment_id', 'Choose a ready or completed appointment that has not been billed.');
                }
                $owner = DB::table('pets')->find($appointment->pet_id)->customer_id;
                if ($customer && (int) $customer !== $owner) {
                    $this->fail('customer_id', 'The selected customer must be the pet owner.');
                }
                $customer = $owner;
                $service = DB::table('services')->find($appointment->service_id);
                $lines[] = ['product_id' => null, 'service_id' => $service->id, 'description' => $service->name, 'quantity' => 1, 'unit_price_cents' => $appointment->price_cents, 'unit_cost_cents' => 0, 'total_cents' => $appointment->price_cents];
            }
            $items = collect($v['items'] ?? [])->filter(fn ($i) => ! empty($i['product_id']) && ($i['quantity'] ?? 0) > 0)->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'))->sortKeys();
            foreach ($items as $productId => $quantity) {
                $p = DB::table('products')->lockForUpdate()->find($productId);
                if (! $p || ! $p->active || $p->stock < $quantity) {
                    $this->fail('items', 'An item is unavailable or has insufficient stock. Refresh the catalog and check quantities.');
                }
                $lines[] = ['product_id' => $p->id, 'service_id' => null, 'description' => $p->name, 'quantity' => $quantity, 'unit_price_cents' => $p->price_cents, 'unit_cost_cents' => $p->cost_cents, 'total_cents' => $p->price_cents * $quantity];
            }
            if (! $lines) {
                $this->fail('items', 'Add at least one product or a billable grooming appointment.');
            }
            $subtotal = array_sum(array_column($lines, 'total_cents'));
            $discount = Shop::cents($v['discount'] ?? 0);
            if ($subtotal > 2000000000) {
                $this->fail('items', 'This sale is too large. Split it into smaller purchases (maximum subtotal 20,000,000).');
            }
            if ($discount > $subtotal) {
                $this->fail('discount', 'Discount cannot exceed the subtotal.');
            }
            $s = Shop::settings();
            $tax = (int) round(($subtotal - $discount) * (float) $s['tax_rate'] / 100);
            $total = $subtotal - $discount + $tax;
            $tendered = Shop::cents($v['tendered']);
            if ($tendered < $total || ($v['payment_method'] !== 'cash' && $tendered !== $total)) {
                $this->fail('tendered', 'Cash must cover the total; card and e-wallet amounts must match it exactly.');
            }
            $id = DB::table('sales')->insertGetId(['checkout_key' => $v['checkout_key'], 'customer_id' => $customer, 'appointment_id' => $appointment?->id, 'user_id' => auth()->id(), 'subtotal_cents' => $subtotal, 'discount_cents' => $discount, 'tax_cents' => $tax, 'total_cents' => $total, 'tendered_cents' => $tendered, 'payment_method' => $v['payment_method'], 'payment_reference' => $v['payment_reference'] ?? null, 'business_snapshot' => json_encode($s), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                DB::table('sale_items')->insert([...$line, 'sale_id' => $id]);
                if ($line['product_id']) {
                    DB::table('products')->where('id', $line['product_id'])->decrement('stock', $line['quantity']);
                    DB::table('stock_movements')->insert(['product_id' => $line['product_id'], 'user_id' => auth()->id(), 'quantity' => -$line['quantity'], 'reason' => 'Sale', 'sale_id' => $id, 'created_at' => now()]);
                }
            }
            Shop::audit('paid', 'sales', $id, ['total_cents' => $total]);

            return $id;
        }, 3);
    }

    public function refund(int $id, string $reason): void
    {
        DB::transaction(function () use ($id, $reason) {
            $sale = DB::table('sales')->lockForUpdate()->find($id);
            abort_unless($sale, 404);
            if ($sale->status !== 'paid') {
                $this->fail('refund_reason', 'This sale has already been refunded.');
            }
            foreach (DB::table('sale_items')->where('sale_id', $id)->whereNotNull('product_id')->orderBy('product_id')->get() as $line) {
                DB::table('products')->where('id', $line->product_id)->lockForUpdate()->first();
                DB::table('products')->where('id', $line->product_id)->increment('stock', $line->quantity);
                DB::table('stock_movements')->insert(['product_id' => $line->product_id, 'user_id' => auth()->id(), 'quantity' => $line->quantity, 'reason' => 'Full refund: '.$reason, 'sale_id' => $id, 'created_at' => now()]);
            }
            DB::table('sales')->where('id', $id)->update(['status' => 'refunded', 'refund_reason' => $reason, 'refunded_at' => now(), 'refunded_by' => auth()->id(), 'updated_at' => now()]);
            Shop::audit('refunded', 'sales', $id, ['reason' => $reason]);
        }, 3);
    }

    private function fail(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
