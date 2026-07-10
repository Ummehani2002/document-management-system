<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign in — Tanseeq DMS</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        :root {
            --navy: #212d3e;
            --navy-deep: #18222f;
            --navy-soft: #2d3a52;
            --gold: #c4a47c;
            --gold-dark: #a88962;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background: #f1f5f9;
            display: flex;
            min-height: 100vh;
        }

        .login-shell {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            width: 100%;
            min-height: 100vh;
        }

        /* ---------- Brand panel ---------- */
        .brand-panel {
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 20% 20%, var(--navy-soft), var(--navy) 45%, var(--navy-deep) 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px 72px;
        }

        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(196, 164, 124, 0.14);
            filter: blur(2px);
            animation: float 12s ease-in-out infinite;
        }

        .brand-panel::before {
            width: 320px; height: 320px;
            top: -90px; right: -80px;
        }

        .brand-panel::after {
            width: 240px; height: 240px;
            bottom: -70px; left: -60px;
            animation-delay: -4s;
            background: rgba(196, 164, 124, 0.10);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); }
            50%      { transform: translateY(-22px) translateX(12px); }
        }

        .brand-content { position: relative; z-index: 1; max-width: 420px; }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--gold);
            border: 1px solid rgba(196, 164, 124, 0.4);
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 30px;
        }

        .brand-title {
            font-size: 2.4rem;
            line-height: 1.18;
            font-weight: 600;
            margin: 0 0 18px;
        }

        .brand-title .accent { color: var(--gold); }

        .brand-tagline {
            font-size: 1.02rem;
            line-height: 1.6;
            color: #cbd5e1;
            margin: 0 0 36px;
        }

        .brand-points { list-style: none; margin: 0; padding: 0; }

        .brand-points li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            color: #e2e8f0;
            font-size: 0.95rem;
        }

        .brand-points .dot {
            flex: none;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(196, 164, 124, 0.18);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            margin-top: 1px;
        }

        /* ---------- Form panel ---------- */
        .form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 32px;
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 2px solid var(--gold);
            border-radius: 16px;
            padding: 44px 40px 40px;
            box-shadow: 0 24px 60px -28px rgba(15, 23, 42, 0.45);
            animation: rise 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-logo {
            display: block;
            margin: 0 auto 26px;
            width: 180px;
            height: auto;
            object-fit: contain;
        }

        .auth-card h1 {
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0 0 28px;
            color: var(--navy);
        }

        .error-msg {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        .error-msg div + div { margin-top: 4px; }

        .microsoft-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 15px;
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.98rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .microsoft-btn:hover {
            background: var(--navy-soft);
            box-shadow: 0 12px 24px -12px rgba(33, 45, 62, 0.7);
            transform: translateY(-2px);
        }

        .microsoft-btn:active { transform: translateY(0); }

        .microsoft-btn .ms-logo {
            display: grid;
            grid-template-columns: 9px 9px;
            grid-template-rows: 9px 9px;
            gap: 2px;
        }
        .microsoft-btn .ms-logo span { display: block; width: 9px; height: 9px; }
        .ms-logo .s1 { background: #f25022; }
        .ms-logo .s2 { background: #7fba00; }
        .ms-logo .s3 { background: #00a4ef; }
        .ms-logo .s4 { background: #ffb900; }

        .form-footer {
            margin-top: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 920px) {
            .login-shell { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .form-panel { padding: 32px 20px; min-height: 100vh; }
        }
    </style>
</head>
<body>

<div class="login-shell">
    <section class="brand-panel">
        <div class="brand-content">
            <span class="brand-badge">Tanseeq Investment</span>
            <h1 class="brand-title">Document Management <span class="accent">System</span></h1>
            <p class="brand-tagline">
                One secure home for every project document — organized by entity, folder and subfolder,
                searchable in seconds, and shared straight from your inbox.
            </p>
            <ul class="brand-points">
                <li><span class="dot">&#10003;</span> Smart search across all your files &amp; scanned text</li>
                <li><span class="dot">&#10003;</span> Entity, folder &amp; subfolder access control</li>
                <li><span class="dot">&#10003;</span> Full activity history and version tracking</li>
            </ul>
        </div>
    </section>

    <section class="form-panel">
        <div class="auth-card">
            <img
                class="login-logo"
                src="{{ asset('images/tanseeq.png') }}?v=5"
                alt="Tanseeq Investment"
            >

            <h1>Welcome back</h1>

            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('login.microsoft') }}" class="microsoft-btn">
                <span class="ms-logo">
                    <span class="s1"></span><span class="s2"></span>
                    <span class="s3"></span><span class="s4"></span>
                </span>
                <span>Sign in with Microsoft</span>
            </a>

            <div class="form-footer">
                &copy; {{ date('Y') }} Tanseeq Investment · Document Management System
            </div>
        </div>
    </section>
</div>

</body>
</html>
