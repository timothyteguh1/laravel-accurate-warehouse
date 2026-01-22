<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F3F4F6;
        }

        /* Sidebar */
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
        
        /* Dropdown User Profile Customization */
        .user-dropdown-toggle {
            cursor: pointer;
        }
    </style>
</head>

<body>

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
                        {{-- PERBAIKAN DI SINI: Ganti route ke 'logout' --}}
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
</body>

</html>