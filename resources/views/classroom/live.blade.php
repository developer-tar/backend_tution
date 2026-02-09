<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $classroom->name }} – Live Class</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }

        .card {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
        }

        .card h1 {
            margin: 0 0 0.5rem;
            font-size: 1.25rem;
            color: #333;
        }

        .card p {
            color: #666;
            margin: 0 0 1.5rem;
            font-size: 0.95rem;
        }

        .card a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #1a73e8;
            color: #fff;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .card a:hover {
            background: #1557b0;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>{{ $classroom->name }}</h1>
        <p>{{ $classroom->course->name ?? 'Live class' }}</p>
        @php
        $displayName = auth()->user()->full_name ?? 'Guest';
        $nameParam = 'userInfo.displayName=' . rawurlencode(json_encode($displayName));
        @endphp
        <a href="https://meet.jit.si/{{ $classroom->room_code }}#{{ $nameParam }}" target="_blank" rel="noopener noreferrer">
            🎥 Join Live Class
        </a>
    </div>
</body>

</html>