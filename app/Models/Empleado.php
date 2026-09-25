<?php

namespace App\Models;

use Database\Factories\EmpleadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'empresa_id',
    'nombre',
    'nombre_usuario',
    'correo',
    'curp',
    'rfc',
    'nss',
    'num_clinica_ss',
    'puesto_id',
    'estado_civil',
    'sexo',
    'domicilio',
    'telefono',
    'avatar',
    'salario_dia',
    'salario_quincena',
    'salario_vacaciones_finiquito',
    'aguinaldo',
    'prima_vacacional',
    'dias_vacaciones',
    'dias_liquidacion',
    'dias_descanso',
    'fecha_ingreso',
    'fecha_nacimiento',
    'periodo_prueba_meses',
    'fecha_contrato_siguiente',
    'fecha_contrato_indefinido',
    'fecha_ultimo_aviso',
    'fecha_evaluacion',
    'fecha_inicio_contrato',
    'fecha_termino_contrato',
])]
/** @property list<string>|null $dias_descanso */
class Empleado extends Model
{
    /** @use HasFactory<EmpleadoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'dias_descanso' => '[]',
    ];

    /**
     * @return BelongsTo<Puesto, $this>
     */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return HasMany<EmpleadoDocumento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(EmpleadoDocumento::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('empleados')
            ->logOnly([
                'empresa_id',
                'nombre',
                'nombre_usuario',
                'correo',
                'puesto_id',
                'estado_civil',
                'sexo',
                'salario_dia',
                'salario_quincena',
                'salario_vacaciones_finiquito',
                'aguinaldo',
                'prima_vacacional',
                'dias_vacaciones',
                'dias_liquidacion',
                'dias_descanso',
                'periodo_prueba_meses',
                'fecha_ingreso',
                'fecha_nacimiento',
                'fecha_contrato_siguiente',
                'fecha_contrato_indefinido',
                'fecha_ultimo_aviso',
                'fecha_evaluacion',
                'fecha_inicio_contrato',
                'fecha_termino_contrato',
            ])
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
            'salario_vacaciones_finiquito' => 'decimal:2',
            'aguinaldo' => 'decimal:2',
            'prima_vacacional' => 'decimal:2',
            'dias_vacaciones' => 'integer',
            'dias_liquidacion' => 'integer',
            'dias_descanso' => 'array',
            'fecha_ingreso' => 'date',
            'fecha_nacimiento' => 'date',
            'periodo_prueba_meses' => 'integer',
            'fecha_contrato_siguiente' => 'date',
            'fecha_contrato_indefinido' => 'date',
            'fecha_ultimo_aviso' => 'date',
            'fecha_evaluacion' => 'date',
            'fecha_inicio_contrato' => 'date',
            'fecha_termino_contrato' => 'date',
            'deleted_at' => 'datetime',
        ];
    }
}
