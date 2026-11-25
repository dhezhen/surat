{{-- Kop yang disusun dari identitas dan logo. Variabel: $identitas, $logoKop. Tabel dipakai demi DomPDF. --}}
<table style="width:100%; border-collapse:collapse">
    <tr>
        <td style="width:2.5cm; padding:0 0 4px 0; text-align:left; vertical-align:middle">
            @if ($logoKop)
                <img src="{{ $logoKop }}" alt="Logo" style="width:2.3cm">
            @endif
        </td>
        <td style="padding:0 0 4px 0; text-align:center; vertical-align:middle; color:#333">
            <div style="font-size:15pt; font-weight:bold; line-height:1.2">{{ $identitas['nama_universitas'] }}</div>
            <div style="font-size:15pt; font-weight:bold; line-height:1.2">{{ $identitas['nama_fakultas'] }}</div>
            <div style="font-size:15pt; font-weight:bold; line-height:1.2">{{ $identitas['nama_prodi'] }}</div>
            @foreach ($identitas['alamat'] as $baris)
                <div style="font-size:10pt; font-style:italic; line-height:1.3">{{ $baris }}</div>
            @endforeach
        </td>
        <td style="width:2.5cm"></td>
    </tr>
</table>
<div style="border-top:2.5pt solid #333; height:2pt; border-bottom:0.75pt solid #333; margin:2px 0 16px 0"></div>
