{{--
    Plantilla de las páginas de error (403, 404, 419, 429, 500, 503…): sustituye a
    errors::minimal de Laravel con el sistema visual de VyaMap. Estilos en línea
    para que se vea bien aunque fallen los assets compilados.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#070B14">
    <meta name="color-scheme" content="dark">
    <title>@yield('title') · VyaMap</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background:
                radial-gradient(50rem 32rem at 0% -10%, rgba(34, 211, 238, 0.09), transparent 60%),
                radial-gradient(44rem 30rem at 100% 0%, rgba(124, 92, 255, 0.10), transparent 60%),
                #070B14;
            color: #EAF0FA;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        main {
            width: 100%;
            max-width: 28rem;
            border: 1px solid rgba(148, 170, 214, 0.14);
            border-radius: 1.75rem;
            background: #0E1525;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 18px 48px -20px rgba(0, 0, 0, 0.85);
        }
        .code {
            display: inline-block;
            margin: 0;
            font-size: 3.5rem;
            font-weight: 700;
            letter-spacing: -0.04em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            color: #5EE7F7;
        }
        .route {
            height: 2px;
            width: 6rem;
            margin: 1.25rem auto;
            border-radius: 9999px;
            background: linear-gradient(90deg, #22D3EE, #7C9BFF 55%, #A78BFA);
        }
        h1 { margin: 0; font-size: 1.25rem; font-weight: 620; }
        p { margin: 0.5rem 0 0; color: #A6B3CA; font-size: 0.9375rem; line-height: 1.5; }
        a {
            display: inline-flex;
            align-items: center;
            min-height: 2.75rem;
            margin-top: 1.5rem;
            padding: 0 1.25rem;
            border-radius: 0.625rem;
            background: #22D3EE;
            color: #041019;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
        }
        a:hover { background: #5EE7F7; }
        a:focus-visible { outline: 2px solid #22D3EE; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <p class="code">@yield('code')</p>
        <div class="route" aria-hidden="true"></div>
        <h1>@yield('message')</h1>
        <p>The page you were looking for could not be shown.</p>
        <a href="{{ url('/') }}">Back to VyaMap</a>
    </main>
</body>
</html>
