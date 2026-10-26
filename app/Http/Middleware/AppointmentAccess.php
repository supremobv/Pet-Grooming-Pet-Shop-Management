<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;

class AppointmentAccess
{
    public function handle($request, Closure $next)
    {
        abort_unless(Access::allows('appointments') || Access::allows('grooming'), 403);

        return $next($request);
    }
}
