<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBroadcastRequest;
use App\Http\Requests\UpdateBroadcastRequest;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function index(Request $request)
    {
        $broadcasts = Broadcast::query()
            ->with([
                'program' => fn ($q) => $q->withTrashed(),
                'program.channel',
                'channel',
            ])
            ->between($request->query('from'), $request->query('to'))
            ->orderBy('scheduled_at')
            ->paginate((int) $request->query('per_page', 30));

        return BroadcastResource::collection($broadcasts);
    }

    public function show(Broadcast $broadcast)
    {
        return new BroadcastResource($broadcast->load([
            'program' => fn ($q) => $q->withTrashed(),
            'program.channel',
            'channel',
        ]));
    }

    public function store(StoreBroadcastRequest $request)
    {
        $broadcast = Broadcast::create($request->validated());
        return (new BroadcastResource($broadcast->load(['program', 'channel'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBroadcastRequest $request, Broadcast $broadcast)
    {
        $broadcast->update($request->validated());
        return new BroadcastResource($broadcast->fresh(['program', 'channel']));
    }

    public function destroy(Broadcast $broadcast)
    {
        $broadcast->delete();
        return response()->noContent();
    }
}
