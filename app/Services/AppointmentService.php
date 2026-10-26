<?php

namespace App\Services;

use App\Support\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function book(array $v, ?int $id = null): int
    {
        return DB::transaction(function () use ($v, $id) {
            // Serialize all bookings for the groomer, including concurrent first bookings.
            $groomer = DB::table('users')->lockForUpdate()->find($v['groomer_id']);
            if (! $groomer || ! $groomer->active || ! in_array($groomer->role, ['groomer', 'admin', 'manager'])) {
                $this->fail('groomer_id', 'Choose an active groomer.');
            }
            $pet = DB::table('pets')->join('customers', 'customers.id', '=', 'pets.customer_id')->where('pets.id', $v['pet_id'])->where('pets.active', true)->where('customers.active', true)->lockForUpdate()->select('pets.*')->first();
            if (! $pet) {
                $this->fail('pet_id', 'Choose an active pet with an active owner.');
            }
            $service = DB::table('services')->where('active', true)->find($v['service_id']);
            if (! $service) {
                $this->fail('service_id', 'Choose an active service.');
            }
            $start = Carbon::parse($v['starts_at']);
            $end = $start->copy()->addMinutes($service->duration);
            $s = Shop::settings();
            if ($start->isPast()) {
                $this->fail('starts_at', 'Choose a future appointment time.');
            }
            if (! in_array((string) $start->dayOfWeekIso, $s['open_days']) || $start->format('H:i') < $s['open_time'] || ! $start->isSameDay($end) || $end->format('H:i') > $s['close_time']) {
                $this->fail('starts_at', 'This service must fit within the configured opening days and hours.');
            }
            if ($id) {
                $existing = DB::table('appointments')->lockForUpdate()->find($id);
                abort_unless($existing, 404);
                if ($existing->status !== 'booked') {
                    $this->fail('starts_at', 'Only booked appointments can be rescheduled.');
                }
            }
            $overlap = DB::table('appointments')->whereNotIn('status', ['cancelled', 'no_show'])->when($id, fn ($q) => $q->where('id', '!=', $id))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->where(fn ($q) => $q->where('groomer_id', $groomer->id)->orWhere('pet_id', $pet->id))->exists();
            if ($overlap) {
                $this->fail('starts_at', 'This groomer or pet already has an appointment during that time. Choose another slot.');
            }
            $data = ['pet_id' => $pet->id, 'service_id' => $service->id, 'groomer_id' => $groomer->id, 'starts_at' => $start, 'ends_at' => $end, 'price_cents' => $service->price_cents, 'notes' => $v['notes'] ?? null, 'updated_at' => now()];
            if ($id) {
                DB::table('appointments')->where('id', $id)->update($data);
            } else {
                $id = DB::table('appointments')->insertGetId([...$data, 'created_at' => now()]);
            }
            Shop::audit('booked', 'appointments', $id);

            return $id;
        }, 3);
    }

    private function fail(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
