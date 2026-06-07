<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $referralService) {}

    /**
     * GET /api/referral — Stats et code parrain de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $stats = $this->referralService->getStats($request->user());

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * POST /api/referral/validate — Vérifier qu'un code est valide (avant inscription)
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:12']);

        $referrer = $this->referralService->validateCode($request->input('code'));

        if (!$referrer) {
            return response()->json([
                'success' => false,
                'message' => 'Code de parrainage invalide ou expiré.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'valid'         => true,
                'referrer_name' => $referrer->first_name,
                'gift'          => '1 Streak Freeze offert à l\'inscription',
            ],
        ]);
    }
}
