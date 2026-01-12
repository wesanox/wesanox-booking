<?php

namespace Wesanox\Booking\Service\Api;

defined('ABSPATH') || exit;

class ServiceAuth
{
    const HEADER_KEY    = 'X-Wesanox-Key';
    const HEADER_SECRET = 'X-Wesanox-Secret';

    // Option-Namen in wp_options (kannst du anpassen)
    const OPT_API_KEY              = 'wesanox_api_key';
    const OPT_API_SECRET_HASH      = 'wesanox_api_secret_hash'; // Hash, nicht im Klartext speichern
    const OPT_ENFORCE_IP_WHITELIST = 'wesanox_api_enforce_ip';
    const OPT_IP_WHITELIST         = 'wesanox_api_ip_whitelist'; // CSV-Liste von IPv4/IPv6

    /**
     * Permission Callback für REST-Routen
     */
    public static function permission_callback(\WP_REST_Request $request)
    {
        $enforce_ip = (bool) get_option(self::OPT_ENFORCE_IP_WHITELIST, false);
        if ($enforce_ip) {
            $allowed = array_filter(array_map('trim', explode(',', (string) get_option(self::OPT_IP_WHITELIST, ''))));
            $ip = self::get_client_ip();
            if (!$ip || !self::ip_in_list($ip, $allowed)) {
                return new \WP_Error('wesanox_forbidden_ip', 'IP not allowed', ['status' => 403]);
            }
        }

        // 2) Header: Key & Secret prüfen
        $headers = self::normalized_headers();

        $key    = $headers[self::HEADER_KEY]    ?? '';
        $secret = $headers[self::HEADER_SECRET] ?? '';

        if ($key === '' || $secret === '') {
            return new \WP_Error('wesanox_auth_missing', 'Missing API credentials', ['status' => 401]);
        }

        $stored_key   = (string) get_option(self::OPT_API_KEY, '');
        $secret_hash  = (string) get_option(self::OPT_API_SECRET_HASH, '');

        if (!hash_equals($stored_key, $key)) {
            return new \WP_Error('wesanox_auth_invalid', 'Invalid API key', ['status' => 401]);
        }

        if ($secret_hash === '' || !password_verify($secret, $secret_hash)) {
            return new \WP_Error('wesanox_auth_invalid', 'Invalid API secret', ['status' => 401]);
        }

        return true; // erlaubt
    }

    private static function normalized_headers(): array
    {
        $out = [];
        foreach (getallheaders() ?: [] as $k => $v) {
            $out[trim($k)] = is_array($v) ? (string) reset($v) : (string) $v;
        }
        return $out;
    }

    private static function get_client_ip(): ?string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                // Falls X-Forwarded-For mehrere IPs enthält
                $val = explode(',', $_SERVER[$key]);
                $ip = trim($val[0]);
                return $ip !== '' ? $ip : null;
            }
        }
        return null;
    }

    private static function ip_in_list(string $ip, array $allowed): bool
    {
        // Simple exact match; bei Bedarf CIDR-Unterstützung ergänzen
        return in_array($ip, $allowed, true);
    }
}