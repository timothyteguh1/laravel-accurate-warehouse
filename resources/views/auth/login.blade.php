<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staff - Gudang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f3f4f6; height: 100vh; display: flex; align-items: center; justify-content: center; }</style>
</head>
<body>
    <div class="card shadow-sm border-0 p-4" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <h4 class="fw-bold">Login Staff</h4>
            <p class="text-muted small">Masuk untuk mengakses sistem gudang</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-bold">Email</label>
                <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">Masuk</button>
        </form>
        
        <div class="text-center mt-3 small">
            Belum punya akun? <a href="{{ route('register') }}">Daftar disini</a>
        </div>
    </div>
</body>
</html>