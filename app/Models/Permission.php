<?php

namespace App\Models;

use App\AlcancePermiso;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property AlcancePermiso $alcance
 * @property int|null $modulo_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'guard_name', 'alcance', 'modulo_id'])]
class Permission extends SpatiePermission
{
    protected $attributes = [
        'guard_name' => 'web',
        'alcance' => AlcancePermiso::Empresa->value,
    ];

    /** @return BelongsTo<Modulo, $this> */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'alcance' => AlcancePermiso::class,
            'modulo_id' => 'integer',
        ];
    }
}
