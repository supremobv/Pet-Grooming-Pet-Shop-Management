<?php

namespace App\Http\Controllers;

use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']);
        $q = DB::table('sales')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')->select('sales.*', 'customers.name as customer');
        if ($r->filled('q')) {
            $q->where('sales.id', (int) $r->q);
        }

        return view('sales.index', ['sales' => $q->orderByDesc('sales.id')->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('sales.create', ['products' => DB::table('products')->where('active', true)->where('stock', '>', 0)->orderBy('name')->get(), 'customers' => DB::table('customers')->where('active', true)->orderBy('name')->get(), 'appointments' => AppointmentController::query()->whereIn('a.status', ['ready', 'completed'])->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('sales')->whereColumn('sales.appointment_id', 'a.id'))->get(), 'checkoutKey' => (string) Str::uuid()]);
    }

    public function store(Request $r, CheckoutService $service)
    {
        // Keep selected lines only, so a catalog with more than 100 products can still check out.
        if (is_array($r->input('items'))) {
            $r->merge(['items' => array_filter($r->input('items'), fn ($item) => ! is_array($item) || ! isset($item['quantity']) || ! in_array($item['quantity'], [0, '0'], true))]);
        }
        $v = $r->validate(['checkout_key' => 'required|uuid', 'customer_id' => 'nullable|integer', 'appointment_id' => 'nullable|integer', 'items' => 'nullable|array|max:100', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:10000', 'discount' => 'required|numeric|min:0|max:1000000', 'tendered' => 'required|numeric|min:0|max:40000000', 'payment_method' => 'required|in:cash,card,e_wallet', 'payment_reference' => 'nullable|required_unless:payment_method,cash|string|max:120']);
        $id = $service->checkout($v);

        return redirect('/sales/'.$id)->with('success', 'Payment recorded. Receipt is ready.');
    }

    public function show(int $id)
    {
        $sale = DB::table('sales')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')->join('users', 'users.id', '=', 'sales.user_id')->select('sales.*', 'customers.name as customer', 'users.name as cashier')->where('sales.id', $id)->first();
        abort_unless($sale, 404);

        return view('sales.show', compact('sale') + ['items' => DB::table('sale_items')->where('sale_id', $id)->get(), 'business' => json_decode($sale->business_snapshot, true)]);
    }

    public function logo(int $id)
    {
        $sale = DB::table('sales')->find($id);
        abort_unless($sale, 404);
        $name = json_decode($sale->business_snapshot, true)['logo'] ?? null;
        if (! $name || ! preg_match('/^[a-f0-9]{40}\.png$/', $name)) {
            return response()->file(public_path('assets/logo.svg'));
        } $path = storage_path('app/logos/'.$name);

        return response()->file(is_file($path) ? $path : public_path('assets/logo.svg'));
    }

    public function refund(Request $r, int $id, CheckoutService $service)
    {
        $v = $r->validate(['refund_reason' => 'required|string|min:5|max:180']);
        $service->refund($id,$v['refund_reason']);

        return back()->with('success','Full refund recorded and products returned to stock. Return the payment through the original payment channel.');
    }
}
