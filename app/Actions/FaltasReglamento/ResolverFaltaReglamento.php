<?php

namespace App\Actions\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Models\Empleado;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverFaltaReglamento
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function handle(
        FaltaReglamento $falta,
        User $actor,
        EstadoFaltaReglamento $estado,
        ?string $comentarioRechazo = null,
    ): FaltaReglamento {
        if (! in_array($estado, [EstadoFaltaReglamento::Autorizada, EstadoFaltaReglamento::Rechazada], true)) {
            throw new \InvalidArgumentException('El estado de resolución de la falta no es válido.');
        }

        $empresaId = $this->empresaContext->empresaRequerida()->id;

        return DB::transaction(function () use ($falta, $actor, $estado, $comentarioRechazo, $empresaId): FaltaReglamento {
            $falta = FaltaReglamento::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($falta->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($falta->estado !== EstadoFaltaReglamento::Pendiente) {
                throw ValidationException::withMessages([
                    'faltaReglamento' => 'Este reporte ya fue resuelto.',
                ]);
            }

            if ($estado === EstadoFaltaReglamento::Autorizada) {
                $empleado = Empleado::query()
                    ->withTrashed()
                    ->where('empresa_id', $empresaId)
                    ->whereKey($falta->empleado_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $catalogo = FaltaReglamentoCatalogo::withTrashed()
                    ->where('empresa_id', $empresaId)
                    ->whereKey($falta->falta_reglamento_catalogo_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $tipoFaltaId = $catalogo->tipo_falta_reglamento_id;

                DB::table('empleado_tipo_falta_reglamento')->insertOrIgnore([
                    'empresa_id' => $empresaId,
                    'empleado_id' => $empleado->id,
                    'tipo_falta_reglamento_id' => $tipoFaltaId,
                    'cantidad' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('empleado_tipo_falta_reglamento')
                    ->where('empresa_id', $empresaId)
                    ->where('empleado_id', $empleado->id)
                    ->where('tipo_falta_reglamento_id', $tipoFaltaId)
                    ->increment('cantidad', 1, ['updated_at' => now()]);
            }

            $falta->forceFill([
                'estado' => $estado,
                'resuelto_por_user_id' => $actor->id,
                'resuelto_at' => now(),
                'comentarios_rechazo' => $estado === EstadoFaltaReglamento::Rechazada ? $comentarioRechazo : null,
            ])->save();

            return $falta;
        });
    }
}
