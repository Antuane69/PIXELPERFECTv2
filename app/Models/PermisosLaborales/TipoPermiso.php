<?php

namespace App\Models\PermisosLaborales;

use App\Models\Empresa;
use Database\Factories\PermisosLaborales\TipoPermisoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['empresa_id', 'nombre', 'descripcion', 'activo'])]
class TipoPermiso extends Model
{
    /** @use HasFactory<TipoPermisoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'tipos_permisos';

    protected $attributes = [
        'activo' => true,
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<PermisoLaboral, $this> */
    public function permisosLaborales(): HasMany
    {
        return $this->hasMany(PermisoLaboral::class, 'tipo_permiso_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tipos_permisos')
            ->logOnly(['empresa_id', 'nombre', 'descripcion', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->setAttribute('empresa_id', $this->empresa_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
