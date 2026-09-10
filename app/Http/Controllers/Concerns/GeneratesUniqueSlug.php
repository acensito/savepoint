<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

/**
 * Antes duplicado byte a byte entre PlatformController y ManufacturerController
 * (issue #188, auditoría de mantenibilidad del 2026-09-10). EditionController
 * no lo necesita: sus ediciones no tienen columna slug.
 */
trait GeneratesUniqueSlug
{
    /**
     * @param  class-string  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            $modelClass::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
