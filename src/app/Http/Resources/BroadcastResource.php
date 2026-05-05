<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'scheduled_at' => optional($this->scheduled_at)->toIso8601String(),
            'replay_until' => optional($this->replay_until)->toIso8601String(),
            'program'      => new ProgramResource($this->whenLoaded('program')),
            'channel'      => new ChannelResource($this->whenLoaded('channel')),
        ];
    }
}
