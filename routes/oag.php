<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('oag.json', static function () {
    return new JsonResponse(
        data: File::get(config('oag.path')),
        json: true,
    );
})->name('oag.json');

Route::get('docs', static fn() => view(config('oag.view'), [
    'url' => route(
        config('oag.route')
    )
]))->name('oag.docs');
