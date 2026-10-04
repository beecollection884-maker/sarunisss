<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('debug', function () {
    return [
        'is_frontend' => EnsureFrontendRequestsAreStateful::fromFrontend(request()),
        'headers' => request()->headers->all(),
        'host' => request()->getHost(),
        'port' => request()->getPort(),
        'stateful_domains' => config('sanctum.stateful'),
    ];
});
