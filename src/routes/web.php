<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app'    => config('app.name'),
    'health' => '/up',
    'api'    => '/api/v1/channels',
]));
