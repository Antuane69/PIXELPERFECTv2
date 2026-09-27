<?php

namespace App\Models\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\FaltasReglamento\FaltaReglamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $empleado_id
 * @property int $falta_reglamento_catalogo_id
 * @property int|null $solicitante_user_id
 * @property int|null $resuelto_por_user_id
 * @property string $solicitante_nombre
 * @property string $solicitante_correo
 * @property CarbonImmutable $fecha_ocurrencia
 * @property EstadoFaltaReglamento $estado
 * @property string|null $comentarios
 * @property string|null $comentarios_rechazo
 * @property CarbonImmutable|null $resuelto_at
 */
#[Fillable([
    'empresa_id',
    'empleado_id',
    'falta_reglamento_catalogo_id',
    'solicitante_user_id',
    'resuelto_por_user_id',
    'solicitante_nombre',
    'solicitante_correo',
    'fecha_ocurrencia',
    'estado',
    'comentarios',
    'comentarios_rechazo',
    'resuelto_at',
])]
class FaltaReglamento extends Model
{
    /** @use HasFactory<FaltaReglamentoFactory> */
    use HasFactory;

    protected $table = 'faltas_reglamento';

    protected $attributes = ['estado' => EstadoFaltaReglamento::Pendiente->value];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Empleado, $this> */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class)->withTrashed();
    }

    /** @return BelongsTo<FaltaReglamentoCatalogo, $this> */
    public function faltaCatalogo(): BelongsTo
    {
        return $this->belongsTo(FaltaReglamentoCatalogo::class, 'falta_reglamento_catalogo_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_user_id');
    }

    /** @return HasMany<FaltaReglamentoEvidencia, $this> */
    public function evidencias(): HasMany
    {
        return $this->hasMany(FaltaReglamentoEvidencia::class, 'falta_reglamento_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoFaltaReglamento::class,
            'fecha_ocurrencia' => 'date',
            'resuelto_at' => 'datetime',
        ];
    }
}
