<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ZeroToProd\JsonApi\Http\Controllers\VersionController;

Route::get('/version', VersionController::class)->name('jsonapi.version');
