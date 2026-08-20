<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The base `ResetPassword` notification's `toMail()` hardcodes `route('password.reset', ...)`.
 * This app's admin reset routes are named `admin.password.reset` (see `routes/admin.php`'s
 * `admin.` route-name prefix), so the unmodified notification throws a `RouteNotFoundException`
 * the moment it actually renders — only masked in a test that `Notification::fake()`s rather than
 * let it render for real. This subclass only overrides the URL the mail points at; everything
 * else (token, expiry) is inherited unchanged. See `Modules\Core\Models\Admin::
 * sendPasswordResetNotification()`, the only place this is dispatched from.
 */
final class AdminResetPasswordNotification extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        /** @var CanResetPassword $notifiable */
        $url = url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in '.config('auth.passwords.admins.expire').' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
