<?php

namespace App\Models;

use App\EstadoMembresiaEmpresa;
use Database\Factories\MembresiaEmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property EstadoMembresiaEmpresa $estado
 */
#[Fillable([
    'empresa_id',
    'user_id',
    'estado',
    'fecha_incorporacion',
    'suspendida_at',
    'invitado_por_user_id',
])]
class MembresiaEmpresa extends Model
{
    /** @use HasFactory<MembresiaEmpresaFactory> */
    use HasFactory;

    protected $table = 'membresias_empresa';

    protected $attributes = [
        'estado' => EstadoMembresiaEmpresa::Activa->value,
    ];

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por_user_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoMembresiaEmpresa::Activa;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoMembresiaEmpresa::class,
            'fecha_incorporacion' => 'datetime',
            'suspendida_at' => 'datetime',
        ];
    }
}
