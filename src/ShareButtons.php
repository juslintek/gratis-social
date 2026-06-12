<?php declare(strict_types=1);

namespace Gratis\Social;

defined('ABSPATH') || exit;

/**
 * Builds and renders social share buttons.
 *
 * Sharing uses each network's public share-intent URL only — no third-party
 * JavaScript SDK and no tracking pixels. Buttons can be auto-appended to post
 * content (inline), shown as a floating sidebar, or placed manually with the
 * [gratis_share] shortcode.
 */
final class ShareButtons {

    /** Guards against rendering the same context twice on one request. */
    private static bool $assetsNeeded = false;

    /**
     * Display labels for each supported network.
     *
     * @return array<string,string>
     */
    public static function networkLabels(): array {
        return [
            'facebook'  => __('Facebook', 'gratis-social'),
            'x'         => __('X', 'gratis-social'),
            'linkedin'  => __('LinkedIn', 'gratis-social'),
            'pinterest' => __('Pinterest', 'gratis-social'),
            'email'     => __('Email', 'gratis-social'),
        ];
    }

    /**
     * Append buttons to singular content for the configured post types.
     */
    public static function appendToContent(string $content): string {
        if (is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_types = Settings::getList('post_types');
        if (!empty($post_types) && !is_singular($post_types)) {
            return $content;
        }

        $display = Settings::getString('display', 'inline');
        $append  = '';

        if (in_array($display, ['inline', 'both'], true)) {
            $append .= self::render(['context' => 'inline']);
        }
        if (in_array($display, ['floating', 'both'], true)) {
            $append .= self::render(['context' => 'floating']);
        }

        return $content . $append;
    }

    /**
     * Register the [gratis_share] shortcode.
     */
    public static function registerShortcode(): void {
        add_shortcode('gratis_share', [__CLASS__, 'shortcode']);
    }

    /**
     * Shortcode handler. Attributes: url, title, networks (comma list),
     * label, counts ("1"/"0").
     *
     * @param array<string,string>|string $atts Shortcode attributes.
     */
    public static function shortcode($atts): string {
        $atts = shortcode_atts(
            [
                'url'      => '',
                'title'    => '',
                'networks' => '',
                'label'    => '',
                'counts'   => '',
            ],
            is_array($atts) ? $atts : [],
            'gratis_share'
        );

        $args = ['context' => 'shortcode'];

        if ('' !== $atts['url']) {
            $args['url'] = esc_url_raw($atts['url']);
        }
        if ('' !== $atts['title']) {
            $args['title'] = sanitize_text_field($atts['title']);
        }
        if ('' !== $atts['networks']) {
            $args['networks'] = array_filter(array_map('trim', explode(',', $atts['networks'])));
        }
        if ('' !== $atts['label']) {
            $args['label'] = sanitize_text_field($atts['label']);
        }
        if ('' !== $atts['counts']) {
            $args['counts'] = (bool) (int) $atts['counts'];
        }

        return self::render($args);
    }

    /**
     * Build the share-intent URL for a network.
     */
    private static function intentUrl(string $network, string $url, string $title, string $image): string {
        $e_url   = rawurlencode($url);
        $e_title = rawurlencode($title);

        switch ($network) {
            case 'facebook':
                return 'https://www.facebook.com/sharer/sharer.php?u=' . $e_url;
            case 'x':
                return 'https://twitter.com/intent/tweet?url=' . $e_url . '&text=' . $e_title;
            case 'linkedin':
                return 'https://www.linkedin.com/sharing/share-offsite/?url=' . $e_url;
            case 'pinterest':
                $media = '' !== $image ? '&media=' . rawurlencode($image) : '';
                return 'https://pinterest.com/pin/create/button/?url=' . $e_url . '&description=' . $e_title . $media;
            case 'email':
                return 'mailto:?subject=' . $e_title . '&body=' . $e_url;
            default:
                return '#';
        }
    }

    /**
     * Inline SVG icon markup for a network (currentColor for theming).
     */
    private static function icon(string $network): string {
        $paths = [
            'facebook'  => '<path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.9h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94z"/>',
            'x'         => '<path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.22-6.82-5.97 6.82H1.66l7.73-8.84L1.24 2.25h6.83l4.71 6.23 5.46-6.23zm-1.16 17.52h1.83L7.01 4.13H5.05l12.03 15.64z"/>',
            'linkedin'  => '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.78C.8 0 0 .78 0 1.74v20.52C0 23.22.8 24 1.78 24h20.44c.98 0 1.78-.78 1.78-1.74V1.74C24 .78 23.2 0 22.22 0z"/>',
            'pinterest' => '<path d="M12 2C6.48 2 2 6.48 2 12c0 4.08 2.45 7.59 5.96 9.13-.08-.78-.16-1.97.03-2.82.18-.78 1.15-4.94 1.15-4.94s-.29-.59-.29-1.46c0-1.37.79-2.39 1.78-2.39.84 0 1.25.63 1.25 1.39 0 .85-.54 2.11-.82 3.28-.23.98.49 1.78 1.46 1.78 1.75 0 3.1-1.85 3.1-4.51 0-2.36-1.69-4.01-4.11-4.01-2.8 0-4.44 2.1-4.44 4.27 0 .85.32 1.75.73 2.24.08.1.09.18.07.28-.08.32-.25 1-.28 1.14-.04.18-.15.22-.34.13-1.25-.58-2.03-2.4-2.03-3.86 0-3.14 2.28-6.02 6.58-6.02 3.45 0 6.14 2.46 6.14 5.75 0 3.43-2.16 6.19-5.16 6.19-1.01 0-1.96-.52-2.28-1.14l-.62 2.37c-.22.86-.83 1.94-1.24 2.6.93.29 1.92.44 2.95.44 5.52 0 10-4.48 10-10S17.52 2 12 2z"/>',
            'email'     => '<path d="M2 4h20c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H2c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm10 7L3.5 6h17L12 11zm0 2.2L3 7.5V18h18V7.5l-9 5.7z"/>',
        ];

        $path = $paths[$network] ?? '';
        if ('' === $path) {
            return '';
        }

        return '<svg class="gratis-share-icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" fill="currentColor">' . $path . '</svg>';
    }

    /**
     * Render a set of share buttons.
     *
     * @param array<string,mixed> $args context, url, title, image, networks,
     *                                  label, counts.
     */
    public static function render(array $args = []): string {
        $context  = isset($args['context']) ? (string) $args['context'] : 'inline';
        $url      = isset($args['url']) ? (string) $args['url'] : (string) get_permalink();
        $title    = isset($args['title']) ? (string) $args['title'] : (string) get_the_title();
        $image    = isset($args['image']) ? (string) $args['image'] : (string) self::featuredImage();
        $networks = isset($args['networks']) && is_array($args['networks']) ? $args['networks'] : Settings::getList('networks');

        if ('' === $url) {
            return '';
        }

        // Keep only known networks, preserving the requested order.
        $labels   = self::networkLabels();
        $networks = array_values(array_filter($networks, static fn($n): bool => isset($labels[$n])));
        if (empty($networks)) {
            return '';
        }

        $show_label = array_key_exists('label', $args) ? '' !== (string) $args['label'] : Settings::getBool('show_label', true);
        $label_text = array_key_exists('label', $args) ? (string) $args['label'] : Settings::getString('label', __('Share', 'gratis-social'));
        $show_count = array_key_exists('counts', $args) ? (bool) $args['counts'] : Settings::getBool('show_counts', false);

        $icon_style = Settings::getString('icon_style', 'solid');
        $size       = Settings::getString('size', 'medium');
        $color      = Settings::getString('color', 'brand');
        $accent     = Settings::getString('accent', '#1f6feb');

        // Assets are needed once we know we're rendering something.
        self::$assetsNeeded = true;
        self::enqueueAssets();

        $wrap_classes = [
            'gratis-share',
            'gratis-share--' . sanitize_html_class($context),
            'gratis-share--' . sanitize_html_class($size),
            'gratis-share--style-' . sanitize_html_class($icon_style),
            'gratis-share--color-' . sanitize_html_class($color),
        ];

        $style_attr = '';
        $safe_accent = sanitize_hex_color($accent);
        if (null !== $safe_accent) {
            $style_attr = ' style="--gratis-share-accent:' . esc_attr($safe_accent) . ';"';
        }

        $aria = 'floating' === $context
            ? esc_attr__('Share this post (floating)', 'gratis-social')
            : esc_attr__('Share this post', 'gratis-social');

        $html  = '<nav class="' . esc_attr(implode(' ', $wrap_classes)) . '"' . $style_attr . ' aria-label="' . $aria . '">';

        if ($show_label && 'floating' !== $context) {
            $html .= '<span class="gratis-share-label">' . esc_html($label_text) . '</span>';
        }

        $html .= '<ul class="gratis-share-list">';
        foreach ($networks as $network) {
            $intent  = self::intentUrl($network, $url, $title, $image);
            $name    = $labels[$network];
            $is_mail = 'email' === $network;

            // translators: %s is the social network name.
            $aria_label = sprintf(__('Share on %s', 'gratis-social'), $name);
            if ($is_mail) {
                $aria_label = __('Share via email', 'gratis-social');
            }

            $rel    = $is_mail ? '' : ' rel="noopener nofollow"';
            $target = $is_mail ? '' : ' target="_blank"';

            $count_html = '';
            if ($show_count) {
                $count = ShareCount::get($network, $url);
                if (null !== $count) {
                    $count_html = '<span class="gratis-share-count">' . esc_html(ShareCount::format($count)) . '</span>';
                }
            }

            $html .= '<li class="gratis-share-item">';
            $html .= '<a class="gratis-share-btn gratis-share-' . esc_attr($network) . '" href="' . esc_url($intent) . '"' . $target . $rel . ' aria-label="' . esc_attr($aria_label) . '">';
            $html .= self::icon($network);
            $html .= '<span class="gratis-share-name">' . esc_html($name) . '</span>';
            $html .= $count_html;
            $html .= '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Featured image URL for the current post, if any.
     */
    private static function featuredImage(): string {
        $id = get_the_ID();
        if (!$id || !has_post_thumbnail($id)) {
            return '';
        }
        $src = get_the_post_thumbnail_url($id, 'full');
        return is_string($src) ? $src : '';
    }

    /**
     * Register and enqueue the stylesheet. Safe to call multiple times.
     */
    public static function enqueueAssets(): void {
        if (!wp_style_is('gratis-social', 'registered')) {
            wp_register_style(
                'gratis-social',
                GRATIS_SOCIAL_URL . 'assets/social.css',
                [],
                GRATIS_SOCIAL_VERSION
            );
        }

        // During wp_enqueue_scripts, decide up front for auto-display contexts.
        if (!self::$assetsNeeded && doing_action('wp_enqueue_scripts')) {
            $display = Settings::getString('display', 'inline');
            if ('none' === $display) {
                return;
            }
            if (is_singular()) {
                $post_types = Settings::getList('post_types');
                if (empty($post_types) || is_singular($post_types)) {
                    self::$assetsNeeded = true;
                }
            }
        }

        if (self::$assetsNeeded) {
            wp_enqueue_style('gratis-social');
        }
    }
}
