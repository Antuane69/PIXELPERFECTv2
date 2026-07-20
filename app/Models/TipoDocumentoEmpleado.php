<?php

namespace App\Models;

use Database\Factories\TipoDocumentoEmpleadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'nombre',
    'es_renovable',
    'frecuencia_cantidad',
    'frecuencia_tipo',
    'documentos_aceptados',
    'activo',
])]
class TipoDocumentoEmpleado extends Model
{
    /** @use HasFactory<TipoDocumentoEmpleadoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'es_renovable' => false,
        'activo' => true,
    ];

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
            ->useLogName('tipos_documento_empleado')
            ->logOnly([
                'nombre',
                'es_renovable',
                'frecuencia_cantidad',
                'frecuencia_tipo',
                'documentos_aceptados',
                'activo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_renovable' => 'boolean',
            'frecuencia_cantidad' => 'integer',
            'documentos_aceptados' => 'array',
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
