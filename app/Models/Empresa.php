<?php

namespace App\Models;

use App\EstadoEmpresa;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\PermisosLaborales\TipoPermiso;
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
 * @property string|null $rfc
 * @property string|null $logo
 * @property string|null $logo_mime_type
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
    'codigo_pais_contacto',
    'telefono_contacto',
    'logo',
    'logo_mime_type',
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
        if ($this->telefono_contacto === null) {
            return null;
        }

        return $this->codigo_pais_contacto === null
            ? $this->telefono_contacto
            : '+'.$this->codigo_pais_contacto.$this->telefono_contacto;
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
     * @return HasMany<EmpleadoCarpeta, $this>
     */
    public function empleadoCarpetas(): HasMany
    {
        return $this->hasMany(EmpleadoCarpeta::class);
    }

    /**
     * @return HasMany<EmpleadoDocumentoCatalogo, $this>
     */
    public function empleadoDocumentosCatalogo(): HasMany
    {
        return $this->hasMany(EmpleadoDocumentoCatalogo::class, 'empresa_id');
    }

    /**
     * @return HasMany<Empleado, $this>
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    /** @return HasMany<Vacacion, $this> */
    public function vacaciones(): HasMany
    {
        return $this->hasMany(Vacacion::class);
    }

    /** @return HasMany<PermisoLaboral, $this> */
    public function permisosLaborales(): HasMany
    {
        return $this->hasMany(PermisoLaboral::class, 'empresa_id');
    }

    /** @return HasMany<Incapacidad, $this> */
    public function incapacidades(): HasMany
    {
        return $this->hasMany(Incapacidad::class, 'empresa_id');
    }

    /** @return HasMany<TipoPermiso, $this> */
    public function tiposPermisos(): HasMany
    {
        return $this->hasMany(TipoPermiso::class, 'empresa_id');
    }

    /** @return HasMany<DiaFestivo, $this> */
    public function diasFestivos(): HasMany
    {
        return $this->hasMany(DiaFestivo::class);
    }

    /**
     * @return HasMany<EmpleadoDocumento, $this>
     */
    public function empleadoDocumentos(): HasMany
    {
        return $this->hasMany(EmpleadoDocumento::class);
    }

    /**
     * @return HasMany<TipoDocumentoEmpleado, $this>
     */
    public function tiposDocumentoEmpleado(): HasMany
    {
        return $this->hasMany(TipoDocumentoEmpleado::class);
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
