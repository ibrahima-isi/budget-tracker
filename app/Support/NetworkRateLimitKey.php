<?php

namespace App\Support;

class NetworkRateLimitKey
{
    public static function fromIp(?string $ip): string
    {
        if (! $ip) {
            return 'unknown';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed === false) {
                return 'unknown';
            }

            return bin2hex(substr($packed, 0, 8)).'/64';
        }

        return 'unknown';
    }
}
