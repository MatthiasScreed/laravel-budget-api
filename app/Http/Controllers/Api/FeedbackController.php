<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    /**
     * POST /api/feedback
     * Reçoit un feedback utilisateur et envoie un email
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type'    => 'required|in:bug,idea,other',
            'message' => 'required|string|min:10|max:1000',
            'page'    => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $msg  = $request->input('message');
        $page = $request->input('page', 'inconnue');

        $emoji = match($type) {
            'bug'   => '🐛',
            'idea'  => '💡',
            default => '💬',
        };

        $label = match($type) {
            'bug'   => 'Bug signalé',
            'idea'  => 'Idée suggérée',
            default => 'Avis utilisateur',
        };

        // Log en base pour retrouver plus tard
        Log::channel('slack')->info("{$emoji} {$label}", [
            'user_id'  => $user->id,
            'user'     => $user->name,
            'email'    => $user->email,
            'type'     => $type,
            'page'     => $page,
            'message'  => $msg,
            'app_url'  => config('app.url'),
        ]);

        // Email direct vers toi
        $this->sendFeedbackEmail($user, $type, $msg, $page, $emoji, $label);

        return response()->json([
            'success' => true,
            'message' => 'Feedback reçu, merci !',
        ]);
    }

    /**
     * Envoie l'email de feedback via Resend/SMTP
     */
    private function sendFeedbackEmail(
        $user,
        string $type,
        string $message,
        string $page,
        string $emoji,
        string $label
    ): void {
        $adminEmail = config('mail.feedback_to', config('mail.from.address'));

        try {
            Mail::html(
                $this->buildEmailHtml($user, $type, $message, $page, $emoji, $label),
                function ($mail) use ($adminEmail, $emoji, $label, $user) {
                    $mail->to($adminEmail)
                        ->replyTo($user->email, $user->name)
                        ->subject("{$emoji} CoinQuest — {$label} de {$user->name}");
                }
            );
        } catch (\Exception $e) {
            Log::error('Feedback email failed', ['error' => $e->getMessage()]);
        }
    }

    private function buildEmailHtml($user, string $type, string $message, string $page, string $emoji, string $label): string
    {
        $appUrl     = config('app.frontend_url', config('app.url'));
        $escapedMsg = nl2br(htmlspecialchars($message));
        $date       = now()->format('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, sans-serif; max-width: 600px; margin: 0 auto; padding: 2rem; color: #111;">
  <h2 style="color: #7c3aed;">{$emoji} {$label}</h2>

  <table style="width:100%; border-collapse:collapse; margin-bottom:1.5rem;">
    <tr>
      <td style="padding:8px 0; color:#6b7280; width:120px;">Utilisateur</td>
      <td style="padding:8px 0; font-weight:600;">{$user->name} ({$user->email})</td>
    </tr>
    <tr>
      <td style="padding:8px 0; color:#6b7280;">Type</td>
      <td style="padding:8px 0;">{$emoji} {$label}</td>
    </tr>
    <tr>
      <td style="padding:8px 0; color:#6b7280;">Page</td>
      <td style="padding:8px 0;"><code>{$page}</code></td>
    </tr>
    <tr>
      <td style="padding:8px 0; color:#6b7280;">Date</td>
      <td style="padding:8px 0;">{$date}</td>
    </tr>
    <tr>
      <td style="padding:8px 0; color:#6b7280;">User ID</td>
      <td style="padding:8px 0;">#{$user->id}</td>
    </tr>
  </table>

  <div style="background:#f9fafb; border-left:4px solid #7c3aed; padding:1rem 1.25rem; border-radius:0 8px 8px 0; margin-bottom:1.5rem;">
    <p style="margin:0; line-height:1.6;">{$escapedMsg}</p>
  </div>

  <p style="color:#9ca3af; font-size:0.8rem;">
    Réponds directement à cet email pour contacter {$user->name}.<br>
    <a href="{$appUrl}/app/admin" style="color:#7c3aed;">Voir l'admin CoinQuest</a>
  </p>
</body>
</html>
HTML;
    }
}
