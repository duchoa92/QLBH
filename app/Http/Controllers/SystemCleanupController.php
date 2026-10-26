<?php

namespace App\Http\Controllers;

use App\Services\SystemCleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemCleanupController extends Controller
{
    public function scan(SystemCleanupService $cleanup): JsonResponse
    {
        return response()->json($cleanup->scan());
    }

    public function destroy(Request $request, SystemCleanupService $cleanup): JsonResponse
    {
        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1', 'max:500'],
            'paths.*' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($cleanup->delete($data['paths']));
    }
}
