@props(['key', 'field', 'value' => null, 'required' => false])

@if (! empty($field['options']))
    {{-- Dropdown: nilai cukup baku sehingga mencegah salah ketik (S4). --}}
    <select id="spec_{{ $key }}" name="specs[{{ $key }}]"
            @if ($required) required @endif
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">{{ __('-- Pilih --') }}</option>
        @foreach ($field['options'] as $option)
            <option value="{{ $option }}" @selected((string) old('specs.'.$key, $value) === $option)>{{ $option }}</option>
        @endforeach
        {{-- Nilai lama yang tidak ada di daftar tetap dapat dipilih. --}}
        @if ($value && ! in_array($value, $field['options'], true))
            <option value="{{ $value }}" selected>{{ $value }}</option>
        @endif
    </select>
@else
    <x-text-input :id="'spec_'.$key" :name="'specs['.$key.']'" type="text" class="mt-1"
                  :value="old('specs.'.$key, $value)"
                  :required="$required" maxlength="100"
                  :placeholder="$field['placeholder'] ?? ''" />
@endif
