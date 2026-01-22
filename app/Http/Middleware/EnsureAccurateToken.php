<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccurateToken
{
   public function handle(Request $request, Closure $next): Response
    {
        if (!Storage::exists('accurate_token.json') || !Storage::exists('accurate_session.json')) {
            // Arahkan ke Halaman Login (Tampilan)
            return redirect()->route('login.page')->with('error', 'Silakan login dulu.');
        }

        return $next($request);
    }
    }