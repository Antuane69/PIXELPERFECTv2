<?php

namespace App\Policies;

use App\Models\EmpleadoCarpeta;
use App\Models\EmpleadoDocumentoCatalogo;
use App\Models\User;

class EmpleadoDocumentoCatalogoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('empleados_documentos_catalogo.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        return $user->can('empleados_documentos_catalogo.view')
            && $this->canAccessFolder($user, $documento);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('empleados_documentos_catalogo.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        return $user->can('empleados_documentos_catalogo.update')
            && $this->canAccessFolder($user, $documento);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        return $user->can('empleados_documentos_catalogo.delete')
            && $this->canAccessFolder($user, $documento);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        return $user->can('empleados_documentos_catalogo.update')
            && $this->canAccessFolder($user, $documento);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        return false;
    }

    public function download(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        return $this->view($user, $documento);
    }

    public function createInFolder(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $user->can('empleados_documentos_catalogo.create')
            && $this->folderIsInActiveCompany($carpeta)
            && $this->userCanAccessFolder($user, $carpeta);
    }

    public function moveToFolder(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $user->can('empleados_documentos_catalogo.update')
            && $this->folderIsInActiveCompany($carpeta)
            && $this->userCanAccessFolder($user, $carpeta);
    }

    private function canAccessFolder(User $user, EmpleadoDocumentoCatalogo $documento): bool
    {
        $carpeta = EmpleadoCarpeta::query()
            ->where('empresa_id', $documento->empresa_id)
            ->find($documento->empleado_carpeta_id);

        return $carpeta instanceof EmpleadoCarpeta
            && $carpeta->empresa_id === $documento->empresa_id
            && $this->folderIsInActiveCompany($carpeta)
            && $this->userCanAccessFolder($user, $carpeta);
    }

    private function folderIsInActiveCompany(EmpleadoCarpeta $carpeta): bool
    {
        return (int) getPermissionsTeamId() === $carpeta->empresa_id;
    }

    private function userCanAccessFolder(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $carpeta->creado_por_id === $user->id
            || $carpeta->usuariosConAcceso()->whereKey($user->id)->exists();
    }
}
