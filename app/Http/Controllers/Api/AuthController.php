<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\Streak;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\StreakService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected StreakService $streakService;

    public function __construct(StreakService $streakService)
    {
        $this->streakService = $streakService;
    }

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

            // ✅ Récupérer TOUTES les données validées
            $validatedData = $request->validated();

            // Créer l'utilisateur
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'currency' => $validatedData['currency'] ?? 'EUR',
                'language' => $validatedData['language'] ?? 'fr',
                'timezone' => $validatedData['timezone'] ?? 'Europe/Paris',
                'email_verified_at' => now() // Auto-verify pour l'API
            ]);

            // ✅ CORRECTION : Créer explicitement le UserLevel
            $userLevel = $user->level()->create([
                'level' => 1,
                'total_xp' => 0,
                'current_level_xp' => 0,
                'next_level_xp' => 100
            ]);

            // ✅ Créer le token d'authentification avec expiration
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));

            // ✅ Charger la relation level pour la réponse
            $user->load('level');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'currency' => $user->currency,
                        'language' => $user->language,
                        'timezone' => $user->timezone,
                        'created_at' => $user->created_at
                    ],
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at
                ],
                'message' => 'Inscription réussie. Bienvenue !'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Registration error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->except('password')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants invalides'
                ], 401);
            }

            $user = Auth::user();

            // ✅ CORRECTION : S'assurer que le UserLevel existe
            if (!$user->level) {
                try {
                    $user->level()->create([
                        'level' => 1,
                        'total_xp' => 0,
                        'current_level_xp' => 0,
                        'next_level_xp' => 100
                    ]);
                } catch (\Exception $e) {
                    // Si erreur de création (ex: déjà existe), recharger
                    $user->refresh();
                }
            }

            // Créer le token avec expiration
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));

            // ✅ CORRECTION : Gestion sécurisée du StreakService
            $streakBonus = 0;
            try {
                if (class_exists(\App\Services\StreakService::class)) {
                    $streakService = app(\App\Services\StreakService::class);
                    $streakResult = $streakService->triggerStreak($user, \App\Models\Streak::TYPE_DAILY_LOGIN);
                    $streakBonus = $streakResult['success'] ? ($streakResult['bonus_xp'] ?? 0) : 0;
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs de streak en mode silencieux
                \Log::info('Streak service error (ignored): ' . $e->getMessage());
                $streakBonus = 0;
            }

            // Charger les relations pour la réponse
            $user->load('level');

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'currency' => $user->currency,
                        'gaming_level' => $user->level->level,
                        'total_xp' => $user->level->total_xp
                    ],
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                    'streak_bonus' => $streakBonus
                ],
                'message' => 'Connexion réussie'
            ], 200);


        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Informations de l'utilisateur connecté
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // ✅ S'assurer que le UserLevel existe
            if (!$user->level) {
                $user->level()->create([
                    'level' => 1,
                    'total_xp' => 0,
                    'current_level_xp' => 0,
                    'next_level_xp' => 100
                ]);
            }

            // Charger les relations nécessaires
            $user->load(['level', 'achievements' => function ($query) {
                $query->orderBy('user_achievements.unlocked_at', 'desc')->limit(3);
            }]);

            // Calculer les statistiques
            $stats = [
                'total_transactions' => $user->transactions()->count(),
                'total_goals' => $user->financialGoals()->count(),
                'completed_goals' => $user->financialGoals()->where('status', 'completed')->count(),
                'gaming_level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
                'achievements_count' => $user->achievements()->count(),
                'current_streak' => $user->streaks()->where('type', Streak::TYPE_DAILY_LOGIN)->first()?->current_count ?? 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'currency' => $user->currency,
                    'timezone' => $user->timezone,
                    'language' => $user->language,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'stats' => $stats,
                    'level_info' => [
                        'current_level' => $user->level->level,
                        'total_xp' => $user->level->total_xp,
                        'progress_percentage' => round($user->level->getProgressPercentage(), 2),
                        'title' => $user->level->getTitle()
                    ],
                    'recent_achievements' => $user->achievements->map(function ($achievement) {
                        return [
                            'id' => $achievement->id,
                            'name' => $achievement->name,
                            'description' => $achievement->description,
                            'icon' => $achievement->icon,
                            'points' => $achievement->points,
                            'unlocked_at' => $achievement->pivot->unlocked_at
                        ];
                    }),
                    'preferences' => $user->preferences ?? []
                ],
                'message' => 'Profil utilisateur récupéré avec succès'
            ], 200);

        } catch (\Exception $e) {
            Log::error('User profile error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil'
            ], 500);
        }
    }

    /**
     * Déconnexion de l'utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // ✅ CORRECTION : Vérifier que le token existe avant de le supprimer
            $currentToken = $request->user()->currentAccessToken();

            if ($currentToken) {
                $currentToken->delete();
            } else {
                // En cas de test, supprimer tous les tokens de l'utilisateur
                $user->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Déconnexion de tous les appareils
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Logout all error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Mise à jour du profil utilisateur
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validatedData = $request->validated();

            $user->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => $user->fresh(),
                'message' => 'Profil mis à jour avec succès'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil'
            ], 500);
        }
    }

    /**
     * Changement de mot de passe
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validatedData = $request->validated();

            if (!Hash::check($validatedData['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($validatedData['new_password'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Password change error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe'
            ], 500);
        }
    }

    /**
     * Demande de réinitialisation de mot de passe
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lien de réinitialisation envoyé par email'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'envoyer le lien de réinitialisation'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation'
            ], 500);
        }
    }

    /**
     * Réinitialisation du mot de passe
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mot de passe réinitialisé avec succès'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Impossible de réinitialiser le mot de passe'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation'
            ], 500);
        }
    }
}
