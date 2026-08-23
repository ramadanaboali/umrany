<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Enums\NotificationChannel;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Notifications\Channels\LoggedSmsChannel;
use Modules\Core\Services\NotificationPreferenceService;

/**
 * Extends BaseUserNotification but overrides via() — the mail-vs-SMS choice depends on which
 * channel the code itself is being delivered to (VerificationCodeType), not on
 * NotificationPreference, so it can't reuse the base class's plain mail/in-app logic wholesale.
 * The in-app ("database") channel is still preference-gated, same as every other notification.
 */
final class VerificationCodeNotification extends BaseUserNotification
{
    /** Deliberately conservative — a stuck mail/SMS provider must not delay the OTP indefinitely;
     * the user can request a fresh code (rate-limited, see docs/api/conventions.md) faster than a
     * 4th retry would land anyway. */
    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function __construct(
        private readonly string $plainCode,
        private readonly VerificationCodeType $type,
        private readonly VerificationCodePurpose $purpose,
        private readonly int $expiresInMinutes,
        private readonly string $renderLocale,
    ) {
        // Latency-sensitive — blocks the user's registration/verification/reset flow. Set via
        // onQueue() rather than redeclaring $queue as a typed property: Queueable already
        // declares an untyped `$queue`, and PHP treats a typed redeclaration in the class as an
        // incompatible trait composition (fatal error at class-load time, not just a lint nit).
        $this->onQueue('core-high');
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::VerificationCodeSent;
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $preferences = app(NotificationPreferenceService::class);
        $channels = [];

        if ($preferences->allows($notifiable, $this->event(), NotificationChannel::InApp)) {
            $channels[] = 'database';
        }

        $channels[] = $this->type === VerificationCodeType::Email ? 'mail' : LoggedSmsChannel::class;

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $variant = $this->purpose === VerificationCodePurpose::PasswordReset ? 'reset' : 'verify';

        return (new MailMessage)
            ->subject(__("core::notifications.verification_code.{$variant}.subject", [], $this->renderLocale))
            ->greeting(__("core::notifications.verification_code.{$variant}.greeting", [], $this->renderLocale))
            ->line(__('core::notifications.verification_code.line_code', ['code' => $this->plainCode], $this->renderLocale))
            ->line(__('core::notifications.verification_code.line_expires', ['minutes' => $this->expiresInMinutes], $this->renderLocale))
            ->line(__('core::notifications.verification_code.line_ignore', [], $this->renderLocale));
    }

    public function toLoggedSms(mixed $notifiable): string
    {
        $key = $this->purpose === VerificationCodePurpose::PasswordReset ? 'sms_reset' : 'sms_verify';

        return __("core::notifications.verification_code.{$key}", ['code' => $this->plainCode, 'minutes' => $this->expiresInMinutes], $this->renderLocale);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => __('core::notifications.verification_code.in_app', [], $this->renderLocale),
            'purpose' => $this->purpose->value,
        ];
    }
}
