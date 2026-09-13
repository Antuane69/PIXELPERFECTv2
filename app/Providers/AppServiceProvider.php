<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\ModuloPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Services\Empresas\EmpresaContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogFolder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();
        $this->app->scoped(EmpresaContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Empresa::class);

        Gate::before(static function (User $user): ?bool {
            return $user->es_superadministrador_plataforma ? true : null;
        });

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Modulo::class, ModuloPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::define(
            'viewLogViewer',
            static fn (User $user): bool => $user->es_superadministrador_plataforma,
        );
        Gate::define(
            'downloadLogFile',
            static fn (User $user, LogFile $file): bool => $user->es_superadministrador_plataforma,
        );
        Gate::define(
            'downloadLogFolder',
            static fn (User $user, LogFolder $folder): bool => $user->es_superadministrador_plataforma,
        );
        Gate::define(
            'deleteLogFile',
            static fn (User $user, LogFile $file): bool => $user->es_superadministrador_plataforma,
        );
        Gate::define(
            'deleteLogFolder',
            static fn (User $user, LogFolder $folder): bool => $user->es_superadministrador_plataforma,
        );
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $password = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->rules(['regex:/[\p{P}\p{S}]/u']);

            return app()->isProduction()
                ? $password->uncompromised()
                : $password;
        });
    }
}
