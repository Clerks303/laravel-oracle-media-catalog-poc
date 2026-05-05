<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\ProgramResource;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index()
    {
        return ChannelResource::collection(Channel::query()->orderBy('code')->get());
    }

    public function show(Channel $channel)
    {
        return new ChannelResource($channel);
    }

    public function programs(Channel $channel, Request $request)
    {
        $programs = $channel->programs()
            ->with(['channel', 'genres'])
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 15));

        return ProgramResource::collection($programs);
    }
}
