<?php

namespace App\Services\Platform;

use App\Models\Modulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageModules
{
    /** @param array<string, mixed> $data */
    public function save(array $data, ?Modulo $module = null): Modulo
    {
        return DB::transaction(function () use ($data, $module): Modulo {
            if ($module instanceof Modulo) {
                $module = Modulo::query()->lockForUpdate()->findOrFail($module->id);
                $module->update($data);

                return $module;
            }

            return Modulo::query()->create($data);
        });
    }

    public function delete(Modulo $module): void
    {
        DB::transaction(function () use ($module): void {
            $module = Modulo::query()->lockForUpdate()->findOrFail($module->id);

            if ($module->empresas()->exists()) {
                throw ValidationException::withMessages([
                    'modulo' => 'No puedes eliminar módulo asignado a empresas. Desasígnalo primero.',
                ]);
            }

            if ($module->permisos()->exists()) {
                throw ValidationException::withMessages([
                    'modulo' => 'No puedes eliminar módulo con permisos asociados.',
                ]);
            }

            if ($module->documentosCatalogo()->exists()) {
                throw ValidationException::withMessages([
                    'modulo' => 'No puedes eliminar módulo relacionado con documentos. Desvincúlalo primero.',
                ]);
            }

            $module->delete();
        });
    }
}
