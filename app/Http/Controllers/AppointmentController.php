<?php

namespace App\Http\Controllers;

use App\Services\AppointmentService;
use App\Support\Access;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public static function query()
    {
        return DB::table('appointments as a')->join('pets as p', 'p.id', '=', 'a.pet_id')->join('customers as c', 'c.id', '=', 'p.customer_id')->join('services as s', 's.id', '=', 'a.service_id')->join('users as u', 'u.id', '=', 'a.groomer_id')->select('a.*', 'p.name as pet', 'p.species', 'p.breed', 'p.medical_notes', 'p.behavior_notes', 'p.vaccination_due', 'c.name as customer', 'c.phone', 's.name as service', 'u.name as groomer');
    }

    public function index(Request $r)
    {
        $q = self::query();
        if (! Access::allows('appointments')) {
            $q->where('a.groomer_id', auth()->id());
        }
        if ($r->filled('date')) {
            $r->validate(['date' => 'date_format:Y-m-d']);
            $q->whereDate('a.starts_at', $r->date);
        } else {
            $q->whereDate('a.starts_at', '>=', today());
        }
        if ($r->filled('status')) {
            $r->validate(['status' => 'in:booked,checked_in,in_progress,ready,completed,cancelled,no_show']);
            $q->where('a.status', $r->status);
        }

        return view('appointments.index', ['appointments' => $q->orderBy('a.starts_at')->paginate(20)->withQueryString()]);
    }

    public function form(?int $id = null)
    {
        $record = $id ? DB::table('appointments')->find($id) : null;
        if ($id) {
            abort_unless($record, 404);
        }

return view('appointments.form', compact('record') + ['pets' => DB::table('pets')->join('customers', 'customers.id', '=', 'pets.customer_id')->where('pets.active', true)->where('customers.active', true)->select('pets.id', 'pets.name', 'customers.name as owner')->orderBy('pets.name')->get()->map(fn ($p) => (object) ['id' => $p->id, 'name' => $p->name.' — '.$p->owner]), 'services' => DB::table('services')->where('active', true)->get(), 'groomers' => DB::table('users')->where('active', true)->whereIn('role', ['groomer', 'admin', 'manager'])->get()]);
    }

    public function save(Request $r, AppointmentService $service, ?int $id = null)
    {
        $v = $r->validate(['pet_id' => 'required|integer', 'service_id' => 'required|integer', 'groomer_id' => 'required|integer', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'notes' => 'nullable|string|max:3000']);
        $service->book($v, $id);

        return redirect('/appointments')->with('success', 'Appointment booked.');
    }

    public function status(Request $r, int $id)
    {
        $v = $r->validate(['status' => 'required|in:checked_in,in_progress,ready,completed,cancelled,no_show', 'grooming_notes' => 'nullable|string|max:4000']);
        DB::transaction(function () use ($id, $v) {
            $a = DB::table('appointments')->lockForUpdate()->find($id);
            abort_unless($a, 404);
            abort_unless(Access::allows('appointments') || (Access::allows('grooming') && $a->groomer_id === auth()->id()), 403);
            $allowed = ['booked' => ['checked_in', 'cancelled', 'no_show'], 'checked_in' => ['in_progress', 'cancelled'], 'in_progress' => ['ready'], 'ready' => ['completed']];
            if (! in_array($v['status'], $allowed[$a->status] ?? []) || (! Access::allows('appointments') && ! in_array($v['status'], ['in_progress', 'ready']))) {
                throw ValidationException::withMessages(['status' => 'That status change is not available for this appointment.']);
            }
            DB::table('appointments')->where('id', $id)->update([...$v, 'updated_at' => now()]);
            Shop::audit('status_changed','appointments',$id,['from' => $a->status, 'to' => $v['status']]);
        });

        return back()->with('success','Appointment updated.');
    }
}
