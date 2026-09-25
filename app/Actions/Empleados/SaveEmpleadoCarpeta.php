<?php

namespace App\Actions\Empleados;

use App\Actions\Empresas\RecordCompanyAccessActivity;
use App\Models\EmpleadoCarpeta;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveEmpleadoCarpeta
{
    public function __construct(private readonly RecordCompanyAccessActivity $recordCompanyAccessActivity) {}

    /**
     * @param  array{nombre: string, user_ids?: array<mixed>}  $data
     */
    public function create(Empresa $empresa, User $creador, array $data): EmpleadoCarpeta
    {
        return DB::transaction(function () use ($empresa, $creador, $data): EmpleadoCarpeta {
            $carpeta = EmpleadoCarpeta::query()->create([
                'empresa_id' => $empresa->id,
                'creado_por_id' => $creador->id,
                'nombre' => $data['nombre'],
            ]);

            $userIds = $this->syncUsuarios($carpeta, $data['user_ids'] ?? []);
            $this->recordCompanyAccessActivity->handle(
                $empresa,
                $creador,
                $carpeta,
                'created',
                [],
                ['usuarios_con_acceso' => $userIds],
            );

            return $carpeta;
        });
    }

    /**
     * @param  array{nombre?: string, user_ids?: array<mixed>}  $data
     */
    public function update(Empresa $empresa, EmpleadoCarpeta $carpeta, User $actor, array $data): EmpleadoCarpeta
    {
        return DB::transaction(function () use ($empresa, $carpeta, $actor, $data): EmpleadoCarpeta {
            $previousUserIds = $this->usuariosConAccesoIds($carpeta);

            if (array_key_exists('nombre', $data)) {
                $carpeta->update(['nombre' => $data['nombre']]);
            }

            $userIds = array_key_exists('user_ids', $data)
                ? $this->syncUsuarios($carpeta, $data['user_ids'])
                : $previousUserIds;
            $this->recordCompanyAccessActivity->handle(
                $empresa,
                $actor,
                $carpeta,
                'updated',
                ['usuarios_con_acceso' => $previousUserIds],
                ['usuarios_con_acceso' => $userIds],
            );

            return $carpeta->refresh();
        });
    }

    /**
     * @param  array<mixed>  $userIds
     * @return list<int>
     */
    private function syncUsuarios(EmpleadoCarpeta $carpeta, array $userIds): array
    {
        $userIds = collect($userIds)
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->reject(fn (int $userId): bool => $userId === $carpeta->creado_por_id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $carpeta->usuariosConAcceso()->syncWithPivotValues(
            $userIds,
            ['empresa_id' => $carpeta->empresa_id],
        );

        return array_values($userIds);
    }

    /**
     * @return list<int>
     */
    private function usuariosConAccesoIds(EmpleadoCarpeta $carpeta): array
    {
        return array_values(
            $carpeta->usuariosConAcceso()
                ->orderBy('users.id')
                ->pluck('users.id')
                ->map(static fn (mixed $userId): int => (int) $userId)
                ->values()
                ->all(),
        );
    }
}
