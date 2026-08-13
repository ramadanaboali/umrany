<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * The closed set of platforms a Provider's `social_links` JSON object may key on
 * (FR-PROVIDER-002 lists "Social Media Links" without fixing the platform set — this is the
 * concrete list for this market). Adding a platform is a code-only change here, never a
 * migration, since the column itself is just JSON.
 */
enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Twitter = 'twitter';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case Snapchat = 'snapchat';
    case WhatsApp = 'whatsapp';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
