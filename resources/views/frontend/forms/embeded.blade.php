<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', __('Laravel')) }}</title>

        @vite(['resources/css/fonts.css', 'resources/css/app.css', 'resources/js/form.js'])
    </head>
    <body class="font-sans antialiased">
        <div id="aineForm" v-cloak>
            <aine-form uuid="{{$form->uuid}}"></aine-form>
        </div>
    </body>
</html>
