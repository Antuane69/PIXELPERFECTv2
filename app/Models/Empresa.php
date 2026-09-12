<?php

namespace App\Models;

use App\EstadoEmpresa;
use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property int $grupo_empresarial_id
 * @property string $nombre_legal
 * @property string|null $nombre_comercial
 * @property string $slug
 * @property EstadoEmpresa $estado
 * @property Carbon|null $demo_ends_at
 */
#[Fillable([
    'grupo_empresarial_id',
    'nombre_legal',
    'nombre_comercial',
    'slug',
    'rfc',
    'correo_contacto',
    'telefono_contacto',
    'zona_horaria',
    'moneda',
    'estado',
    'demo_ends_at',
    'activada_at',
    'vence_at',
    'desactivada_at',
    'retencion_hasta',
])]
#[Hidden(['stripe_id', 'pm_type', 'pm_last_four'])]
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use Billable, HasFactory;

    protected $attributes = [
        'zona_horaria' => 'America/Mexico_City',
        'moneda' => 'MXN',
        'estado' => EstadoEmpresa::Prospecto->value,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stripeName(): string
    {
        return $this->nombre_comercial ?: $this->nombre_legal;
    }

    public function stripeEmail(): ?string
    {
        return $this->correo_contacto;
    }

    public function stripePhone(): ?string
    {
        return $this->telefono_contacto;
    }

    /**
     * @return BelongsTo<GrupoEmpresarial, $this>
     */
    public function grupoEmpresarial(): BelongsTo
    {
        return $this->belongsTo(GrupoEmpresarial::class);
    }

    /**
     * @return HasMany<MembresiaEmpresa, $this>
     */
    public function membresias(): HasMany
    {
        return $this->hasMany(MembresiaEmpresa::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'membresias_empresa')
            ->withPivot(['estado', 'fecha_incorporacion', 'suspendida_at', 'invitado_por_user_id'])
            ->withTimestamps();
    }

    /**
     * Route binding alias for the users resource.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->usuarios();
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return HasMany<Puesto, $this>
     */
    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }

    /**
     * @return HasMany<Empleado, $this>
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    /**
     * @return HasMany<EmpleadoDocumento, $this>
     */
    public function empleadoDocumentos(): HasMany
    {
        return $this->hasMany(EmpleadoDocumento::class);
    }

    /**
     * @return BelongsToMany<Modulo, $this>
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'empresa_modulo')
            ->withPivot('habilitado')
            ->withTimestamps();
    }

    public function moduloHabilitado(string $moduleKey): bool
    {
        return $this->modulos()
            ->where('clave', $moduleKey)
            ->where('activo', true)
            ->wherePivot('habilitado', true)
            ->exists();
    }

    public function permiteAcceso(): bool
    {
        if (! $this->estado->permiteAcceso()) {
            return false;
        }

        return $this->estado !== EstadoEmpresa::Demo
            || $this->demo_ends_at === null
            || $this->demo_ends_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoEmpresa::class,
            'demo_ends_at' => 'datetime',
            'activada_at' => 'datetime',
            'vence_at' => 'datetime',
            'desactivada_at' => 'datetime',
            'retencion_hasta' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }
}
