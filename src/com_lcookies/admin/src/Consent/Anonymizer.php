<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Consent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Pseudonymisation of the visitor data kept in the consent log.
 *
 * The IP address is truncated (IPv4 /24, IPv6 /48) and then hashed with HMAC-SHA256 keyed with
 * the site secret, so the log never holds an address and the hash cannot be reversed by trying
 * every possible network without the secret.
 */
final class Anonymizer
{
    /**
     * @param   string  $secret  Site secret (`secret` in configuration.php).
     */
    public function __construct(private string $secret)
    {
    }

    /**
     * Truncates an IP address: IPv4 keeps the first 3 bytes (/24), IPv6 the first 6 bytes (/48).
     * IPv4-mapped IPv6 addresses are treated as IPv4.
     *
     * @param   string  $ip  The address.
     *
     * @return  string  The truncated address, empty if it is not a valid address.
     */
    public static function truncateIp(string $ip): string
    {
        $packed = $ip === '' ? false : @inet_pton(trim($ip));

        if ($packed === false) {
            return '';
        }

        if (\strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            $packed = substr($packed, 12);
        }

        if (\strlen($packed) === 4) {
            return inet_ntop(substr($packed, 0, 3) . "\0");
        }

        return inet_ntop(substr($packed, 0, 6) . str_repeat("\0", 10));
    }

    /**
     * Hash of the truncated IP address.
     *
     * @param   string  $ip  The address.
     *
     * @return  string  64 hexadecimal characters, empty if the address is not valid.
     */
    public function ipHash(string $ip): string
    {
        $truncated = self::truncateIp($ip);

        return $truncated === '' ? '' : $this->hash('ip', $truncated);
    }

    /**
     * Hash of the user agent.
     *
     * @param   string  $userAgent  The user agent.
     *
     * @return  string  64 hexadecimal characters, empty if there is no user agent.
     */
    public function userAgentHash(string $userAgent): string
    {
        $userAgent = trim($userAgent);

        return $userAgent === '' ? '' : $this->hash('ua', $userAgent);
    }

    /**
     * @param   string  $kind   Kind of value, so equal values of different kinds get different hashes.
     * @param   string  $value  The value.
     *
     * @return  string
     */
    private function hash(string $kind, string $value): string
    {
        return hash_hmac('sha256', 'lcookies:' . $kind . ':' . $value, $this->secret);
    }
}
