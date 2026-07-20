<?php

namespace App\Models;

use Database\Factories\EmpleadoDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'empleado_id',
    'tipo_documento_empleado_id',
    'nombre_original',
    'ruta',
    'disco',
    'mime_type',
    'tamano',
    'vence_el',
])]
#[Hidden(['ruta', 'disco'])]
class EmpleadoDocumento extends Model
{
    /** @use HasFactory<EmpleadoDocumentoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'disco' => 'local',
    ];

    /**
     * @return BelongsTo<Empleado, $this>
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * @return BelongsTo<TipoDocumentoEmpleado, $this>
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumentoEmpleado::class, 'tipo_documento_empleado_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documentos_empleado')
            ->logOnly([
                'empleado_id',
                'tipo_documento_empleado_id',
                'nombre_original',
                'mime_type',
                'tamano',
                'vence_el',
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
            'tamano' => 'integer',
            'vence_el' => 'date',
            'deleted_at' => 'datetime',
        ];
    }
}
