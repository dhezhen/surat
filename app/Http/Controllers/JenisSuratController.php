<?php

namespace App\Http\Controllers;

use App\Models\JenisSurat;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JenisSuratController extends Controller
{
    public function index()
    {
        return view('jenis-surat.index', [
            'jenisSurats' => JenisSurat::withCount('suratKeluar')->orderBy('nama_surat')->get(),
        ]);
    }

    public function create()
    {
        return view('jenis-surat.form', ['jenis' => new JenisSurat(['is_active' => true, 'form_schema' => []])]);
    }

    public function store(Request $request)
    {
        JenisSurat::create($this->validasi($request));

        return redirect()->route('jenis-surat.index')->with('status', 'Jenis surat ditambahkan.');
    }

    public function edit(JenisSurat $jenisSurat)
    {
        return view('jenis-surat.form', ['jenis' => $jenisSurat]);
    }

    public function update(Request $request, JenisSurat $jenisSurat)
    {
        $jenisSurat->update($this->validasi($request, $jenisSurat));

        return redirect()->route('jenis-surat.index')->with('status', 'Jenis surat diperbarui.');
    }

    public function destroy(JenisSurat $jenisSurat)
    {
        if ($jenisSurat->suratKeluar()->exists()) {
            return back()->withErrors(['hapus' => 'Jenis surat sudah dipakai surat keluar. Nonaktifkan saja, jangan dihapus.']);
        }
        $jenisSurat->delete();

        return redirect()->route('jenis-surat.index')->with('status', 'Jenis surat dihapus.');
    }

    private function validasi(Request $request, ?JenisSurat $current = null): array
    {
        $data = $request->validate([
            'nama_surat' => ['required', 'string', 'max:255', Rule::unique('jenis_surats', 'nama_surat')->ignore($current)],
            'template_html' => ['required', 'string'],
            'form_schema' => ['required', 'string'],
        ], [
            'nama_surat.unique' => 'Nama jenis surat sudah ada.',
        ]);

        $schema = json_decode($data['form_schema'], true);
        if (! is_array($schema) || ! array_is_list($schema)) {
            throw ValidationException::withMessages(['form_schema' => 'Skema harus berupa JSON array berisi definisi field.']);
        }
        $names = [];
        foreach ($schema as $i => $f) {
            $name = is_array($f) ? ($f['name'] ?? '') : '';
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $name) || empty($f['label'])
                || in_array($name, ['nomor_surat', 'tanggal_surat', 'tujuan_surat', 'perihal', 'halaman_baru'], true)
                || in_array($name, $names, true)
                || ! in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext', 'list', 'tabel', 'date', 'number', 'select'], true)
                || (($f['type'] ?? '') === 'select' && empty($f['options']))
                || (($f['type'] ?? '') === 'tabel' && (! is_array($f['kolom'] ?? null) || count(array_filter($f['kolom'])) === 0 || count($f['kolom']) > 8))) {
                throw ValidationException::withMessages(['form_schema' => 'Field ke-'.($i + 1).' tidak valid: wajib ada "name" (huruf kecil/angka/underscore, unik, bukan nama bawaan), "label", dan "type" (text, textarea, richtext, list, tabel, date, number, select; select memerlukan "options", tabel memerlukan 1-8 "kolom").']);
            }
            $names[] = $name;
        }

        $data['form_schema'] = $schema;
        $data['template_html'] = HtmlSanitizer::bersihkan($data['template_html']);
        $data['is_active'] = $request->boolean('is_active');
        $data['pdf_publik'] = $request->boolean('pdf_publik');

        return $data;
    }
}
