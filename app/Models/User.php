<?php

namespace App\Models;

use App\Jobs\SendEmailVerificationEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property string|null $avatar_mime_type
 * @property Carbon|null $email_verified_at
 * @property bool $es_superadministrador_plataforma
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'avatar', 'avatar_mime_type', 'es_superadministrador_plataforma'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, MustVerifyEmailTrait, Notifiable, TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    /**
     * @return HasMany<MembresiaEmpresa, $this>
     */
    public function membresiasEmpresa(): HasMany
    {
        return $this->hasMany(MembresiaEmpresa::class);
    }

    /**
     * @return HasMany<EmpleadoCarpeta, $this>
     */
    public function empleadoCarpetasCreadas(): HasMany
    {
        return $this->hasMany(EmpleadoCarpeta::class, 'creado_por_id');
    }

    /**
     * @return BelongsToMany<EmpleadoCarpeta, $this>
     */
    public function empleadoCarpetasConAcceso(): BelongsToMany
    {
        return $this->belongsToMany(
            EmpleadoCarpeta::class,
            'empleados_carpetas_usuarios',
            'user_id',
            'empleado_carpeta_id',
        )->withPivot('empresa_id')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Empresa, $this>
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'membresias_empresa')
            ->withPivot(['estado', 'fecha_incorporacion', 'suspendida_at', 'invitado_por_user_id'])
            ->withTimestamps();
    }

    /**
     * Queue the branded verification email.
     */
    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ],
        );

        SendEmailVerificationEmail::dispatch(
            name: $this->name,
            email: $this->getEmailForVerification(),
            verificationUrl: $verificationUrl,
        )
            ->onQueue((string) config('mail.auth.queue'))
            ->afterCommit();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('usuarios')
            ->logOnly(['name', 'email', 'email_verified_at', 'avatar_mime_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => Str::squish($value),
        );
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'es_superadministrador_plataforma' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
