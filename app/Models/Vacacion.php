<?php

namespace App\Models;

use App\EstadoVacacion;
use Carbon\CarbonImmutable;
use Database\Factories\VacacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $empleado_id
 * @property int|null $solicitante_user_id
 * @property int|null $resuelto_por_user_id
 * @property int $dias_solicitados
 * @property int $saldo_dias_al_solicitar
 * @property CarbonImmutable|null $fecha_ultima_vacacion_al_solicitar
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable $fecha_fin
 * @property EstadoVacacion $estado
 * @property string|null $comentarios
 * @property string|null $comentarios_rechazo
 * @property CarbonImmutable|null $resuelto_at
 */
#[Fillable([
    'empresa_id',
    'empleado_id',
    'solicitante_user_id',
    'resuelto_por_user_id',
    'solicitante_nombre',
    'solicitante_correo',
    'dias_solicitados',
    'saldo_dias_al_solicitar',
    'fecha_ultima_vacacion_al_solicitar',
    'fecha_inicio',
    'fecha_fin',
    'estado',
    'comentarios',
    'comentarios_rechazo',
    'resuelto_at',
])]
class Vacacion extends Model
{
    /** @use HasFactory<VacacionFactory> */
    use HasFactory;

    protected $table = 'vacaciones';

    protected $attributes = [
        'estado' => EstadoVacacion::Pendiente->value,
    ];

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

    /** @return BelongsToMany<Empleado, $this> */
    public function empleadosCobertura(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'vacacion_empleado_cubre')
            ->withPivot('empresa_id')
            ->withTimestamps()
            ->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoVacacion::class,
            'dias_solicitados' => 'integer',
            'saldo_dias_al_solicitar' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_ultima_vacacion_al_solicitar' => 'date',
            'resuelto_at' => 'datetime',
        ];
    }
}
