<?php

namespace App\Models\FaltasReglamento;

use App\Models\Empresa;
use Database\Factories\FaltasReglamento\FaltaReglamentoEvidenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'falta_reglamento_id', 'nombre', 'mime_type', 'file_extension', 'archivo'])]
#[Hidden(['archivo'])]
class FaltaReglamentoEvidencia extends Model
{
    /** @use HasFactory<FaltaReglamentoEvidenciaFactory> */
    use HasFactory;

    protected $table = 'faltas_reglamento_evidencias';

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<FaltaReglamento, $this> */
    public function faltaReglamento(): BelongsTo
    {
        return $this->belongsTo(FaltaReglamento::class, 'falta_reglamento_id');
    }
}
