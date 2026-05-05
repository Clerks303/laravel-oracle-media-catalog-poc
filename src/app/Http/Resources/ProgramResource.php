<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'synopsis'     => $this->synopsis,
            'duration_min' => $this->duration_min,
            'deleted_at'   => optional($this->deleted_at)->toIso8601String(),
            'channel'      => new ChannelResource($this->whenLoaded('channel')),
            'genres'       => GenreResource::collection($this->whenLoaded('genres')),
        ];
    }
}
