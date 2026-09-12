<?php

namespace App\Actions\Empresas;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class RecordCompanyAccessActivity
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function handle(Empresa $empresa, User $actor, Model $subject, string $event, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        activity('accesos_empresa')
            ->causedBy($actor)
            ->performedOn($subject)
            ->event($event)
            ->withProperties(['old' => $before, 'attributes' => $after])
            ->tap(function (Activity $activity) use ($empresa): void {
                $activity->setAttribute('empresa_id', $empresa->id);
            })
            ->log('Acceso empresarial actualizado');
    }
}
