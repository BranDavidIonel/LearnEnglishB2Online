<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\UnitResource;
use Illuminate\Http\Request;

/**
 * A unit card of the learning path: the unit summary plus the title of its grammar
 * (CourseMapService::learnLevels()).
 *
 * @property array<string, mixed> $resource
 */
class CourseUnitResource extends UnitResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'grammar' => $this->resource['grammar'],
        ];
    }
}
