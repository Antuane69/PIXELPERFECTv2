<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int|null $empresa_id
 * @property string $name
 */
class Role extends SpatieRole
{
    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function esProtegido(): bool
    {
        return $this->name === 'Administrador';
    }
}
