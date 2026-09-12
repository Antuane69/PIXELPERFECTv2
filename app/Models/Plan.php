<?php

namespace App\Models;

use App\IconoPlan;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $nombre
 * @property string $precio_mensual
 * @property string $color
 * @property IconoPlan $icono
 * @property string|null $modulos_incluidos
 * @property int|null $limite_usuarios
 * @property int $periodo_gracia_dias
 * @property bool $activo
 */
#[Fillable([
    'nombre',
    'precio_mensual',
    'color',
    'icono',
    'modulos_incluidos',
    'limite_usuarios',
    'periodo_gracia_dias',
    'activo',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'color' => '#7C3AED',
        'icono' => IconoPlan::Crown->value,
        'periodo_gracia_dias' => 15,
        'activo' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('planes')
            ->logOnly([
                'nombre',
                'precio_mensual',
                'color',
                'icono',
                'modulos_incluidos',
                'limite_usuarios',
                'periodo_gracia_dias',
                'activo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'icono' => IconoPlan::class,
            'limite_usuarios' => 'integer',
            'periodo_gracia_dias' => 'integer',
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
