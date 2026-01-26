<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Pro</title>
    
    {{-- CSS Lokal (Pastikan file ini ada sesuai rencana optimasi sebelumnya) --}}
    {{-- Jika belum ada, ganti asset(...) dengan link CDN Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F3F4F6;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            background: #FFFFFF;
            border-right: 1px solid #E5E7EB;
            padding-top: 24px;
            z-index: 1000;
        }

        .brand {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            padding: 0 24px 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: #6B7280;
            font-weight: 500;
            transition: 0.2s;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #EFF6FF;
            color: #2563EB;
            border-right: 3px solid #2563EB;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main {
            margin-left: 260px;
            padding: 32px;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
        }

        /* Cards */
        .card-custom {
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .user-dropdown-toggle {
            cursor: pointer;
        }

        /* =========================================
           LOADING SCENE CSS (Global Loader)
           ========================================= */
        #global-loader {
            position: fixed;
            z-index: 99999;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9); /* Background putih semi-transparan */
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }

        /* Animasi Spinner (Modern Circle) */
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e5e7eb; /* Warna abu muda */
            border-top: 5px solid #2563EB; /* Warna Biru Utama */
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Class untuk menyembunyikan loader */
        .loader-hidden {
            opacity: 0;
            visibility: hidden;
        }
    </style>
</head>

<body>

    <div id="global-loader">
        <div class="text-center">
            <div class="spinner mb-3 mx-auto"></div>
            <h6 class="text-primary fw-bold animate__animated animate__pulse animate__infinite">Memuat...</h6>
        </div>
    </div>

    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-cube text-primary"></i> Warehouse Pro
        </div>
        <nav class="nav flex-column">
            <a href="{{ url('/dashboard') }}" class="nav-link {{ Request::is('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>

            <a href="{{ url('/scan-so') }}"
                class="nav-link {{ Request::is('scan-so*') || Request::is('scan-process*') ? 'active' : '' }}">
                <i class="fa-solid fa-barcode"></i> Scan SO
            </a>

            <a href="{{ url('/history-do') }}" class="nav-link {{ Request::is('history-do*') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-check"></i> SO Selesai (Closed)
            </a>

            {{-- Tambahan menu Inventory --}}
            <a href="{{ url('/inventory') }}" class="nav-link {{ Request::is('inventory*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked"></i> Inventory
            </a>
        </nav>
    </div>

    <div class="main">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="header-title">@yield('header')</h1>
            
            <div class="dropdown">
                <div class="d-flex align-items-center gap-3 user-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="text-end d-none d-md-block">
                        <div class="fw-bold text-dark" style="font-size: 14px;">{{ Auth::user()->name ?? 'Staff Gudang' }}</div>
                        <div class="text-muted" style="font-size: 12px;">{{ Auth::user()->email ?? 'staff@warehouse.com' }}</div>
                    </div>
                    <div class="bg-dark text-white rounded-circle d-flex justify-content-center align-items-center"
                        style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>
                
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow mt-2">
                    <li><h6 class="dropdown-header">Akun</h6></li>
                    <li><a class="dropdown-item" href="#"><i class="fa-solid fa-gear me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
            
        </div>

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // 1. Saat Halaman Selesai Loading -> Sembunyikan Loader
        window.addEventListener("load", function () {
            const loader = document.getElementById("global-loader");
            loader.classList.add("loader-hidden");
        });

        // 2. Deteksi Navigasi Browser (Back/Forward Button)
        // Agar loader hilang jika user tekan tombol Back di browser
        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                const loader = document.getElementById("global-loader");
                loader.classList.add("loader-hidden");
            }
        });

        // 3. Saat Klik Link (Pindah Halaman) -> Munculkan Loader
        document.addEventListener("DOMContentLoaded", function() {
            const links = document.querySelectorAll("a");
            const forms = document.querySelectorAll("form");

            // Handle Link Click
            links.forEach(function(link) {
                link.addEventListener("click", function(e) {
                    const href = link.getAttribute("href");
                    const target = link.getAttribute("target");

                    // Validasi: Munculkan loader HANYA JIKA link valid & bukan tab baru
                    if (href && href !== "#" && href !== "javascript:void(0)" && target !== "_blank") {
                        // Jangan munculkan loader jika klik link dropdown (toggle)
                        if (!link.classList.contains("dropdown-toggle") && !link.hasAttribute("data-bs-toggle")) {
                            document.getElementById("global-loader").classList.remove("loader-hidden");
                        }
                    }
                });
            });

            // Handle Form Submit (Logout, dll)
            forms.forEach(function(form) {
                form.addEventListener("submit", function() {
                    document.getElementById("global-loader").classList.remove("loader-hidden");
                });
            });
        });
    </script>
</body>

</html>