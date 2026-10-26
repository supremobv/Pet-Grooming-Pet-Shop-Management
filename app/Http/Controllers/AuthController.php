<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = 'login:'.hash('sha256', strtolower($v['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }
        if (! Auth::attempt([...$v, 'active' => true])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'The email or password is incorrect, or this account is inactive.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();
        Shop::audit('login', 'users', auth()->id());

        return redirect()->intended('/');
    }

    public function logout(Request $r)
    {
        Shop::audit('logout', 'users', auth()->id());
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function password(Request $r)
    {
        $v = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $r->user()->update(['password' => $v['password'], 'remember_token' => null]);
        DB::table('sessions')->where('user_id', auth()->id())->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->regenerate();
        Shop::audit('password_changed', 'users', auth()->id());

        return back()->with('success','Your password has been updated. Other sessions have been signed out.');
    }
}
