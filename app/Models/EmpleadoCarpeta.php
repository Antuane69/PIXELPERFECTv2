<?php

namespace App\Models;

use Database\Factories\EmpleadoCarpetaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['empresa_id', 'creado_por_id', 'nombre', 'activo'])]
class EmpleadoCarpeta extends Model
{
    /** @use HasFactory<EmpleadoCarpetaFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'empleados_carpetas';

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
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuariosConAcceso(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'empleados_carpetas_usuarios',
            'empleado_carpeta_id',
            'user_id',
        )->withPivot('empresa_id')->withTimestamps();
    }

    /**
     * @return HasMany<EmpleadoDocumentoCatalogo, $this>
     */
    public function documentosCatalogo(): HasMany
    {
        return $this->hasMany(EmpleadoDocumentoCatalogo::class, 'empleado_carpeta_id');
    }

    /**
     * @param  Builder<EmpleadoCarpeta>  $query
     * @return Builder<EmpleadoCarpeta>
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $visibleQuery): Builder => $visibleQuery
            ->where('creado_por_id', $user->id)
            ->orWhereHas('usuariosConAcceso', fn (Builder $accessQuery): Builder => $accessQuery
                ->whereKey($user->id)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('empleados_carpetas')
            ->logOnly(['empresa_id', 'creado_por_id', 'nombre', 'activo'])
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
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
