<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="APP_URL" content="{{ config('app.url') }}">

        <title>{{ config('app.name', __('Laravel')) }}</title>
        
        <link rel="icon" type="image/svg+xml" href="{{ config('app.url') . '/images/favicon.svg'}}">
        @vite(['resources/css/fonts.css'])
        <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    </head>
    <body class="bg-gray-100">
        <div class="font-sans text-gray-900 antialiased bg-gray-100">
            {{ $slot }}
        </div>
        <script>
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.tagName !== 'FORM') return;
            var valid = true;
            form.querySelectorAll('input[required], select[required]').forEach(function (input) {
                if (!input.value.trim()) {
                    valid = false;
                    input.classList.add('border-red-500', 'bg-red-50');
                    var err = input.parentElement.querySelector('.field-error-msg');
                    if (!err) {
                        err = document.createElement('p');
                        err.className = 'field-error-msg text-sm text-red-600 mt-1';
                        err.textContent = '{{ __("This field is required.") }}';
                        input.parentElement.appendChild(err);
                    }
                }
            });
            if (!valid) e.preventDefault();
        }, true);
        document.addEventListener('input', function (e) {
            var input = e.target;
            if (input.matches('input[required], select[required]')) {
                input.classList.remove('border-red-500', 'bg-red-50');
                var err = input.parentElement.querySelector('.field-error-msg');
                if (err) err.remove();
            }
        });
        </script>
    </body>
</html>
