<?php

namespace App\Models;

use Database\Factories\ModuloFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['clave', 'nombre', 'descripcion', 'activo', 'orden'])]
class Modulo extends Model
{
    /** @use HasFactory<ModuloFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Empresa, $this>
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_modulo')
            ->withPivot('habilitado')
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
