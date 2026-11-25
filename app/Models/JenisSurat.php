<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisSurat extends Model
{
    protected $fillable = ['nama_surat', 'template_html', 'form_schema', 'is_active', 'pdf_publik'];

    protected function casts(): array
    {
        return [
            'form_schema' => 'array',
            'is_active' => 'boolean',
            'pdf_publik' => 'boolean',
        ];
    }

    public function suratKeluar()
    {
        return $this->hasMany(SuratKeluar::class);
    }
}
