<?php

namespace App\Policies\Vacaciones;

use App\Models\DiaFestivo;
use App\Models\User;

class DiaFestivoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('vacaciones.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('vacaciones.manage_holidays');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DiaFestivo $diaFestivo): bool
    {
        return $user->can('vacaciones.manage_holidays')
            && $this->belongsToActiveCompany($diaFestivo);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DiaFestivo $diaFestivo): bool
    {
        return $user->can('vacaciones.manage_holidays')
            && $this->belongsToActiveCompany($diaFestivo);
    }

    private function belongsToActiveCompany(DiaFestivo $diaFestivo): bool
    {
        return (int) getPermissionsTeamId() === $diaFestivo->empresa_id;
    }
}
