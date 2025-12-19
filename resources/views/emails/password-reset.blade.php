<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Reset Your Password') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .message {
            color: #666;
            margin-bottom: 25px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white !important;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
        }
        .button:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .expires {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #92400e;
            font-size: 14px;
        }
        .alternative {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #666;
        }
        .alternative p {
            margin: 5px 0;
        }
        .url {
            word-break: break-all;
            color: #10b981;
            font-size: 12px;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name', 'Ghanem ERP') }}</h1>
        </div>
        
        <div class="content">
            <p class="greeting">{{ __('Hello') }} {{ $user->name }},</p>
            
            <p class="message">
                {{ __('You are receiving this email because we received a password reset request for your account.') }}
            </p>
            
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="button">
                    {{ __('Reset Password') }}
                </a>
            </div>
            
            <div class="expires">
                <strong>{{ __('Important:') }}</strong> {{ __('This password reset link will expire in :count minutes.', ['count' => $expiresIn]) }}
            </div>
            
            <p class="message">
                {{ __('If you did not request a password reset, no further action is required. Your account is safe.') }}
            </p>
            
            <div class="alternative">
                <p>{{ __('If you\'re having trouble clicking the button, copy and paste the URL below into your web browser:') }}</p>
                <p class="url">{{ $resetUrl }}</p>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Ghanem ERP') }}. {{ __('All rights reserved.') }}</p>
            <p>{{ __('This is an automated message. Please do not reply to this email.') }}</p>
        </div>
    </div>
</body>
</html>
