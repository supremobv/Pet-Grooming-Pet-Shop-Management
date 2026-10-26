<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $r)
    {
        $r->merge(['from' => $r->input('from') ?: today()->startOfMonth()->toDateString(), 'to' => $r->input('to') ?: today()->toDateString()]);
        $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $from = $r->input('from');
        $to = $r->input('to');
        $sales = DB::table('sales')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $paid = (clone $sales)->where('status', 'paid');
        if ($r->query('export') === 'csv') {
            Shop::audit('exported', 'sales', null, ['from' => $from, 'to' => $to]);

            return response()->streamDownload(function () use ($sales) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Receipt', 'Date', 'Status', 'Subtotal', 'Discount', 'Tax', 'Total', 'Payment', 'Currency']);
                foreach ($sales->orderBy('id')->cursor() as $s) {
                    fputcsv($out, [$s->id, $s->created_at, $s->status, $s->subtotal_cents / 100, $s->discount_cents / 100, $s->tax_cents / 100, $s->total_cents / 100, $s->payment_method, json_decode($s->business_snapshot, true)['currency']]);
                }
                fclose($out);
            }, 'sales-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv']);
        }
        $totals = (clone $paid)->selectRaw('COUNT(*) as count, COALESCE(SUM(subtotal_cents),0) as subtotal, COALESCE(SUM(discount_cents),0) as discount, COALESCE(SUM(tax_cents),0) as tax, COALESCE(SUM(total_cents),0) as total')->first();
        $currencies = (clone $paid)->get(['business_snapshot'])->map(fn ($s) => json_decode($s->business_snapshot, true)['currency'])->unique();
        $top = DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id')->where('s.status', 'paid')->whereBetween('s.created_at', [$from.' 00:00:00', $to.' 23:59:59'])->select('i.description')->selectRaw('SUM(i.quantity) as units, SUM(i.total_cents) as revenue')->groupBy('i.description')->orderByDesc('revenue')->limit(10)->get();

        return view('reports', compact('from', 'to', 'totals', 'top', 'currencies') + ['refunds' => (clone $sales)->where('status', 'refunded')->sum('total_cents'), 'payments' => (clone $paid)->select('payment_method')->selectRaw('COUNT(*) as count, SUM(total_cents) as total')->groupBy('payment_method')->get()]);
    }
}
