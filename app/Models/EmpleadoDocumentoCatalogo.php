<?php

namespace App\Models;

use Database\Factories\EmpleadoDocumentoCatalogoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['empresa_id', 'empleado_carpeta_id', 'nombre', 'contenido_html'])]
class EmpleadoDocumentoCatalogo extends Model
{
    /** @use HasFactory<EmpleadoDocumentoCatalogoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'empleados_documentos_catalogo';

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<EmpleadoCarpeta, $this>
     */
    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(EmpleadoCarpeta::class, 'empleado_carpeta_id');
    }

    /**
     * @return BelongsToMany<Modulo, $this>
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(
            Modulo::class,
            'empleados_documentos_catalogo_modulos',
            'empleado_documento_catalogo_id',
            'modulo_id',
        )
            ->withPivot('empresa_id')
            ->wherePivot('empresa_id', $this->empresa_id);
    }

    /**
     * @param  Builder<EmpleadoDocumentoCatalogo>  $query
     * @return Builder<EmpleadoDocumentoCatalogo>
     */
    public function scopeVisiblesPara(Builder $query, Empresa $empresa, User $user): Builder
    {
        return $query
            ->where('empresa_id', $empresa->id)
            ->whereIn('empleado_carpeta_id', EmpleadoCarpeta::query()
                ->select('id')
                ->whereBelongsTo($empresa)
                ->where('activo', true)
                ->visiblesPara($user));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('empleados_documentos_catalogo')
            ->logOnly(['empresa_id', 'empleado_carpeta_id', 'nombre'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->setAttribute('empresa_id', $this->empresa_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }
}
