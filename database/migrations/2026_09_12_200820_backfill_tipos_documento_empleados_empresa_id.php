<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companyIds = DB::table('empresas')->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id);
        $maximumOriginalId = DB::table('tipo_documento_empleados')->max('id');

        if ($maximumOriginalId === null) {
            return;
        }

        if ($companyIds->isEmpty()) {
            throw new RuntimeException('No existen empresas para asignar los tipos de documento existentes.');
        }

        DB::table('tipo_documento_empleados')
            ->where('id', '<=', $maximumOriginalId)
            ->orderBy('id')
            ->chunkById(200, function ($documentTypes) use ($companyIds): void {
                foreach ($documentTypes as $documentType) {
                    $originalId = (int) $documentType->id;
                    $firstCompanyId = $companyIds->first();

                    DB::table('tipo_documento_empleados')
                        ->where('id', $originalId)
                        ->update(['empresa_id' => $firstCompanyId]);

                    foreach ($companyIds as $companyId) {
                        $companyDocumentTypeId = $originalId;

                        if ($companyId !== $firstCompanyId) {
                            $companyDocumentTypeId = DB::table('tipo_documento_empleados')->insertGetId([
                                'empresa_id' => $companyId,
                                'nombre' => $documentType->nombre,
                                'es_renovable' => $documentType->es_renovable,
                                'frecuencia_cantidad' => $documentType->frecuencia_cantidad,
                                'frecuencia_tipo' => $documentType->frecuencia_tipo,
                                'documentos_aceptados' => $documentType->documentos_aceptados,
                                'activo' => $documentType->activo,
                                'created_at' => $documentType->created_at,
                                'updated_at' => $documentType->updated_at,
                                'deleted_at' => $documentType->deleted_at,
                            ]);
                        }

                        DB::table('empleado_documentos')
                            ->where('tipo_documento_empleado_id', $originalId)
                            ->where('empresa_id', $companyId)
                            ->update(['tipo_documento_empleado_id' => $companyDocumentTypeId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tipo_documento_empleados')->update(['empresa_id' => null]);
    }
};
