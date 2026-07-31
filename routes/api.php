<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ZeroToProd\JsonApi\Http\Controllers\SchemaController;

Route::get('/schema', SchemaController::class)->name('jsonapi.schema');
