<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\LevelResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The "road to B2" card of the home and progress pages (DashboardService::road()):
 * the level reached, the level being worked on, every level with its state, and the
 * progress and vocabulary towards B1 and B2.
 *
 * @property array<string, mixed> $resource
 */
class RoadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $road = $this->resource;

        return [
            'achieved' => $road['achieved'],
            'current' => $road['current'],
            'levels' => LevelResource::collection($road['levels']),
            'goals' => $road['goals'],
        ];
    }
}
