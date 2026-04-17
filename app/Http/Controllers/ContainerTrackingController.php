<?php

namespace App\Http\Controllers;

use App\Services\ShipsGoService;
use Illuminate\Http\JsonResponse;

class ContainerTrackingController extends Controller
{
    public function __invoke(string $container, ShipsGoService $shipsGo): JsonResponse
    {
        $tracking = $shipsGo->trackContainer($container);

        if (isset($tracking['error'])) {
            return response()->json([
                'data'    => null,
                'message' => $tracking['error'],
            ], 503);
        }

        return response()->json([
            'data'    => $tracking,
            'message' => 'success',
        ]);
    }
}
