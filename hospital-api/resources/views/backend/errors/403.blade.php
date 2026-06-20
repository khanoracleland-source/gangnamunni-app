<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('messages.forbidden') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f8;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .error-box {
            background: #fff;
            padding: 40px 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .error-box h1 {
            font-size: 64px;
            color: #e3342f;
            margin-bottom: 10px;
        }

        .error-box h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .error-box p {
            font-size: 16px;
            margin-bottom: 30px;
            color: #666;
        }

        .error-box a {
            display: inline-block;
            padding: 10px 25px;
            background-color: #3490dc;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .error-box a:hover {
            background-color: #2779bd;
        }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>403</h1>
        <h2>{{ __('messages.access_denied') }}</h2>
        <p>{{ $message ?? __('messages.no_permission_access_page') }}</p>
    </div>
</body>
</html>
