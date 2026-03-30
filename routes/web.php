<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DebugConfigController;
use Illuminate\Http\Request;

Route::get('/', fn (Request $request) => DebugConfigController::rootPathInfo($request, __FILE__ . ':' . __LINE__));

require __DIR__ . '/auth.php';
