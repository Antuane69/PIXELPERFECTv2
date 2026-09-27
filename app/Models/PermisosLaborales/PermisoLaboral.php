<?php

namespace App\Models\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PermisosLaborales\PermisoLaboralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $empleado_id
 * @property int|null $tipo_permiso_id
 * @property int|null $solicitante_user_id
 * @property int|null $resuelto_por_user_id
 * @property string $solicitante_nombre
 * @property string $solicitante_correo
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable $fecha_fin
 * @property EstadoPermisoLaboral $estado
 * @property string|null $comentarios
 * @property string|null $comentarios_rechazo
 * @property CarbonImmutable|null $resuelto_at
 */
#[Fillable([
    'empresa_id',
    'empleado_id',
    'tipo_permiso_id',
    'solicitante_user_id',
    'resuelto_por_user_id',
    'solicitante_nombre',
    'solicitante_correo',
    'fecha_inicio',
    'fecha_fin',
    'estado',
    'comentarios',
    'comentarios_rechazo',
    'resuelto_at',
])]
class PermisoLaboral extends Model
{
    /** @use HasFactory<PermisoLaboralFactory> */
    use HasFactory;

    protected $table = 'permisos_laborales';

    protected $attributes = [
        'estado' => EstadoPermisoLaboral::Pendiente->value,
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<TipoPermiso, $this> */
    public function tipoPermiso(): BelongsTo
    {
        return $this->belongsTo(TipoPermiso::class)->withTrashed();
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
        return $this->belongsToMany(Empleado::class, 'permiso_laboral_empleado_cubre')
            ->withPivot('empresa_id')
            ->withTimestamps()
            ->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPermisoLaboral::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'resuelto_at' => 'datetime',
        ];
    }
}
