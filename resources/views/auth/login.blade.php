<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staff Gudang</title>
    
    {{-- Gunakan Asset Lokal jika ada, atau CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden; /* Mencegah scroll saat loading */
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            padding: 40px;
            animation: fadeIn 0.5s ease-out;
            position: relative;
            z-index: 1; /* Di bawah loader */
        }
        .brand-icon {
            width: 60px;
            height: 60px;
            background: #EFF6FF;
            color: #2563EB;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #2563EB;
            transform: scale(.85) translateY(-0.5rem) translateX(0.15rem);
        }
        .form-control:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1);
        }
        .btn-primary {
            background-color: #2563EB;
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #1D4ED8;
            transform: translateY(-1px);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* =========================================
           LOADING SCENE CSS
           ========================================= */
        #global-loader {
            position: fixed;
            z-index: 99999;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.95); /* Background putih solid transparan */
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }

        /* Animasi Spinner (Modern Circle) */
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e5e7eb;
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
            <h6 class="text-primary fw-bold animate__animated animate__pulse animate__infinite">Memproses...</h6>
        </div>
    </div>

    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand-icon">
                <i class="fa-solid fa-warehouse"></i>
            </div>
            <h4 class="fw-bold text-dark">Warehouse System</h4>
            <p class="text-muted small">Masuk untuk mengakses Dashboard</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4">
                <i class="fa-solid fa-circle-exclamation me-2"></i> {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST" id="loginForm">
            @csrf
            
            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control bg-light border-0" id="emailInput" placeholder="name@example.com" value="{{ old('email') }}" required>
                <label for="emailInput" class="text-muted"><i class="fa-solid fa-envelope me-2"></i>Email Staff</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" name="password" class="form-control bg-light border-0" id="passInput" placeholder="Password" required>
                <label for="passInput" class="text-muted"><i class="fa-solid fa-lock me-2"></i>Password</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3" id="btnLogin">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk Sistem
            </button>
        </form>
        
        <div class="text-center pt-3 border-top">
            <p class="small text-muted mb-0">
                Belum punya akses? <a href="{{ route('register') }}" class="text-decoration-none fw-bold text-primary link-loader">Daftar Akun Baru</a>
            </p>
        </div>
    </div>

    <script>
        // 1. Hilangkan Loader saat halaman selesai dimuat
        window.addEventListener("load", function () {
            const loader = document.getElementById("global-loader");
            loader.classList.add("loader-hidden");
        });

        // 2. Munculkan Loader saat Form Disubmit (Login)
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loader = document.getElementById("global-loader");
            const btn = document.getElementById("btnLogin");
            
            loader.classList.remove("loader-hidden");
            
            // Ubah tombol jadi loading state juga (visual feedback ganda)
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Memeriksa...';
        });

        // 3. Munculkan Loader saat klik link "Daftar Akun Baru"
        document.querySelectorAll('.link-loader').forEach(function(link) {
            link.addEventListener('click', function() {
                document.getElementById("global-loader").classList.remove("loader-hidden");
            });
        });
    </script>

</body>
</html>