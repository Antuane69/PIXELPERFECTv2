<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request): Response
    {
        $request->session()->forget(EmpresaContext::SESSION_KEY);

        if ($request->wantsJson()) {
            return $request->routeIs('two-factor.login.store')
                ? new JsonResponse(null, 204)
                : response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user->es_superadministrador_plataforma
            ? to_route('dashboard')
            : to_route('empresa-contexto.create');
    }
}
