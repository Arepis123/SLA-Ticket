<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }}</title>
    <style>
        html, body { margin: 0; height: 100%; background: #0e0e0e; }
        body { display: flex; align-items: center; justify-content: center; }
        img { display: block; max-width: 100%; max-height: 100vh; cursor: zoom-in; }
        body.zoomed { display: block; overflow: auto; }
        body.zoomed img { max-width: none; max-height: none; margin: auto; cursor: zoom-out; }
    </style>
</head>
<body>
    <img src="{{ $src }}" alt="{{ $name }}" onclick="document.body.classList.toggle('zoomed')">
</body>
</html>
