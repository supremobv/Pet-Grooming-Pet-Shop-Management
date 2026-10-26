<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Access;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function settings()
    {
        return view('settings', ['settings' => Shop::settings()]);
    }

    public function saveSettings(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'tagline' => 'nullable|string|max:160', 'email' => 'nullable|email|max:160', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:255', 'currency' => 'required|in:PHP,USD,SGD,EUR,GBP,AUD,CAD,MYR', 'tax_rate' => 'required|numeric|min:0|max:100|decimal:0,2', 'open_time' => 'required|date_format:H:i', 'close_time' => 'required|date_format:H:i|after:open_time', 'open_days' => 'required|array|min:1', 'open_days.*' => 'required|in:1,2,3,4,5,6,7', 'receipt_footer' => 'nullable|string|max:500', 'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=2400,max_height=2400', 'remove_logo' => 'nullable|boolean']);
        $s = Shop::settings();
        unset($v['logo'],$v['remove_logo']);
        if ($v['currency'] !== $s['currency'] && DB::table('sales')->exists()) {
            throw ValidationException::withMessages(['currency' => 'Currency cannot be changed after a sale is recorded. Use a separate database for a business with another currency.']);
        }
        if ($r->boolean('remove_logo')) {
            $s['logo'] = null;
        }
        if ($r->hasFile('logo')) {
            $file = $r->file('logo');
            $name = bin2hex(random_bytes(20)).'.png';
            $path = storage_path('app/logos');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }$image = imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (! $image) {
                throw ValidationException::withMessages(['logo' => 'This image could not be read. Try another image.']);
            }imagepng($image, $path.'/'.$name);
            imagedestroy($image);
            $s['logo'] = $name;
        }
        DB::transaction(function () use ($s, $v) {
            DB::table('settings')->updateOrInsert(['id' => 1], ['data' => json_encode(array_merge($s, $v)), 'updated_at' => now(), 'created_at' => now()]);
            Shop::audit('updated', 'settings', 1);
        });

        return back()->with('success', 'Business settings saved. New receipts will use these details.');
    }

    public function logo()
    {
        $name = Shop::settings()['logo'];
        if (! $name || ! preg_match('/^[a-f0-9]{40}\.png$/', $name)) {
            return response()->file(public_path('assets/logo.svg'));
        }$path = storage_path('app/logos/'.$name);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function users()
    {
        return view('users', ['users' => User::orderBy('name')->get(), 'roles' => Access::ROLES]);
    }

    public function saveUser(Request $r, ?int $id = null)
    {
        $v = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:160', Rule::unique('users')->ignore($id)], 'role' => ['required', Rule::in(Access::ROLES)], 'active' => 'required|boolean', 'password' => [$id ? 'nullable' : 'required', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        DB::transaction(function () use ($v, $id) {
            $admins = User::where('role', 'admin')->where('active', true)->lockForUpdate()->get();
            $u = $id ? User::lockForUpdate()->findOrFail($id) : new User;
            if ($id === auth()->id() && (! $v['active'] || $v['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'You cannot disable or demote your own administrator account.']);
            }
            if ($u->role === 'admin' && $u->active && (! $v['active'] || $v['role'] !== 'admin') && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
            }
            if (empty($v['password'])) {
                unset($v['password']);
            }$u->fill($v)->save();
            if (isset($v['password'])) {
                DB::table('sessions')->where('user_id', $u->id)->delete();
            }Shop::audit('staff_updated', 'users', $u->id, ['role' => $u->role, 'active' => $u->active]);
        });

        return back()->with('success', 'Staff account saved.');
    }

    public function audit()
    {
        return view('audit', ['logs' => DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->select('a.*','u.name as user')->orderByDesc('a.id')->paginate(30)]);
    }
}
