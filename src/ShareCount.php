<?php declare(strict_types=1);

namespace Gratis\Social;

defined('ABSPATH') || exit;

/**
 * Optional share-count provider.
 *
 * The plugin ships no third-party JavaScript SDK and makes no automatic
 * outbound API calls. Counts are resolved through the `gratis_social_share_count`
 * filter so site owners can wire in their own data source (e.g. a server-side
 * Graph API call with their own token). Resolved values are cached in a
 * transient for one hour to avoid recomputing on every request.
 */
final class ShareCount {

    /** Cache lifetime for resolved counts. */
    private const TTL = HOUR_IN_SECONDS;

    /**
     * Resolve the share count for a network on a given URL, or null when no
     * provider supplies a value (in which case no count badge is rendered).
     */
    public static function get(string $network, string $url): ?int {
        $key    = 'gratis_social_count_' . md5($network . '|' . $url);
        $cached = get_transient($key);
        if (false !== $cached) {
            return null === $cached || '' === $cached ? null : (int) $cached;
        }

        /**
         * Filter the share count for a network/URL pair.
         *
         * Return an integer to display a count, or null to hide it.
         *
         * @param int|null $count   Resolved count (null by default).
         * @param string   $network Network slug.
         * @param string   $url     Shared URL.
         */
        $count = apply_filters('gratis_social_share_count', null, $network, $url);

        if (null !== $count && is_numeric($count)) {
            $count = (int) $count;
            set_transient($key, $count, self::TTL);
            return $count;
        }

        // Cache the "unknown" result too, so we do not re-run the filter every load.
        set_transient($key, '', self::TTL);
        return null;
    }

    /**
     * Human-friendly short form for a count (1200 -> 1.2k).
     */
    public static function format(int $count): string {
        if ($count >= 1000000) {
            return rtrim(rtrim(number_format($count / 1000000, 1), '0'), '.') . 'M';
        }
        if ($count >= 1000) {
            return rtrim(rtrim(number_format($count / 1000, 1), '0'), '.') . 'k';
        }
        return (string) $count;
    }
}
