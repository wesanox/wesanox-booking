<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Area;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Area\Area;
use Wesanox\Booking\Domain\Area\AreaRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveAreaService
{
    /** @var string[] Valid weekday keys */
    private const WEEKDAYS = Area::WEEKDAYS;

    /** Valid values for area_time_separator */
    private const VALID_SEPARATORS = ['1', '2'];

    /** Valid booking type values */
    private const VALID_BOOKING_TYPES = Area::BOOKING_TYPES;

    public function __construct(
        private AreaRepositoryInterface $repository,
    ) {
    }

    /**
     * Create or update an area.
     *
     * @param ?int   $id               null = create, int = update
     * @param string $name
     * @param array<string, array{enabled: bool, from: ?string, to: ?string}> $opening
     * @param array{area_time_separator: string, area_time_sheet: string, area_time_min: string, area_time_max: string} $time_settings
     * @param array{booking_type: string, redirect_url: string, title: string} $booking_settings
     * @param array{api_enabled: bool, credential_id: int, external_id: string} $api_settings
     * @return int  Saved area ID
     * @throws ValidationException
     */
    public function execute(
        ?int   $id,
        string $name,
        array  $opening,
        array  $time_settings,
        array  $booking_settings = [],
        array  $api_settings = [],
    ): int {
        $errors = [];

        // --- Name ---
        if (trim($name) === '') {
            $errors[] = 'Der Name ist erforderlich.';
        }

        // --- Opening hours ---
        $errors = array_merge($errors, $this->validateOpening($opening));

        // --- Time settings ---
        $errors = array_merge($errors, $this->validateTimeSettings($time_settings));

        // --- Booking settings ---
        $errors = array_merge($errors, $this->validateBookingSettings($booking_settings));

        if (!empty($errors)) {
            throw ValidationException::withErrors($errors);
        }

        $data = [
            'name'                  => $name,
            'opening_json'          => json_encode($opening, JSON_UNESCAPED_UNICODE),
            'time_settings_json'    => json_encode($time_settings, JSON_UNESCAPED_UNICODE),
            'booking_settings_json' => json_encode($booking_settings, JSON_UNESCAPED_UNICODE),
            'api_settings_json'     => json_encode($this->normaliseApiSettings($api_settings), JSON_UNESCAPED_UNICODE),
        ];

        if ($id === null) {
            return $this->repository->create($data);
        }

        $this->repository->update($id, $data);

        return $id;
    }

    /**
     * @param array<string, mixed> $opening
     * @return string[]
     */
    private function validateOpening(array $opening): array
    {
        $errors        = [];
        $day_labels    = [
            'monday'    => 'Montag',
            'tuesday'   => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday'  => 'Donnerstag',
            'friday'    => 'Freitag',
            'saturday'  => 'Samstag',
            'sunday'    => 'Sonntag',
        ];

        foreach (self::WEEKDAYS as $day) {
            $day_data = $opening[$day] ?? [];
            $label    = $day_labels[$day] ?? $day;
            $enabled  = (bool) ($day_data['enabled'] ?? false);

            if (!$enabled) {
                continue;
            }

            $from = $day_data['from'] ?? '';
            $to   = $day_data['to']   ?? '';

            if (!$this->isValidTime($from)) {
                $errors[] = "{$label}: Öffnungszeit \"Von\" ist ungültig.";
                continue;
            }

            if (!$this->isValidTime($to)) {
                $errors[] = "{$label}: Öffnungszeit \"Bis\" ist ungültig.";
                continue;
            }

            // 00:00 as closing time means midnight / end of day (= 24:00).
            // <input type="time"> cannot express 24:00, so users enter 00:00.
            // Treat it as later than any HH:MM value for comparison purposes.
            $to_cmp = ($to === '00:00') ? '24:00' : $to;

            if ($to_cmp <= $from) {
                $errors[] = "{$label}: Öffnungszeit \"Bis\" muss nach \"Von\" liegen.";
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $time_settings
     * @return string[]
     */
    private function validateTimeSettings(array $time_settings): array
    {
        $errors    = [];
        $separator = (string) ($time_settings['area_time_separator'] ?? '');

        if (!in_array($separator, self::VALID_SEPARATORS, true)) {
            $errors[] = 'Zeitintervall-Typ ist ungültig.';
        }

        foreach (['area_time_sheet', 'area_time_min', 'area_time_max'] as $key) {
            $value = (string) ($time_settings[$key] ?? '');
            if ($value !== '' && (!ctype_digit($value) || (int) $value < 0)) {
                $errors[] = "Zeiteinstellung \"{$key}\" muss eine nicht-negative Ganzzahl sein.";
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $booking_settings
     * @return string[]
     */
    private function validateBookingSettings(array $booking_settings): array
    {
        $errors = [];

        $type = (string) ($booking_settings['booking_type'] ?? 'standard');

        if (!in_array($type, self::VALID_BOOKING_TYPES, true)) {
            $errors[] = 'Buchungstyp ist ungültig.';
        }

        return $errors;
    }

    private function isValidTime(string $time): bool
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $api_settings
     * @return array{api_enabled: bool, credential_id: int, external_id: string}
     */
    private function normaliseApiSettings(array $api_settings): array
    {
        return [
            'api_enabled'   => (bool)   ($api_settings['api_enabled']   ?? false),
            'credential_id' => (int)    ($api_settings['credential_id'] ?? 0),
            'external_id'   => (string) ($api_settings['external_id']   ?? ''),
        ];
    }
}
