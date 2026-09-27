<?php

namespace App\Models;

use Database\Factories\DiaFestivoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property string $nombre
 * @property Carbon $fecha
 */
#[Fillable(['empresa_id', 'nombre', 'fecha'])]
class DiaFestivo extends Model
{
    /** @use HasFactory<DiaFestivoFactory> */
    use HasFactory;

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }
}
