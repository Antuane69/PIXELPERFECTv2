<?php

namespace App\Http\Middleware;

use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private EmpresaContext $empresaContext) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->userData($request->user()),
            ],
            'empresas' => [
                'activa' => $this->empresaData($this->empresaContext->empresa()),
                'disponibles' => $this->empresasDisponibles($request->user()),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userData(?Authenticatable $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $user->loadMissing(['roles.permissions', 'permissions']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $this->avatarDataUri($user),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'es_superadministrador_plataforma' => $user->es_superadministrador_plataforma,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'permissions' => $user->es_superadministrador_plataforma
                ? ['*']
                : $user->getAllPermissions()
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function empresaData(?Empresa $empresa): ?array
    {
        if (! $empresa instanceof Empresa) {
            return null;
        }

        return [
            'id' => $empresa->id,
            'nombre' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'nombre_legal' => $empresa->nombre_legal,
            'slug' => $empresa->slug,
            'estado' => $empresa->estado->value,
            'grupo' => $empresa->grupoEmpresarial === null ? null : [
                'id' => $empresa->grupoEmpresarial->id,
                'nombre' => $empresa->grupoEmpresarial->nombre,
                'slug' => $empresa->grupoEmpresarial->slug,
            ],
            'modulos' => $empresa->modulos()
                ->where('activo', true)
                ->wherePivot('habilitado', true)
                ->orderBy('orden')
                ->pluck('clave')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function empresasDisponibles(?Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $empresas = $user->es_superadministrador_plataforma
            ? Empresa::query()
            : $user->empresas()->wherePivot('estado', EstadoMembresiaEmpresa::Activa->value);

        return array_values($empresas
            ->select([
                'empresas.id',
                'empresas.nombre_legal',
                'empresas.nombre_comercial',
                'empresas.slug',
                'empresas.estado',
                'empresas.demo_ends_at',
            ])
            ->orderBy('empresas.nombre_legal')
            ->get()
            ->map(fn (Empresa $empresa): array => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
                'nombre_legal' => $empresa->nombre_legal,
                'slug' => $empresa->slug,
                'estado' => $empresa->estado->value,
                'puede_acceder' => $user->es_superadministrador_plataforma || $empresa->permiteAcceso(),
            ])
            ->all());
    }

    private function avatarDataUri(User $user): ?string
    {
        if ($user->avatar === null || $user->avatar_mime_type === null) {
            return null;
        }

        return "data:{$user->avatar_mime_type};base64,".base64_encode($user->avatar);
    }
}
