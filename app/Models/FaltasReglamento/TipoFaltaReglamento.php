<?php

namespace App\Models\FaltasReglamento;

use App\Models\Empleado;
use App\Models\Empresa;
use Database\Factories\FaltasReglamento\TipoFaltaReglamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['empresa_id', 'nombre', 'descripcion', 'activo'])]
class TipoFaltaReglamento extends Model
{
    /** @use HasFactory<TipoFaltaReglamentoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'tipos_faltas_reglamento';

    protected $attributes = ['activo' => true];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<FaltaReglamentoCatalogo, $this> */
    public function faltasCatalogo(): HasMany
    {
        return $this->hasMany(FaltaReglamentoCatalogo::class, 'tipo_falta_reglamento_id');
    }

    /** @return BelongsToMany<Empleado, $this> */
    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(
            Empleado::class,
            'empleado_tipo_falta_reglamento',
            'tipo_falta_reglamento_id',
            'empleado_id',
        )->withPivot(['empresa_id', 'cantidad'])->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tipos_faltas_reglamento')
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
