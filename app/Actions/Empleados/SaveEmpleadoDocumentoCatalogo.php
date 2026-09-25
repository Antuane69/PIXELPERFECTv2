<?php

namespace App\Actions\Empleados;

use App\Models\EmpleadoDocumentoCatalogo;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Empleados\ModulosRelacionablesDocumentoCatalogo;
use App\Services\Empleados\SanitizeEmpleadoDocumentoHtml;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SaveEmpleadoDocumentoCatalogo
{
    public function __construct(
        private readonly SanitizeEmpleadoDocumentoHtml $sanitizeHtml,
        private readonly ModulosRelacionablesDocumentoCatalogo $modulosRelacionables,
    ) {}

    /** @param array{nombre: string, empleado_carpeta_id: int, contenido_html: string, modulo_ids: list<int>} $data */
    public function create(Empresa $empresa, User $actor, array $data): EmpleadoDocumentoCatalogo
    {
        return DB::transaction(function () use ($empresa, $actor, $data): EmpleadoDocumentoCatalogo {
            $documento = EmpleadoDocumentoCatalogo::query()->create([
                'empresa_id' => $empresa->id,
                'empleado_carpeta_id' => $data['empleado_carpeta_id'],
                'nombre' => $data['nombre'],
                'contenido_html' => $this->sanitizeHtml->handle($data['contenido_html']),
            ]);
            $documento->modulos()->syncWithPivotValues($data['modulo_ids'], ['empresa_id' => $empresa->id]);
            $this->recordModuleChanges($empresa, $actor, $documento, [], $this->moduleIds($documento), 'created');

            return $documento;
        });
    }

    /** @param array{nombre: string, empleado_carpeta_id: int, contenido_html: string, modulo_ids: list<int>} $data */
    public function update(
        Empresa $empresa,
        EmpleadoDocumentoCatalogo $documento,
        User $actor,
        array $data,
    ): EmpleadoDocumentoCatalogo {
        return DB::transaction(function () use ($empresa, $documento, $actor, $data): EmpleadoDocumentoCatalogo {
            $previousModuleIds = $this->moduleIds($documento);
            $availableModuleIds = $this->availableModuleIds($actor, $empresa);
            $preservedModuleIds = array_diff($previousModuleIds, $availableModuleIds);
            $selectedModuleIds = array_values(array_intersect($data['modulo_ids'], $availableModuleIds));
            $moduleIds = array_values(array_unique([
                ...$selectedModuleIds,
                ...$preservedModuleIds,
            ]));
            sort($moduleIds);

            $documento->update([
                'empleado_carpeta_id' => $data['empleado_carpeta_id'],
                'nombre' => $data['nombre'],
                'contenido_html' => $this->sanitizeHtml->handle($data['contenido_html']),
            ]);
            $documento->modulos()->syncWithPivotValues($moduleIds, ['empresa_id' => $empresa->id]);
            $this->recordModuleChanges(
                $empresa,
                $actor,
                $documento,
                $previousModuleIds,
                $this->moduleIds($documento),
                'updated',
            );

            return $documento->refresh();
        });
    }

    /** @return list<int> */
    private function moduleIds(EmpleadoDocumentoCatalogo $documento): array
    {
        return array_values($documento->modulos()
            ->orderBy('modulos.id')
            ->pluck('modulos.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }

    /** @return list<int> */
    private function availableModuleIds(User $user, Empresa $empresa): array
    {
        return array_values($this->modulosRelacionables->query($user, $empresa)
            ->pluck('modulos.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }

    /** @param list<int> $previousModuleIds
     * @param  list<int>  $moduleIds
     */
    private function recordModuleChanges(
        Empresa $empresa,
        User $actor,
        EmpleadoDocumentoCatalogo $documento,
        array $previousModuleIds,
        array $moduleIds,
        string $event,
    ): void {
        if ($previousModuleIds === $moduleIds) {
            return;
        }

        activity('empleados_documentos_catalogo')
            ->causedBy($actor)
            ->performedOn($documento)
            ->event('module_relations_'.$event)
            ->withProperties([
                'old' => ['modulo_ids' => $previousModuleIds],
                'attributes' => ['modulo_ids' => $moduleIds],
            ])
            ->tap(static function (Activity $activity) use ($empresa): void {
                $activity->setAttribute('empresa_id', $empresa->id);
            })
            ->log('Módulos relacionados del documento actualizados');
    }
}
