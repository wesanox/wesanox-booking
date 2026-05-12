<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Area;

defined('ABSPATH') || exit;

/**
 * Area domain entity (immutable DTO).
 * No WordPress dependencies – fully unit-testable.
 */
final class Area
{
    /** @var string[] Canonical weekday keys */
    public const WEEKDAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /** Valid booking type identifiers. */
    public const BOOKING_TYPES = ['standard', 'suite', 'timeslot'];

    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly ?string $opening_json,          // area_opening column
        public readonly ?string $time_settings_json,    // area_time_settings column
        public readonly ?string $booking_settings_json = null, // area_booking_settings column
        public readonly ?string $api_settings_json = null,     // wesanox_api_settings column
    ) {
    }

    /**
     * Deserialised opening hours, keyed by weekday.
     * Returns an array like ['monday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'], ...].
     *
     * @return array<string, array{enabled: bool, from: ?string, to: ?string}>
     */
    public function openingData(): array
    {
        if ($this->opening_json === null || $this->opening_json === '') {
            return $this->emptyOpening();
        }

        $decoded = json_decode($this->opening_json, true);

        if (!is_array($decoded)) {
            return $this->emptyOpening();
        }

        $result = [];
        foreach (self::WEEKDAYS as $day) {
            $day_data          = $decoded[$day] ?? [];
            $result[$day] = [
                'enabled' => (bool) ($day_data['enabled'] ?? false),
                'from'    => isset($day_data['from']) && $day_data['from'] !== '' ? (string) $day_data['from'] : null,
                'to'      => isset($day_data['to']) && $day_data['to'] !== '' ? (string) $day_data['to'] : null,
            ];
        }

        return $result;
    }

    /**
     * Deserialised time settings.
     *
     * @return array{area_time_separator: string, area_time_sheet: string, area_time_min: string, area_time_max: string}
     */
    public function timeSettingsData(): array
    {
        $defaults = [
            'area_time_separator' => '1',
            'area_time_sheet'     => '30',
            'area_time_min'       => '60',
            'area_time_max'       => '240',
        ];

        if ($this->time_settings_json === null || $this->time_settings_json === '') {
            return $defaults;
        }

        $decoded = json_decode($this->time_settings_json, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    /**
     * Booking settings for this area.
     *
     * @return array{booking_type: string, redirect_url: string, title: string}
     */
    public function bookingSettingsData(): array
    {
        $defaults = [
            'booking_type' => 'standard',
            'redirect_url' => '',
            'title'        => '',
        ];

        if ($this->booking_settings_json === null || $this->booking_settings_json === '') {
            return $defaults;
        }

        $decoded = json_decode($this->booking_settings_json, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    /**
     * API integration settings for this area.
     *
     * @return array{api_enabled: bool, credential_id: int, external_id: string}
     */
    public function apiSettingsData(): array
    {
        $defaults = [
            'api_enabled'   => false,
            'credential_id' => 0,
            'external_id'   => '',
        ];

        if ($this->api_settings_json === null || $this->api_settings_json === '') {
            return $defaults;
        }

        $decoded = json_decode($this->api_settings_json, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return [
            'api_enabled'   => (bool) ($decoded['api_enabled']   ?? false),
            'credential_id' => (int)  ($decoded['credential_id'] ?? 0),
            'external_id'   => (string) ($decoded['external_id'] ?? ''),
        ];
    }

    /**
     * Generate the appropriate shortcode string for this area.
     */
    public function shortcodeSnippet(): string
    {
        $bs = $this->bookingSettingsData();

        $type         = $bs['booking_type'];
        $redirect_url = $bs['redirect_url'];
        $title        = $bs['title'];

        $tag = match ($type) {
            'suite'    => 'booking_suite',
            'timeslot' => 'booking_timeslot',
            default    => 'booking_view',
        };

        $attrs = 'area_id="' . $this->id . '"';

        if ($redirect_url !== '') {
            $attrs .= ' redirect_url="' . esc_attr($redirect_url) . '"';
        }

        if ($title !== '') {
            $attrs .= ' title="' . esc_attr($title) . '"';
        }

        return '[' . $tag . ' ' . $attrs . ']';
    }

    /** @return array<string, array{enabled: bool, from: null, to: null}> */
    private function emptyOpening(): array
    {
        $result = [];
        foreach (self::WEEKDAYS as $day) {
            $result[$day] = ['enabled' => false, 'from' => null, 'to' => null];
        }
        return $result;
    }
}
