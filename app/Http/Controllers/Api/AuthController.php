<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Créer l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now() // Auto-verify pour l'API, ou null si verification nécessaire
            ]);

            // Créer le token d'authentification
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'created_at']),
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at
                ],
                'message' => 'Inscription réussie. Bienvenue !'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Connexion utilisateur
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects',
                'errors' => [
                    'email' => ['Les identifiants fournis ne correspondent à aucun compte.']
                ]
            ], 401);
        }

        $user = Auth::user();

        // Vérifier si le compte est actif (pas soft deleted)
        if ($user->trashed()) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Compte désactivé. Contactez le support.'
            ], 403);
        }

        // Révoquer les anciens tokens si demandé
        if ($request->boolean('revoke_other_tokens')) {
            $user->tokens()->delete();
        }

        // Créer un nouveau token
        $tokenName = $request->input('device_name', 'api_token');
        $expiresAt = $request->boolean('remember') ? now()->addDays(90) : now()->addDays(30);

        $token = $user->createToken($tokenName, ['*'], $expiresAt);

        // Mettre à jour la dernière connexion
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'avatar_url', 'preferences']),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
                'gaming_stats' => $user->getGamingStats()
            ],
            'message' => 'Connexion réussie. Bon retour !'
        ]);
    }

    /**
     * Déconnexion (token actuel uniquement)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Déconnexion de tous les appareils
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokensCount = $user->tokens()->count();

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'revoked_tokens_count' => $tokensCount
            ],
            'message' => 'Déconnexion de tous les appareils réussie'
        ]);
    }

    /**
     * Informations utilisateur actuel
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => array_merge(
                    $user->only(['id', 'name', 'email', 'avatar_url', 'created_at', 'preferences']),
                    [
                        'email_verified' => $user->hasVerifiedEmail(),
                        'is_admin' => $user->hasRole('admin'), // Si tu as un système de rôles
                        'last_login_at' => $user->last_login_at,
                        'account_status' => 'active'
                    ]
                ),
                'gaming_stats' => $user->getGamingStats(),
                'financial_summary' => [
                    'total_balance' => $user->getTotalBalance(),
                    'active_goals_count' => $user->financialGoals()->active()->count(),
                    'transactions_this_month' => $user->transactions()
                        ->whereMonth('transaction_date', now()->month)
                        ->count()
                ]
            ],
            'message' => 'Informations utilisateur récupérées'
        ]);
    }

    /**
     * Demande de réinitialisation de mot de passe
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->email;

        // Vérifier si l'utilisateur existe
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Pour la sécurité, on ne révèle pas si l'email existe ou non
            return response()->json([
                'success' => true,
                'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'
            ]);
        }

        // Générer un token de réinitialisation
        $token = Str::random(64);

        // Supprimer les anciens tokens pour cet email
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Créer un nouveau token
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now()
        ]);

        // Envoyer l'email de réinitialisation
        $user->notify(new ResetPasswordNotification($token));

        return response()->json([
            'success' => true,
            'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.'
        ]);
    }

    /**
     * Réinitialiser le mot de passe
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Vérifier le token
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de réinitialisation invalide ou expiré',
                    'errors' => [
                        'token' => ['Le token de réinitialisation est invalide.']
                    ]
                ], 400);
            }

            // Vérifier l'expiration (24h)
            if (now()->diffInHours($passwordReset->created_at) > 24) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Token de réinitialisation expiré',
                    'errors' => [
                        'token' => ['Le token de réinitialisation a expiré.']
                    ]
                ], 400);
            }

            // Réinitialiser le mot de passe
            $user = User::where('email', $request->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
                'remember_token' => null
            ]);

            // Supprimer le token utilisé
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            // Révoquer tous les tokens existants pour sécurité
            $user->tokens()->delete();

            // Déclencher l'événement de réinitialisation
            event(new PasswordReset($user));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès. Veuillez vous reconnecter.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Changer le mot de passe (utilisateur connecté)
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier l'ancien mot de passe
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect',
                'errors' => [
                    'current_password' => ['Le mot de passe actuel est incorrect.']
                ]
            ], 400);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Optionnel : révoquer les autres tokens
        if ($request->boolean('revoke_other_tokens', true)) {
            $currentToken = $request->user()->currentAccessToken();
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }

    /**
     * Mettre à jour le profil utilisateur
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'avatar_url', 'preferences'])
            ],
            'message' => 'Profil mis à jour avec succès'
        ]);
    }

    /**
     * Lister les sessions actives (tokens)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activeSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $sessions = $user->tokens()->get()->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $token->id === $currentTokenId,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'abilities' => $token->abilities
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $sessions,
                'total_count' => $sessions->count()
            ],
            'message' => 'Sessions actives récupérées'
        ]);
    }

    /**
     * Révoquer une session spécifique
     *
     * @param Request $request
     * @param string $tokenId
     * @return JsonResponse
     */
    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        // Empêcher la révocation du token actuel
        if ($tokenId == $currentTokenId) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de révoquer la session actuelle'
            ], 400);
        }

        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Session non trouvée'
            ], 404);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session révoquée avec succès'
        ]);
    }
}
