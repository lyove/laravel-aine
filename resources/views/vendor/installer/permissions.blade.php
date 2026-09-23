@extends('vendor.installer.layouts.master')

@section('template_title')
    {{ trans('installer_messages.permissions.templateTitle') }}
@endsection

@section('title')
    {{ trans('installer_messages.permissions.title') }}
@endsection

@section('container')
    <div class="overflow-hidden rounded-lg ring-1 ring-slate-200">
        <ul class="divide-y divide-slate-100 bg-white">
            @foreach ($permissions['permissions'] as $permission)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span class="font-medium text-slate-700">{{ $permission['folder'] }}</span>
                    <span class="flex items-center gap-2 text-slate-500">
                        <span class="font-mono text-xs">{{ $permission['permission'] }}</span>
                        @if ($permission['isSet'])
                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @else
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    @if (isset($permissions['errors']))
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">{{ trans('installer_messages.permissions.errors_title') }}</p>
            <p class="mt-1">{{ trans('installer_messages.permissions.errors_description') }}</p>
            <pre class="mt-2 overflow-x-auto rounded bg-amber-100 px-3 py-2 text-xs text-amber-900">chown -R www-data:www-data storage/ bootstrap/cache/ database/
chmod -R 775 storage/ bootstrap/cache/ database/</pre>
        </div>
        <div class="mt-6 flex items-center justify-between">
            <a href="{{ route('LaravelInstaller::requirements') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                {{ trans('installer_messages.back') }}
            </a>
            <a href="{{ route('LaravelInstaller::permissions') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0011.667 0l3.181-3.183m-4.991-2.696v4.992m0 0h-4.992" /></svg>
                {{ trans('installer_messages.permissions.retry') }}
            </a>
        </div>
    @else
        <div class="mt-6 flex items-center justify-between">
            <a href="{{ route('LaravelInstaller::requirements') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                {{ trans('installer_messages.back') }}
            </a>
            <a href="{{ route('LaravelInstaller::environment') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                {{ trans('installer_messages.permissions.next') }}
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
            </a>
        </div>
    @endif
@endsection
