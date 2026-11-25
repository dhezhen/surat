@props(['terpilih' => null, 'kunciDigital' => false])

{{-- Radio metode tanda tangan. Atribut tambahan (mis. x-model) diteruskan ke tiap radio. --}}
<fieldset>
    <legend class="text-sm font-medium text-slate-700">Metode tanda tangan</legend>
    <div class="mt-2 space-y-2">
        @foreach (\App\Models\SuratKeluar::METODE_TTD as $nilai => $m)
            @php $mati = $kunciDigital && $nilai === \App\Models\SuratKeluar::TTD_DIGITAL; @endphp
            <label class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm has-[:checked]:border-indigo-800 has-[:checked]:bg-indigo-50 {{ $mati ? 'opacity-60' : 'cursor-pointer' }}">
                <input type="radio" name="metode_ttd" value="{{ $nilai }}" required @checked($terpilih === $nilai && ! $mati) @disabled($mati)
                       {{ $attributes->merge(['class' => 'mt-0.5 border-slate-300 text-indigo-800 focus:ring-indigo-500']) }}>
                <span>
                    <span class="block font-medium text-slate-900">{{ $m['label'] }}</span>
                    <span class="block text-xs text-slate-600">{{ $m['keterangan'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('metode_ttd')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</fieldset>
