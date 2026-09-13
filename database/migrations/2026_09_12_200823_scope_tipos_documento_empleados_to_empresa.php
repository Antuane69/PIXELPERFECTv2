<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('tipo_documento_empleados')->whereNull('empresa_id')->exists()) {
            throw new RuntimeException('Existen tipos de documento sin empresa; no puede completarse el aislamiento empresarial.');
        }

        Schema::table('tipo_documento_empleados', function (Blueprint $table): void {
            $table->dropUnique(['nombre']);
            $table->unique(['empresa_id', 'nombre']);
            $table->unique(['empresa_id', 'id'], 'tipo_documento_empresa_id_id_unique');
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
        });

        Schema::table('empleado_documentos', function (Blueprint $table): void {
            $table->dropForeign(['tipo_documento_empleado_id']);
            $table->foreign(
                ['empresa_id', 'tipo_documento_empleado_id'],
                'empleado_documentos_empresa_tipo_foreign',
            )
                ->references(['empresa_id', 'id'])
                ->on('tipo_documento_empleados')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasForeignKey('empleado_documentos', 'empleado_documentos_empresa_tipo_foreign')) {
            Schema::table('empleado_documentos', function (Blueprint $table): void {
                $table->dropForeign('empleado_documentos_empresa_tipo_foreign');
            });
        }

        if (Schema::hasForeignKey('empleado_documentos', 'empleado_documentos_tipo_documento_empleado_id_foreign')) {
            Schema::table('empleado_documentos', function (Blueprint $table): void {
                $table->dropForeign('empleado_documentos_tipo_documento_empleado_id_foreign');
            });
        }

        if (Schema::hasIndex('empleado_documentos', 'empleado_documentos_empresa_tipo_foreign')) {
            Schema::table('empleado_documentos', function (Blueprint $table): void {
                $table->dropIndex('empleado_documentos_empresa_tipo_foreign');
            });
        }

        Schema::table('empleado_documentos', function (Blueprint $table): void {
            $table->foreign('tipo_documento_empleado_id')
                ->references('id')
                ->on('tipo_documento_empleados')
                ->cascadeOnDelete();
        });

        DB::table('tipo_documento_empleados')
            ->select(['id', 'nombre'])
            ->orderBy('id')
            ->get()
            ->groupBy('nombre')
            ->each(function ($documentTypes): void {
                $keptId = (int) $documentTypes->first()->id;
                $duplicateIds = $documentTypes->skip(1)->pluck('id');

                if ($duplicateIds->isEmpty()) {
                    return;
                }

                DB::table('empleado_documentos')
                    ->whereIn('tipo_documento_empleado_id', $duplicateIds)
                    ->update(['tipo_documento_empleado_id' => $keptId]);
                DB::table('tipo_documento_empleados')->whereIn('id', $duplicateIds)->delete();
            });

        if (! Schema::hasIndex('tipo_documento_empleados', ['empresa_id'])) {
            Schema::table('tipo_documento_empleados', function (Blueprint $table): void {
                $table->index('empresa_id');
            });
        }

        if (Schema::hasIndex('tipo_documento_empleados', 'tipo_documento_empresa_id_id_unique')) {
            Schema::table('tipo_documento_empleados', function (Blueprint $table): void {
                $table->dropUnique('tipo_documento_empresa_id_id_unique');
            });
        }

        if (Schema::hasIndex('tipo_documento_empleados', 'tipo_documento_empleados_empresa_id_nombre_unique')) {
            Schema::table('tipo_documento_empleados', function (Blueprint $table): void {
                $table->dropUnique('tipo_documento_empleados_empresa_id_nombre_unique');
            });
        }

        Schema::table('tipo_documento_empleados', function (Blueprint $table): void {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
        });

        if (! Schema::hasIndex('tipo_documento_empleados', ['nombre'], 'unique')) {
            Schema::table('tipo_documento_empleados', function (Blueprint $table): void {
                $table->unique('nombre');
            });
        }
    }
};
