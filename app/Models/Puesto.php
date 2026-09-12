<?php

namespace App\Models;

use Database\Factories\PuestoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['empresa_id', 'nombre', 'salario_dia', 'salario_quincena', 'activo'])]
class Puesto extends Model
{
    /** @use HasFactory<PuestoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'activo' => true,
    ];

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return HasMany<Empleado, $this>
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('puestos')
            ->logOnly(['empresa_id', 'nombre', 'salario_dia', 'salario_quincena', 'activo'])
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
        return [
            'salario_dia' => 'decimal:2',
            'salario_quincena' => 'decimal:2',
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
