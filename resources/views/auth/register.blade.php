<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register — Document Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .company-name {
            position: absolute;
            top: 40px;
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            font-size: 22px;
            font-weight: bold;
        }

        .auth-box {
            background: white;
            padding: 40px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .auth-box h2 {
            text-align: center;
            margin: 0 0 24px 0;
            font-size: 1.5rem;
        }

        .auth-box input {
            width: 100%;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .auth-box input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .auth-box button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 8px;
        }

        .auth-box button[type="submit"]:hover {
            background: #1e40af;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: #2563eb;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .error-msg {
            color: #b91c1c;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<div class="company-name">
    Document Management System
</div>

<div class="auth-box">
    <h2>Register</h2>

    @if ($errors->any())
        <div class="error-msg">
            @foreach ($errors->all() as $err)
                {{ $err }}
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <input type="text" name="username" value="{{ old('username') }}" placeholder="Username" required autofocus autocomplete="username">
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name" required autocomplete="name">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required autocomplete="email">
        <input type="password" name="password" placeholder="Password" required autocomplete="new-password">
        <input type="password" name="password_confirmation" placeholder="Confirm password" required autocomplete="new-password">

        <button type="submit">Create account</button>
    </form>

    <p class="auth-footer">
        Already have an account? <a href="{{ route('login') }}">Login here</a>
    </p>
</div>

</body>
</html>
