<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * The channels a NotificationPreference row can toggle per event type. Push is deliberately not
 * a case yet — it needs a device-token model and an FCM/APNs integration that don't exist in this
 * codebase at all; adding it later is one more enum case plus one more boolean column on
 * notification_preferences, not a schema redesign. See docs/decisions/0017-notification-
 * preferences-schema.md.
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
}
