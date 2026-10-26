<?php

namespace App\Http\Controllers;

use App\Support\Access;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $q = AppointmentController::query()->whereDate('a.starts_at', today())->whereNotIn('a.status', ['cancelled', 'no_show']);
        if (auth()->user()->role === 'groomer') {
            $q->where('a.groomer_id', auth()->id());
        }
        if (! Access::allows('appointments') && ! Access::allows('grooming')) {
            $q->whereRaw('1=0');
        }

        return view('dashboard', ['appointments' => (clone $q)->orderBy('a.starts_at')->limit(8)->get(), 'appointmentCount' => (clone $q)->count(), 'customers' => DB::table('customers')->where('active', true)->count(), 'pets' => DB::table('pets')->where('active', true)->count(), 'revenue' => DB::table('sales')->where('status', 'paid')->whereDate('created_at', today())->sum('total_cents'), 'lowStock' => DB::table('products')->where('active', true)->whereColumn('stock', '<=', 'reorder_level')->orderBy('stock')->limit(6)->get(), 'sales' => DB::table('sales')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')->select('sales.*', 'customers.name as customer')->orderByDesc('sales.id')->limit(5)->get(), 'week' => collect(range(6, 0))->map(function ($i) {
            $d = today()->subDays($i);

            return ['label' => $d->format('D'), 'total' => (int) DB::table('sales')->where('status', 'paid')->whereDate('created_at', $d)->sum('total_cents')];
        })]);
    }
}
