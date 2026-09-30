<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="APP_URL" content="{{ config('app.url') }}">

        <!-- Resolved admin entry paths (configurable in Settings > Security) -->
        <script>
            window.ADMIN_BASE = "{{ \App\Support\AdminPath::prefix() }}";
            window.ADMIN_API_BASE = "{{ \App\Support\AdminPath::apiPrefix() }}";
        </script>

        <!-- <title>{{ env("APP_NAME") }}</title> -->
        <title>{{ config('app.name', 'Aine') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ config('app.url') . '/images/favicon.svg'}}">

        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    </head>
    <body class="font-sans antialiased">
        <div id="admin" v-cloak>
            <admin></admin>
        </div>
    </body>
</html>
