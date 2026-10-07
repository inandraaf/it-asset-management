<section>
    <header>
        <h2 class="text-lg font-medium text-slate-900">
            {{ __('Informasi Profil') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __('Perbarui nama tampilan akun Anda.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Nama')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label :value="__('Nama Pengguna')" />
            <p class="mt-1 font-mono text-sm text-slate-900">{{ $user->username }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Nama pengguna tidak dapat diubah.') }}</p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Simpan') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }"
                   x-show="show"
                   x-transition
                   x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-slate-600">{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>
