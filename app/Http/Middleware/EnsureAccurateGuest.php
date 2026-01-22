<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccurateGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        // Kalau file token ada, jangan kasih masuk halaman login lagi
        if (Storage::exists('accurate_token.json') && Storage::exists('accurate_session.json')) {
            return redirect('/dashboard');
        }

        return $next($request);
    }
}