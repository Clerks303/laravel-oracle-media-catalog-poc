<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Defence-in-depth on broadcast overlaps: the API rule
        // (BroadcastNonOverlapping) catches it as 422; if a write slips past
        // the rule (race, direct SQL, batch job), the Oracle compound trigger
        // raises ORA-20010. Translate that to the same 422 payload here so
        // the HTTP contract stays consistent.
        $exceptions->render(function (\PDOException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }
            if (! str_contains($e->getMessage(), 'ORA-20010')) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'scheduled_at' => [
                        'This broadcast overlaps an existing one on the same channel.',
                    ],
                ],
            ], 422);
        });
    })
    ->create();
