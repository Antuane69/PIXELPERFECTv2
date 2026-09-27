<?php

namespace App\Models;

use App\Enums\Incapacidades\EstadoIncapacidad;
use Carbon\CarbonImmutable;
use Database\Factories\IncapacidadFactory;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $empleado_id
 * @property int|null $solicitante_user_id
 * @property int|null $resuelto_por_user_id
 * @property string $solicitante_nombre
 * @property string $solicitante_correo
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable $fecha_fin
 * @property EstadoIncapacidad $estado
 * @property string $motivo
 * @property string|null $comentarios_rechazo
 * @property CarbonImmutable|null $resuelto_at
 * @property string|null $nombre_original
 * @property string|null $mime_type
 * @property string|null $extension
 * @property string|null $archivo
 */
#[Fillable([
    'empresa_id',
    'empleado_id',
    'solicitante_user_id',
    'resuelto_por_user_id',
    'solicitante_nombre',
    'solicitante_correo',
    'fecha_inicio',
    'fecha_fin',
    'estado',
    'motivo',
    'comentarios_rechazo',
    'resuelto_at',
    'nombre_original',
    'mime_type',
    'extension',
    'archivo',
])]
#[Hidden(['archivo'])]
class Incapacidad extends Model
{
    /** @use HasFactory<IncapacidadFactory> */
    use HasFactory;

    protected $table = 'incapacidades';

    protected $attributes = [
        'estado' => EstadoIncapacidad::Pendiente->value,
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

    /**
     * Keep the BLOB out of implicit route model binding for review and download routes.
     */
    public function resolveRouteBindingQuery(
        mixed $query,
        mixed $value,
        mixed $field = null,
    ): BuilderContract|Relation {
        return parent::resolveRouteBindingQuery($query, $value, $field)->select([
            'id',
            'empresa_id',
            'empleado_id',
            'solicitante_user_id',
            'resuelto_por_user_id',
            'solicitante_nombre',
            'solicitante_correo',
            'fecha_inicio',
            'fecha_fin',
            'estado',
            'motivo',
            'comentarios_rechazo',
            'resuelto_at',
            'nombre_original',
            'mime_type',
            'extension',
            'created_at',
            'updated_at',
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoIncapacidad::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'resuelto_at' => 'datetime',
        ];
    }
}
