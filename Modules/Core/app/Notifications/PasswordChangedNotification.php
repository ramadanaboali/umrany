<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security confirmation sent whenever a password changes (self-service change or a completed
 * reset) — deliberately email-only regardless of which channel the reset code itself used, since
 * email is the more durable, reviewable record for a security-sensitive event. Callers must check
 * $user->email !== null before dispatching — there is no channel to fall back to for a
 * mobile-only account, and that's a data-availability fact, not something this class should paper
 * over with a silent SMS substitution.
 */
final class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        // Set via onQueue(), not a redeclared $queue property: Queueable already declares an
        // untyped $queue, and a typed redeclaration is an incompatible trait composition (fatal
        // error at class-load time) — the exact bug already fixed once in
        // VerificationCodeNotification; same fix here.
        $this->onQueue('core-default');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Umrany password was changed')
            ->greeting('Password changed')
            ->line('This is a confirmation that your account password was just changed.')
            ->line('If you did not make this change, contact support immediately.');
    }
}
