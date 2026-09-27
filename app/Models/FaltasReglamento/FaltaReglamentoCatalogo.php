<?php

namespace App\Models\FaltasReglamento;

use App\Models\Empresa;
use Database\Factories\FaltasReglamento\FaltaReglamentoCatalogoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['empresa_id', 'tipo_falta_reglamento_id', 'nombre', 'descripcion', 'activo'])]
class FaltaReglamentoCatalogo extends Model
{
    /** @use HasFactory<FaltaReglamentoCatalogoFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'catalogo_faltas_reglamento';

    protected $attributes = ['activo' => true];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<TipoFaltaReglamento, $this> */
    public function tipoFalta(): BelongsTo
    {
        return $this->belongsTo(TipoFaltaReglamento::class, 'tipo_falta_reglamento_id')->withTrashed();
    }

    /** @return HasMany<FaltaReglamento, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(FaltaReglamento::class, 'falta_reglamento_catalogo_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogo_faltas_reglamento')
            ->logOnly(['empresa_id', 'tipo_falta_reglamento_id', 'nombre', 'descripcion', 'activo'])
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
