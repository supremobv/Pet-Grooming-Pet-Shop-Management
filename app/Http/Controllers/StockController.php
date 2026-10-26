<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index()
    {
        return view('stock', ['products' => DB::table('products')->where('active', true)->orderBy('name')->get(), 'movements' => DB::table('stock_movements as m')->join('products as p', 'p.id', '=', 'm.product_id')->leftJoin('users as u', 'u.id', '=', 'm.user_id')->select('m.*', 'p.name as product', 'u.name as user')->orderByDesc('m.id')->paginate(20)]);
    }

    public function store(Request $r)
    {
        $v = $r->validate(['product_id' => 'required|integer|exists:products,id', 'quantity' => 'required|integer|not_in:0|min:-1000000|max:1000000', 'reason' => 'required|string|min:5|max:180']);
        DB::transaction(function () use ($v) {
            $p = DB::table('products')->lockForUpdate()->find($v['product_id']);
            if (! $p->active || $p->stock + $v['quantity'] < 0 || $p->stock + $v['quantity'] > 10000000) {
                throw ValidationException::withMessages(['quantity' => 'The adjustment must leave an active product with stock between 0 and 10,000,000.']);
            } DB::table('products')->where('id', $p->id)->update(['stock' => $p->stock + $v['quantity'], 'updated_at' => now()]);
            DB::table('stock_movements')->insert([...$v, 'user_id' => auth()->id(), 'created_at' => now()]);
            Shop::audit('stock_adjusted', 'products', $p->id, $v);
        }, 3);

        return back()->with('success','Stock adjustment recorded.');
    }
}
