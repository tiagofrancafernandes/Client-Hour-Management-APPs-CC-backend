<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PublicResourceController extends Controller
{
    public function timezones(Request $request): JsonResponse
    {
        return response()->json((array) config('application.date.timezones', []));
    }
}
