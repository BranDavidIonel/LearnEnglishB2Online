<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A float saved to the last digit. A float bound to a query is turned into text with PHP's
 * "precision" (14 digits), so 0.9166666666666666 would be saved as 0.91666666666667;
 * written out in full here, scores keep every digit, as in the original progress file.
 *
 * @implements CastsAttributes<float|null, float|int|string|null>
 */
class ExactFloat implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : var_export((float) $value, true);
    }
}
