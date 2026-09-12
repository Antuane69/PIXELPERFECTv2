<?php

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Puesto;
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
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table): void {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
            });

        $activityTable = (string) config('activitylog.table_name');
        $connection = DB::connection(config('activitylog.database_connection'));

        foreach ([
            Puesto::class => 'puestos',
            Empleado::class => 'empleados',
            EmpleadoDocumento::class => 'empleado_documentos',
        ] as $subjectType => $subjectTable) {
            $activities = $connection->table($activityTable)
                ->select(['id', 'subject_id'])
                ->where('subject_type', $subjectType)
                ->whereNull('empresa_id')
                ->get();
            $companyIds = DB::table($subjectTable)
                ->whereIn('id', $activities->pluck('subject_id'))
                ->pluck('empresa_id', 'id');

            foreach ($activities as $activity) {
                $empresaId = $companyIds->get($activity->subject_id);

                if (! is_int($empresaId) && ! (is_string($empresaId) && ctype_digit($empresaId))) {
                    continue;
                }

                $normalizedEmpresaId = (int) $empresaId;

                $connection->table($activityTable)
                    ->where('id', $activity->id)
                    ->update(['empresa_id' => $normalizedEmpresaId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table): void {
                $table->dropConstrainedForeignId('empresa_id');
            });
    }
};
