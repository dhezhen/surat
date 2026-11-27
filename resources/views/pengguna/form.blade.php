@php
    $edit = $pengguna->exists;
    $diriSendiri = $edit && $pengguna->is(Auth::user());
    $label = \App\Support\Identitas::ambil('label_id_penandatangan');
@endphp

<x-app-layout>
    <x-slot name="title">{{ $edit ? 'Ubah Pengguna' : 'Tambah Pengguna' }}</x-slot>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-900">{{ $edit ? 'Ubah Pengguna' : 'Tambah Pengguna' }}</h1></x-slot>

    <form method="post" action="{{ $edit ? route('pengguna.update', $pengguna) : route('pengguna.store') }}"
          class="max-w-3xl bg-white border border-slate-200 rounded-lg p-6 grid gap-4 sm:grid-cols-2">
        @csrf
        @if ($edit) @method('put') @endif

        <div class="sm:col-span-2">
            <x-input-label for="name" value="Nama lengkap beserta gelar" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $pengguna->name)" required />
        </div>
        <div>
            <x-input-label for="email" value="Email (untuk masuk)" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $pengguna->email)" required autocomplete="off" />
        </div>
        <div>
            <x-input-label for="jabatan" value="Jabatan" />
            <x-text-input id="jabatan" name="jabatan" class="mt-1 block w-full" :value="old('jabatan', $pengguna->jabatan)" required />
        </div>
        <div>
            <x-input-label for="nidn" :value="$label.' (opsional)'" />
            <x-text-input id="nidn" name="nidn" class="mt-1 block w-full" :value="old('nidn', $pengguna->nidn)" />
        </div>
        <div>
            <x-input-label for="role" value="Peran" />
            <select id="role" name="role" class="mt-1 block w-full rounded-md border-slate-300 text-sm" @disabled($diriSendiri)>
                @foreach (\App\Models\User::pilihanPeran() as $nilai => $nama)
                    <option value="{{ $nilai }}" @selected(old('role', $pengguna->kunciPeran()) === $nilai)>{{ $nama }}</option>
                @endforeach
            </select>
            @if ($diriSendiri)
                <input type="hidden" name="role" value="{{ $pengguna->kunciPeran() }}">
                <p class="mt-1 text-xs text-slate-600">Peran akun Anda sendiri tidak dapat diubah.</p>
            @else
                <p class="mt-1 text-xs text-slate-600">Peran tambahan dikelola di <a href="{{ route('pengaturan.aplikasi') }}#judul-peran" class="underline">Pengaturan Aplikasi</a>.</p>
            @endif
            <x-input-error :messages="$errors->get('role')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password" :value="$edit ? 'Kata sandi baru (kosongkan jika tidak diubah)' : 'Kata sandi awal'" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $edit" />
            <p class="mt-1 text-xs text-slate-600">Minimal 8 karakter. Sampaikan kepada pengguna secara langsung, lalu minta menggantinya di Profil Akun.</p>
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Ulangi kata sandi" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $edit" />
        </div>

        <label class="sm:col-span-2 inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pengguna->is_active)) @disabled($diriSendiri) class="rounded border-slate-300 text-indigo-800">
            Akun aktif (dapat masuk ke sistem)
        </label>

        <div class="sm:col-span-2 flex gap-3">
            <x-primary-button>Simpan</x-primary-button>
            <a href="{{ route('pengguna.index') }}" class="self-center text-sm text-slate-600 underline">Batal</a>
        </div>
    </form>
</x-app-layout>
