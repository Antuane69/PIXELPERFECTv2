<?php

namespace App\Models;

use App\TipoGrupoEmpresarial;
use Database\Factories\GrupoEmpresarialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TipoGrupoEmpresarial $tipo
 */
#[Fillable(['nombre', 'slug', 'tipo', 'activo'])]
class GrupoEmpresarial extends Model
{
    /** @use HasFactory<GrupoEmpresarialFactory> */
    use HasFactory;

    protected $table = 'grupos_empresariales';

    protected $attributes = [
        'tipo' => TipoGrupoEmpresarial::Individual->value,
        'activo' => true,
    ];

    /**
     * @return HasMany<Empresa, $this>
     */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoGrupoEmpresarial::class,
            'activo' => 'boolean',
        ];
    }
}
