<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $broadcastTitle }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f7f9; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #f59e0b, #d97706); padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 28px; letter-spacing: -0.5px; }
        .header span { font-size: 32px; display: block; margin-bottom: 8px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 12px; }
        .badge-info    { background: #dbeafe; color: #1d4ed8; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #a16207; }
        .badge-error   { background: #fee2e2; color: #b91c1c; }
        .body { padding: 40px; }
        .body h2 { color: #1e293b; font-size: 22px; margin: 0 0 16px; }
        .body p  { color: #475569; font-size: 16px; line-height: 1.7; margin: 0; }
        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 24px 40px; text-align: center; }
        .footer p { color: #94a3b8; font-size: 13px; margin: 0; }
        .footer strong { color: #64748b; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <span>
                <img src="{{ asset('images/logo/logo.svg') }}" alt="Logo">
            </span>
        </div>

        <div class="body">
            <span class="badge badge-{{ $notificationType }}">{{ ucfirst($notificationType) }}</span>
            <h2>{{ $broadcastTitle }}</h2>
            <p>{{ $broadcastMessage }}</p>
        </div>

        <div class="footer">
            <p>Ce message vous a été envoyé par l'équipe <strong>CoinQuest</strong>.<br>
            Vous recevez cet email car vous êtes inscrit sur CoinQuest.</p>
        </div>
    </div>
</body>
</html>
