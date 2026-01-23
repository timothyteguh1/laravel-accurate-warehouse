<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Staff Baru</title>
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
        }
        .login-card {
            width: 100%;
            max-width: 450px; /* Sedikit lebih lebar untuk register */
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            padding: 40px;
            animation: fadeIn 0.5s ease-out;
        }
        .brand-icon {
            width: 60px;
            height: 60px;
            background: #ECFDF5; /* Hijau muda untuk Register */
            color: #10B981;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }
        .form-control:focus {
            border-color: #10B981; /* Fokus warna hijau */
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.1);
        }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #10B981;
            transform: scale(.85) translateY(-0.5rem) translateX(0.15rem);
        }
        .btn-success-custom {
            background-color: #10B981;
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            color: white;
            transition: all 0.3s;
        }
        .btn-success-custom:hover {
            background-color: #059669;
            color: white;
            transform: translateY(-1px);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h4 class="fw-bold text-dark">Registrasi Staff</h4>
            <p class="text-muted small">Buat akun untuk operasional gudang</p>
        </div>

        <form action="{{ route('register.post') }}" method="POST">
            @csrf
            
            <div class="form-floating mb-3">
                <input type="text" name="name" class="form-control bg-light border-0" id="nameInput" placeholder="Nama Lengkap" value="{{ old('name') }}" required>
                <label for="nameInput" class="text-muted"><i class="fa-solid fa-id-card me-2"></i>Nama Lengkap</label>
            </div>

            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control bg-light border-0" id="emailInput" placeholder="name@example.com" value="{{ old('email') }}" required>
                <label for="emailInput" class="text-muted"><i class="fa-solid fa-envelope me-2"></i>Email</label>
            </div>

            <div class="row g-2 mb-4">
                <div class="col-6">
                    <div class="form-floating">
                        <input type="password" name="password" class="form-control bg-light border-0" id="passInput" placeholder="Password" required>
                        <label for="passInput" class="text-muted"><i class="fa-solid fa-lock me-2"></i>Password</label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-floating">
                        <input type="password" name="password_confirmation" class="form-control bg-light border-0" id="confPass" placeholder="Konfirmasi" required>
                        <label for="confPass" class="text-muted">Konfirmasi</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success-custom w-100 mb-3">
                <i class="fa-solid fa-check-circle me-2"></i> Buat Akun
            </button>
        </form>
        
        <div class="text-center pt-3 border-top">
            <p class="small text-muted mb-0">
                Sudah punya akun? <a href="{{ route('login') }}" class="text-decoration-none fw-bold text-success">Login Disini</a>
            </p>
        </div>
    </div>

</body>
</html>