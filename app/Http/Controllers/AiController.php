<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiException;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    /** Menyusun isi surat dari pokok isi yang diketik staff. Hasil hanya dikembalikan, tidak disimpan. */
    public function susun(Request $request, AiService $ai): JsonResponse
    {
        if (! AiService::tersedia()) {
            return response()->json(['message' => 'Fitur AI belum diaktifkan oleh Super Admin.'], 403);
        }

        $data = $request->validate([
            'pokok' => ['required', 'string', 'min:10', 'max:2000'],
            'perihal' => ['nullable', 'string', 'max:255'],
            'tujuan' => ['nullable', 'string', 'max:255'],
            'jenis' => ['nullable', 'string', 'max:255'],
        ], [
            'pokok.required' => 'Tuliskan pokok isi surat terlebih dahulu.',
            'pokok.min' => 'Pokok isi terlalu singkat; tulis minimal satu kalimat.',
        ]);

        try {
            return response()->json(['html' => $ai->susun($data)]);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
