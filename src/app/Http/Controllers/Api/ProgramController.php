<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::query()
            ->with(['channel', 'genres'])
            ->search($request->query('search'))
            ->ofGenre($request->query('genre'))
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 15));

        return ProgramResource::collection($programs);
    }

    public function show(Program $program)
    {
        return new ProgramResource($program->load(['channel', 'genres']));
    }

    public function destroy(Program $program)
    {
        $futureCount = $program->broadcasts()
            ->where('scheduled_at', '>=', now())
            ->count();

        if ($futureCount > 0) {
            throw ValidationException::withMessages([
                'program' => "Cannot delete program with {$futureCount} future broadcast(s) scheduled. Cancel or reschedule them first.",
            ]);
        }

        $program->delete();
        return response()->noContent();
    }

    public function restore(int $id)
    {
        $program = Program::onlyTrashed()->findOrFail($id);
        $program->restore();
        return new ProgramResource($program->load(['channel', 'genres']));
    }
}
