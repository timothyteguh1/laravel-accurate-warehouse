<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // --- HALAMAN LOGIN ---
    public function showLogin()
    {
        return view('auth.login');
    }

    // --- PROSES LOGIN ---
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            // Login sukses -> Masuk Dashboard
            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    // --- HALAMAN REGISTER (DAFTAR) ---
    public function showRegister()
    {
        return view('auth.register');
    }

    // --- PROSES REGISTER ---
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed', // butuh input password_confirmation
        ]);

        // Buat User Baru di Database
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Otomatis login setelah daftar
        Auth::login($user);

        return redirect('dashboard')->with('success', 'Akun berhasil dibuat!');
    }

    // --- LOGOUT STAFF ---
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Berhasil Logout');
    }
}