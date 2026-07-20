<?php

namespace App\Models;

use Database\Factories\PuestoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['nombre', 'salario_dia', 'salario_quincena', 'activo'])]
class Puesto extends Model
{
    /** @use HasFactory<PuestoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'activo' => true,
    ];

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
            ->logOnly(['nombre', 'salario_dia', 'salario_quincena', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
