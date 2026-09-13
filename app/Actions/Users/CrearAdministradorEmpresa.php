<?php

namespace App\Actions\Users;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Empresas\ManageCompanyUsers;

class CrearAdministradorEmpresa
{
    public function __construct(
        private readonly CrearRolesPredeterminadosEmpresa $crearRolesPredeterminadosEmpresa,
        private readonly ManageCompanyUsers $manageCompanyUsers,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Empresa $empresa, User $actor, array $data): void
    {
        $role = $this->crearRolesPredeterminadosEmpresa->handle($empresa);
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($empresa->id);
            $this->manageCompanyUsers->create($empresa, $actor, $data, [$role->id]);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
