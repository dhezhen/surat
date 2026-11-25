<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat {{ $surat->nomor_surat_resmi }}</title>
    <style>
        @page { size: A4; margin: 2cm 2.5cm 2.5cm 3cm; }
        body { margin: 0; font-family: "Times New Roman", Times, serif; font-size: 12pt; }
        .watermark { position: fixed; left: 0; width: 100%; z-index: 1000; text-align: center; transform: rotate(-25deg); font-family: Helvetica, sans-serif; font-size: 40pt; font-weight: bold; color: #b91c1c; opacity: 0.2; }
    </style>
</head>
<body>
    {{-- Elemen fixed harus mendahului isi agar DomPDF mengulangnya di setiap halaman. --}}
    @if (! empty($salinan))
        @foreach (['18%', '46%', '76%'] as $atas)
            <div class="watermark" style="top: {{ $atas }}">SALINAN VERIFIKASI</div>
        @endforeach
    @endif
    @include('surat-keluar._kertas')
</body>
</html>
