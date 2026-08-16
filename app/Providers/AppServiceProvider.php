<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogFolder;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::define(
            'viewLogViewer',
            static fn (User $user): bool => $user->can('logs.view'),
        );
        Gate::define(
            'downloadLogFile',
            static fn (User $user, LogFile $file): bool => $user->can('logs.view'),
        );
        Gate::define(
            'downloadLogFolder',
            static fn (User $user, LogFolder $folder): bool => $user->can('logs.view'),
        );
        Gate::define(
            'deleteLogFile',
            static fn (User $user, LogFile $file): bool => $user->can('logs.delete'),
        );
        Gate::define(
            'deleteLogFolder',
            static fn (User $user, LogFolder $folder): bool => $user->can('logs.delete'),
        );
        Gate::before(
            static fn (User $user, string $ability): ?bool => $user->hasRole('Administrador', 'web') ? true : null,
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
