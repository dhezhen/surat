<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pembersih HTML berdaftar-putih untuk isi surat dan template yang diketik staff.
 * Isi ini ditampilkan ke Kaprodi dan dirender ke PDF, jadi skrip, atribut peristiwa,
 * dan gaya berbahaya tidak boleh lolos.
 */
class HtmlSanitizer
{
    private const TAG_DIIZINKAN = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 'sub', 'sup',
        'ol', 'ul', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'hr',
    ];

    /** Tag yang dibuang beserta isinya. */
    private const TAG_DIBUANG = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'svg', 'math', 'base', 'noscript', 'template'];

    private const GAYA_DIIZINKAN = [
        'text-align', 'text-indent', 'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
        'padding-left', 'width', 'vertical-align', 'font-weight', 'font-style', 'text-decoration', 'line-height',
    ];

    public static function bersihkan(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="akar">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $akar = $dom->getElementById('akar');
        if (! $akar) {
            return e(strip_tags($html));
        }

        self::bersihkanAnak($akar);

        $hasil = '';
        foreach ($akar->childNodes as $anak) {
            $hasil .= $dom->saveHTML($anak);
        }

        return trim($hasil);
    }

    private static function bersihkanAnak(DOMNode $induk): void
    {
        foreach (iterator_to_array($induk->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                if ($node->nodeType !== XML_TEXT_NODE) {
                    $induk->removeChild($node);
                }
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::TAG_DIBUANG, true)) {
                $induk->removeChild($node);
                continue;
            }

            self::bersihkanAnak($node);

            if (! in_array($tag, self::TAG_DIIZINKAN, true)) {
                // Tag tak dikenal dilepas, isinya dipertahankan.
                while ($node->firstChild) {
                    $induk->insertBefore($node->firstChild, $node);
                }
                $induk->removeChild($node);
                continue;
            }

            self::bersihkanAtribut($node);
        }
    }

    private static function bersihkanAtribut(DOMElement $el): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $nama = strtolower($attr->name);
            $nilai = $attr->value;

            $boleh = match ($nama) {
                'style' => self::bersihkanGaya($nilai) !== '',
                'class' => $nilai === 'indent',
                'colspan', 'rowspan' => ctype_digit($nilai),
                default => false,
            };

            if (! $boleh) {
                $el->removeAttribute($attr->name);
            } elseif ($nama === 'style') {
                $el->setAttribute('style', self::bersihkanGaya($nilai));
            }
        }
    }

    private static function bersihkanGaya(string $gaya): string
    {
        $keluar = [];
        foreach (explode(';', $gaya) as $deklarasi) {
            if (! str_contains($deklarasi, ':')) {
                continue;
            }
            [$prop, $nilai] = array_map('trim', explode(':', $deklarasi, 2));
            $prop = strtolower($prop);

            if (in_array($prop, self::GAYA_DIIZINKAN, true) && preg_match('/^[A-Za-z0-9\s.,%\-]+$/', $nilai)) {
                $keluar[] = "{$prop}: {$nilai}";
            }
        }

        return implode('; ', $keluar);
    }
}
