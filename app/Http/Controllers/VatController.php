<?php

namespace App\Http\Controllers;

use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VatController extends Controller
{
    public function __construct(private VatValidationService $vatService) {}

    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'vat_number' => ['required', 'string', 'min:4', 'max:20'],
        ]);

        $result = $this->vatService->validate($request->vat_number);

        return response()->json(['data' => $result]);
    }
}
