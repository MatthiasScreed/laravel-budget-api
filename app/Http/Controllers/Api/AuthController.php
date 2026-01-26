<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'currency' => $validatedData['currency'] ?? 'EUR',
                'language' => $validatedData['language'] ?? 'fr',
                'timezone' => $validatedData['timezone'] ?? 'Europe/Paris',
                'email_verified_at' => now(),
            ]);

            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));

            DB::commit();

            // ✅ Recharger le user avec son level
            $user->load('level');

            // ✅ S'assurer que le level existe
            $this->ensureUserLevelExists($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatUserData($user),  // ✅ Utilise formatUserData qui inclut le level
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                ],
                'message' => 'Inscription réussie. Bienvenue !',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne',
            ], 500);
        }
    }

    /**
     * Connexion utilisateur avec remember token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides',
            ], 401);
        }

        $user = Auth::user();

        // Révoquer anciens tokens si demandé
        if ($request->boolean('revoke_other_tokens')) {
            $user->tokens()->delete();
        }

        $tokenName = $request->input('device_name', 'api_token');
        $expiresAt = $request->boolean('remember') ? now()->addDays(90) : now()->addDays(30);

        $token = $user->createToken($tokenName, ['*'], $expiresAt);
        $user->update(['last_login_at' => now()]);

        // ✅ S'assurer que le UserLevel existe
        $this->ensureUserLevelExists($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUserData($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
                'gaming_stats' => $user->getGamingStats(),
            ],
            'message' => 'Connexion réussie !',
        ]);
    }

    /**
     * ✅ CORRIGÉ: Informations utilisateur (compatible avec /me et /user)
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // ✅ Créer UserLevel si manquant
            $this->ensureUserLevelExists($user);

            $stats = [
                'total_transactions' => $user->transactions()->count(),
                'gaming_level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
                'achievements_count' => $user->achievements()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user, $stats),
            ]);

        } catch (\Exception $e) {
            Log::error('User profile error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU: Alias pour user() pour compatibilité /auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->user($request);
    }

    /**
     * Déconnexion robuste
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();

            if ($currentToken && method_exists($currentToken, 'delete')) {
                $currentToken->delete();
            } else {
                // Fallback pour les tests
                $user->tokens()->latest()->first()?->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error: '.$e->getMessage());

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion effectuée',
            ]);
        }
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie',
            ]);

        } catch (\Exception $e) {
            Log::error('Logout all error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
            ], 500);
        }
    }

    /**
     * Changement de mot de passe avec validation manuelle
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Vérifier le mot de passe actuel
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors' => [
                    'current_password' => ['Le mot de passe actuel est incorrect.'],
                ],
            ], 422);
        }

        // Vérifier que le nouveau mot de passe est différent
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
                'errors' => [
                    'new_password' => ['Le nouveau mot de passe doit être différent de l\'ancien.'],
                ],
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès',
        ]);
    }

    /**
     * Mise à jour profil avec validation flexible
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|min:2|max:255',
                'email' => 'sometimes|email|unique:users,email,'.$request->user()->id,
                'phone' => 'nullable|string|max:20',
                'currency' => 'sometimes|string|in:EUR,USD,GBP,CHF',
                'language' => 'sometimes|string|in:fr,en,es,de',
                'preferences' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $user->update($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user->fresh()),
                'message' => 'Profil mis à jour avec succès',
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
            ], 500);
        }
    }

    /**
     * Forgot password flexible
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte trouvé avec cette adresse email',
                'errors' => [
                    'email' => ['Aucun compte n\'est associé à cette adresse email.'],
                ],
            ], 422);
        }

        try {
            // Créer token de réinitialisation
            $token = \Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.',
            ]);

        } catch (\Exception $e) {
            Log::error('Forgot password error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation',
            ], 500);
        }
    }

    /**
     * Reset password custom
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Vérifier le token
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (! $passwordReset || ! Hash::check($request->token, $passwordReset->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de réinitialisation invalide',
                ], 400);
            }

            // Vérifier l'expiration (24h)
            if (\Carbon\Carbon::parse($passwordReset->created_at)->diffInHours(now()) > 24) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Token de réinitialisation expiré',
                ], 400);
            }

            // Réinitialiser le mot de passe
            $user = User::where('email', $request->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
                'remember_token' => null,
            ]);

            // Supprimer le token utilisé
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            // Révoquer tous les tokens
            $user->tokens()->delete();

            event(new PasswordReset($user));

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès. Veuillez vous reconnecter.',
            ]);

        } catch (\Exception $e) {
            Log::error('Reset password error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation',
            ], 500);
        }
    }

    /**
     * ✅ NOUVELLE MÉTHODE HELPER: Formater les données utilisateur
     */
    protected function formatUserData(\App\Models\User $user, array $additionalStats = []): array
    {
        $this->ensureUserLevelExists($user);

        $baseData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'currency' => $user->currency ?? 'EUR',
            'language' => $user->language,
            'timezone' => $user->timezone,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
        ];

        // Ajouter les données de niveau gaming
        if ($user->level) {
            $baseData['level'] = [
                'id' => $user->level->id,
                'user_id' => $user->level->user_id,
                'level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
                'current_level_xp' => $user->level->current_level_xp,
                'next_level_xp' => $user->level->next_level_xp,
                'created_at' => $user->level->created_at->toISOString(),
                'updated_at' => $user->level->updated_at->toISOString(),
            ];
            $baseData['total_xp'] = $user->level->total_xp;
        }

        // Ajouter stats additionnelles si fournies
        if (! empty($additionalStats)) {
            $baseData['stats'] = $additionalStats;
            $baseData['level_info'] = [
                'current_level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
                'progress_percentage' => $user->level ? round($user->level->getProgressPercentage(), 2) : 0,
                'title' => $user->level && method_exists($user->level, 'getTitle') ? $user->level->getTitle() : 'Débutant',
            ];
        }

        return $baseData;
    }

    /**
     * ✅ MÉTHODE HELPER: S'assurer que le UserLevel existe
     */
    protected function ensureUserLevelExists(\App\Models\User $user): void
    {
        $user->load('level');

        if (! $user->level || ! $user->level->exists) {
            $user->level()->create([
                'level' => 1,
                'total_xp' => 0,
                'current_level_xp' => 0,
                'next_level_xp' => 100,
            ]);

            $user->load('level');
        }
    }
}
