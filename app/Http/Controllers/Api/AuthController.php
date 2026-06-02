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
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            $user = User::create([
                'name'              => $validatedData['name'],
                'email'             => $validatedData['email'],
                'password'          => Hash::make($validatedData['password']),
                'currency'          => $validatedData['currency'] ?? 'EUR',
                'language'          => $validatedData['language'] ?? 'fr',
                'timezone'          => $validatedData['timezone'] ?? 'Europe/Paris',
                'email_verified_at' => now(),
            ]);

            $user->level()->create([
                'level'            => 1,
                'total_xp'         => 0,
                'current_level_xp' => 0,
                'next_level_xp'    => 100,
            ]);

            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => [
                    'user' => [
                        'id'         => $user->id,
                        'name'       => $user->name,
                        'email'      => $user->email,
                        'created_at' => $user->created_at,
                    ],
                    'token'      => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                ],
                'message' => 'Inscription réussie. Bienvenue !',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
            ], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect',
                    'errors'  => ['email' => ['Identifiants invalides']],
                ], 401);
            }

            $user  = Auth::user();
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));

            return response()->json([
                'success' => true,
                'data'    => [
                    'user' => [
                        'id'       => $user->id,
                        'name'     => $user->name,
                        'email'    => $user->email,
                        'is_admin' => $user->is_admin ?? false,
                    ],
                    'token'      => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                ],
                'message' => 'Connexion réussie',
            ]);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
            ], 500);
        }
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['user' => $request->user()->load('level')],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $currentToken = $request->user()->currentAccessToken();

            if ($currentToken && method_exists($currentToken, 'delete')) {
                $currentToken->delete();
            } else {
                $request->user()->tokens()->latest()->first()?->delete();
            }

            return response()->json(['success' => true, 'message' => 'Déconnexion réussie']);

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json(['success' => true, 'message' => 'Déconnexion effectuée']);
        }
    }

    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();
            return response()->json(['success' => true, 'message' => 'Déconnexion de tous les appareils réussie']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors de la déconnexion'], 500);
        }
    }

    /**
     * Mot de passe oublié — FIX: envoi du mail ajouté
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
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Réponse identique que l'email existe ou non (sécurité)
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Si cette adresse email est associée à un compte, un lien de réinitialisation vous a été envoyé.',
            ]);
        }

        try {
            // Générer le token
            $token = Str::random(64);

            // Sauvegarder en base
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            // ✅ FIX: Envoyer le mail (était absent avant)
            $user->notify(new ResetPasswordNotification($token));

            return response()->json([
                'success' => true,
                'message' => 'Si cette adresse email est associée à un compte, un lien de réinitialisation vous a été envoyé.',
            ]);

        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation',
            ], 500);
        }
    }

    /**
     * Réinitialisation du mot de passe
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de réinitialisation invalide',
                ], 400);
            }

            if (Carbon::parse($passwordReset->created_at)->diffInHours(now()) > 24) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Le lien de réinitialisation a expiré. Refais une demande.',
                ], 400);
            }

            $user = User::where('email', $request->email)->first();
            $user->update([
                'password'       => Hash::make($request->password),
                'remember_token' => null,
            ]);

            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            $user->tokens()->delete();

            event(new PasswordReset($user));

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès. Tu peux maintenant te connecter.',
            ]);

        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation',
            ], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors'  => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
                'errors'  => ['new_password' => ['Le nouveau mot de passe doit être différent de l\'ancien.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'        => 'sometimes|string|min:2|max:255',
                'email'       => 'sometimes|email|unique:users,email,' . $request->user()->id,
                'phone'       => 'nullable|string|max:20',
                'currency'    => 'sometimes|string|in:EUR,USD,GBP,CHF',
                'language'    => 'sometimes|string|in:fr,en,es,de',
                'preferences' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $user->update($validator->validated());

            return response()->json([
                'success' => true,
                'data'    => ['user' => $user->fresh()],
                'message' => 'Profil mis à jour avec succès',
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
            ], 500);
        }
    }

    public function sessions(Request $request): JsonResponse
    {
        try {
            $tokens = $request->user()->tokens()->latest()->get()->map(fn($t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'created_at' => $t->created_at,
                'expires_at' => $t->expires_at,
                'is_current' => $t->id === $request->user()->currentAccessToken()?->id,
            ]);

            return response()->json(['success' => true, 'data' => $tokens]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur sessions'], 500);
        }
    }

    public function revokeSession(Request $request, int $sessionId): JsonResponse
    {
        try {
            $token = $request->user()->tokens()->find($sessionId);

            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Session non trouvée'], 404);
            }

            $currentToken = $request->user()->currentAccessToken();
            if ($currentToken && $currentToken->id == $sessionId) {
                return response()->json(['success' => false, 'message' => 'Impossible de révoquer la session actuelle'], 400);
            }

            $token->delete();

            return response()->json(['success' => true, 'message' => 'Session révoquée avec succès']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors de la révocation'], 500);
        }
    }
}
