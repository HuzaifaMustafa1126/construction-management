<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class AccountPasswordReset extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return $frontend.'/reset-password?token='.urlencode($this->token)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());
    }
}
