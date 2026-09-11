<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

/**
 * Antes duplicado byte a byte entre PlatformController y ManufacturerController
 * (issue #188, auditoría de mantenibilidad del 2026-09-10). EditionController
 * no lo necesita: sus ediciones no tienen columna slug.
 *
 * $userId (issue #175): el catálogo pasó de compartido a por cuenta, así que
 * la unicidad del slug también se comprueba solo dentro del catálogo de ese
 * usuario, no contra el de todos.
 */
trait GeneratesUniqueSlug
{
    /**
     * @param  class-string  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name, int $userId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            $modelClass::where('user_id', $userId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
