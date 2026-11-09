<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

final class SpecialsCustomerNotice
{
    public const LABEL = 'Hinweise zu Allergien und Unverträglichkeiten bei Specials:';

    /**
     * Order meta read by the Laravel booking import as the booking's own allergy notice.
     */
    public const ORDER_META_KEY = '_wesanox_allergy_notice';

    public static function merge(?string $customerNotice, ?string $specialsNotice): string
    {
        $customerNotice = (string) $customerNotice;
        $specialsNotice = trim((string) $specialsNotice);

        if ($specialsNotice === '') {
            return $customerNotice;
        }

        $specialsSection = self::LABEL."\n".$specialsNotice;
        $existingSectionStart = strpos($customerNotice, self::LABEL);

        if ($existingSectionStart !== false) {
            $customerNotice = rtrim(substr($customerNotice, 0, $existingSectionStart));
        }

        if ($customerNotice === '') {
            return $specialsSection;
        }

        return $customerNotice."\n\n".$specialsSection;
    }
}
