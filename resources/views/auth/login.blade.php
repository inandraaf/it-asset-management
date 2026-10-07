<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Masuk ke akun Anda') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Gunakan nama pengguna yang diberikan Admin IT.') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Username -->
        <div>
            <x-input-label for="username" :value="__('Nama Pengguna')" />
            <x-text-input id="username" class="mt-1 block w-full" type="text" name="username"
                          :value="old('username')" required autofocus autocomplete="username"
                          placeholder="admin" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Kata Sandi')" />
            <x-text-input id="password" class="mt-1 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">{{ __('Ingat saya') }}</span>
            </label>
        </div>

        <x-primary-button class="w-full">
            {{ __('Masuk') }}
        </x-primary-button>
    </form>

    <p class="mt-6 border-t border-slate-200 pt-4 text-center text-xs text-slate-500">
        {{ __('Lupa kata sandi? Hubungi operator untuk menjalankan perintah reset kata sandi.') }}
    </p>
</x-guest-layout>
