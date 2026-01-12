<?php


namespace Wesanox\Booking\Service\Api;

defined('ABSPATH') || exit;

class ServiceAuthFixed
{
    // ======= HIER DEINE FESTEN TEST-CREDENTIALS EINTRAGEN =======
    private const FIXED_KEY = '1827771200DSAJJAHHSDJD23999192ASDDDAA';
    private const FIXED_SECRET = 'TZJAJSDHSADJFHASDUASHDHFH1230998812812AAAA';

    // Welche Header erwartest du?
    private const HDR_KEY = 'X-Wesanox-Key';
    private const HDR_SECRET = 'X-Wesanox-Secret';

    public static function wesanox_permission(\WP_REST_Request $request)
    {
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $key = $headers[self::HDR_KEY] ?? $request->get_header(self::HDR_KEY);
        $secret = $headers[self::HDR_SECRET] ?? $request->get_header(self::HDR_SECRET);

        if (!$key || !$secret) {
            return new \WP_Error('wesanox_auth_missing', 'Missing API credentials', ['status' => 401]);
        }

        if (!hash_equals(self::FIXED_KEY, (string)$key)) {
            return new \WP_Error('wesanox_auth_invalid', 'Invalid API key', ['status' => 401]);
        }
        if (!hash_equals(self::FIXED_SECRET, (string)$secret)) {
            return new \WP_Error('wesanox_auth_invalid', 'Invalid API secret', ['status' => 401]);
        }

        return true;
    }
}
