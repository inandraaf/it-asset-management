<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Profil') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Kelola informasi akun dan keamanan Anda') }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card>
            @include('profile.partials.delete-user-form')
        </x-card>
    </div>
</x-app-layout>
