<?php

namespace App\Http\Controllers;

use App\Support\Access;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function customer(int $id)
    {
        $customer = DB::table('customers')->find($id);
        abort_unless($customer, 404);

        return view('profile', ['title' => $customer->name, 'customer' => $customer, 'pet' => null, 'pets' => DB::table('pets')->where('customer_id', $id)->get(), 'appointments' => AppointmentController::query()->where('c.id', $id)->orderByDesc('a.starts_at')->paginate(10)->withQueryString(), 'sales' => Access::allows('sales') ? DB::table('sales')->where('customer_id', $id)->orderByDesc('id')->limit(10)->get() : collect()]);
    }

    public function pet(int $id)
    {
        $pet = DB::table('pets')->find($id);
        abort_unless($pet, 404);
        $customer = DB::table('customers')->find($pet->customer_id);

        return view('profile', ['title' => $pet->name, 'customer' => $customer, 'pet' => $pet, 'pets' => collect(), 'appointments' => AppointmentController::query()->where('p.id', $id)->orderByDesc('a.starts_at')->paginate(10)->withQueryString(), 'sales' => collect()]);
    }
}
