<?php

namespace App\Http\Controllers;

use App\Support\Catalog;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $r, string $module)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'archived' => 'nullable|in:1', 'page' => 'nullable|integer|min:1']);
        $def = Catalog::definition($module);
        $q = DB::table($module)->select($module.'.*');
        if ($module === 'pets') {
            $q->join('customers', 'customers.id', '=', 'pets.customer_id')->addSelect('customers.name as owner');
        }
        if ($search = $r->string('q')->toString()) {
            $q->where($module.'.name', 'like', '%'.mb_substr($search, 0, 100).'%');
        }
        if ($r->query('archived') !== '1') {
            $q->where($module.'.active', true);
        }

        return view('catalog.index', ['module' => $module, 'def' => $def, 'records' => $q->orderByDesc($module.'.id')->paginate(15)->withQueryString()]);
    }

    public function form(Request $r)
    {
        $module = $r->route('module');
        $id = $r->route('id') ? (int) $r->route('id') : null;
        $def = Catalog::definition($module);
        $record = $id ? DB::table($module)->find($id) : null;
        if ($id) {
            abort_unless($record, 404);
        }
        if ($record && isset($record->price_cents)) {
            $record->price = $record->price_cents / 100;
        }
        if ($record && isset($record->cost_cents)) {
            $record->cost = $record->cost_cents / 100;
        }

        return view('catalog.form', compact('module', 'def', 'record') + ['options' => Catalog::options()]);
    }

    public function save(Request $r)
    {
        $module = $r->route('module');
        $id = $r->route('id') ? (int) $r->route('id') : null;
        $def = Catalog::definition($module);
        if ($id) {
            abort_unless(DB::table($module)->find($id), 404);
        }
        $rules = array_map(fn ($f) => $f[2], $def['fields']);
        if ($module === 'products') {
            $rules['sku'] = ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($id)];
        }
        $v = $r->validate($rules);
        foreach (['price', 'cost'] as $field) {
            if (isset($v[$field])) {
                $v[$field.'_cents'] = Shop::cents($v[$field]);
                unset($v[$field]);
            }
        }
        DB::transaction(function () use ($module, $id, $v) {
            $data = [...$v, 'updated_at' => now()];
            if ($id) {
                DB::table($module)->where('id', $id)->update($data);
            } else {
                $id = DB::table($module)->insertGetId([...$data, 'created_at' => now()]);
            } Shop::audit('saved', $module, $id);
        });

        return redirect('/'.$module)->with('success', ucfirst($def['singular']).' saved.');
    }

    public function archive(Request $r)
    {
        $module = $r->route('module');
        $id = (int) $r->route('id');
        Catalog::definition($module);
        DB::transaction(function () use ($module, $id) {
            $item = DB::table($module)->lockForUpdate()->find($id);
            abort_unless($item, 404);
            DB::table($module)->where('id', $id)->update(['active' => ! $item->active, 'updated_at' => now()]);
            Shop::audit($item->active ? 'archived' : 'restored', $module, $id);
        });

        return back()->with('success','Record updated. Historical records are preserved.');
    }
}
