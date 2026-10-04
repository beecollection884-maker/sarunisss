<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMP IP YAKIN - Sistem Manajemen Sekolah</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/src/main.tsx'])
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">
    <div id="root"></div>
</body>
</html>
