<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membuka input yang disandikan resources/js/sandi-form.js ("b64:<base64>").
 * Disandikan agar HTML template tidak diblokir firewall hosting (ModSecurity).
 */
class BukaSandiInput
{
    private const AWALAN = 'b64:';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            $request->request->replace($this->buka($request->request->all()));
        }

        return $next($request);
    }

    private function buka(array $data): array
    {
        foreach ($data as $kunci => $nilai) {
            if (is_array($nilai)) {
                $data[$kunci] = $this->buka($nilai);
            } elseif (is_string($nilai) && str_starts_with($nilai, self::AWALAN)) {
                $asli = base64_decode(substr($nilai, strlen(self::AWALAN)), true);
                if ($asli !== false && mb_check_encoding($asli, 'UTF-8')) {
                    $data[$kunci] = $asli;
                }
            }
        }

        return $data;
    }
}
