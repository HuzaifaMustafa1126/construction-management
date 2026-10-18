<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('roles.view')) {
            return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
        }

        return response()->json(['data' => PermissionCatalog::GROUPS]);
    }
}
