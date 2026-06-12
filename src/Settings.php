<?php declare(strict_types=1);

namespace Gratis\Social;

defined('ABSPATH') || exit;

/**
 * Global Gratis Social settings, stored as a single option array and exposed
 * through the WordPress Settings API on a submenu under Settings.
 *
 * Controls which networks appear, how/where buttons are displayed, the icon
 * style/size/colour, the optional label, and whether share counts are shown.
 */
final class Settings {

    /** Option name holding all settings. */
    public const OPTION = 'gratis_social_settings';

    /** Settings group / page slug. */
    private const GROUP = 'gratis_social_settings_group';
    private const PAGE  = 'gratis-social-settings';

    /** Networks the plugin knows how to share to, in display order. */
    public const NETWORKS = ['facebook', 'x', 'linkedin', 'pinterest', 'email'];

    /**
     * Default values for every setting.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        return [
            'networks'    => ['facebook', 'x', 'linkedin', 'pinterest', 'email'],
            'display'     => 'inline',   // inline | floating | both | none.
            'post_types'  => ['post'],
            'icon_style'  => 'solid',    // solid | outline.
            'size'        => 'medium',   // small | medium | large.
            'color'       => 'brand',    // brand | mono.
            'accent'      => '#1f6feb',
            'show_label'  => 1,
            'label'       => __('Share', 'gratis-social'),
            'show_counts' => 0,
        ];
    }

    /**
     * Fetch the merged settings array (stored values over defaults), then run
     * it through a filter so other code can override at runtime.
     *
     * @return array<string,mixed>
     */
    public static function all(): array {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $settings = array_merge(self::defaults(), $stored);

        /**
         * Filter the resolved Gratis Social settings.
         *
         * @param array<string,mixed> $settings Merged settings.
         */
        return (array) apply_filters('gratis_social_settings', $settings);
    }

    /**
     * Boolean accessor for a single setting.
     */
    public static function getBool(string $key, bool $fallback = false): bool {
        $all = self::all();
        return isset($all[$key]) ? (bool) $all[$key] : $fallback;
    }

    /**
     * String accessor for a single setting.
     */
    public static function getString(string $key, string $fallback = ''): string {
        $all = self::all();
        return isset($all[$key]) ? (string) $all[$key] : $fallback;
    }

    /**
     * List accessor for a single setting.
     *
     * @return array<int,string>
     */
    public static function getList(string $key): array {
        $all = self::all();
        return isset($all[$key]) && is_array($all[$key]) ? array_values(array_map('strval', $all[$key])) : [];
    }

    /**
     * Register the settings submenu page.
     */
    public static function registerMenu(): void {
        add_options_page(
            __('Gratis Social', 'gratis-social'),
            __('Gratis Social', 'gratis-social'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'renderPage']
        );
    }

    /**
     * Register the option and its sanitisation callback.
     */
    public static function registerSettings(): void {
        register_setting(
            self::GROUP,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
    }

    /**
     * Sanitise the incoming settings array against known whitelists.
     *
     * @param mixed $input Raw option input.
     * @return array<string,mixed>
     */
    public static function sanitize($input): array {
        $input    = is_array($input) ? $input : [];
        $defaults = self::defaults();

        $networks = [];
        if (isset($input['networks']) && is_array($input['networks'])) {
            foreach (self::NETWORKS as $network) {
                if (in_array($network, $input['networks'], true)) {
                    $networks[] = $network;
                }
            }
        }
        if (empty($networks)) {
            $networks = $defaults['networks'];
        }

        $post_types = [];
        if (isset($input['post_types']) && is_array($input['post_types'])) {
            foreach ($input['post_types'] as $pt) {
                $pt = sanitize_key((string) $pt);
                if ('' !== $pt && post_type_exists($pt)) {
                    $post_types[] = $pt;
                }
            }
        }
        if (empty($post_types)) {
            $post_types = $defaults['post_types'];
        }

        $display     = in_array($input['display'] ?? '', ['inline', 'floating', 'both', 'none'], true) ? $input['display'] : $defaults['display'];
        $icon_style  = in_array($input['icon_style'] ?? '', ['solid', 'outline'], true) ? $input['icon_style'] : $defaults['icon_style'];
        $size        = in_array($input['size'] ?? '', ['small', 'medium', 'large'], true) ? $input['size'] : $defaults['size'];
        $color       = in_array($input['color'] ?? '', ['brand', 'mono'], true) ? $input['color'] : $defaults['color'];
        $accent      = sanitize_hex_color((string) ($input['accent'] ?? '')) ?? $defaults['accent'];

        return [
            'networks'    => array_values(array_unique($networks)),
            'display'     => $display,
            'post_types'  => array_values(array_unique($post_types)),
            'icon_style'  => $icon_style,
            'size'        => $size,
            'color'       => $color,
            'accent'      => $accent,
            'show_label'  => empty($input['show_label']) ? 0 : 1,
            'label'       => sanitize_text_field((string) ($input['label'] ?? $defaults['label'])),
            'show_counts' => empty($input['show_counts']) ? 0 : 1,
        ];
    }

    /**
     * Render the settings screen.
     */
    public static function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-social'));
        }

        $settings      = self::all();
        $network_names = ShareButtons::networkLabels();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis Social', 'gratis-social') . '</h1>';
        echo '<p>' . esc_html__('Social share buttons with no tracking and no third-party JavaScript. Use the [gratis_share] shortcode for manual placement.', 'gratis-social') . '</p>';

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);

        echo '<table class="form-table" role="presentation"><tbody>';

        // Networks.
        echo '<tr><th scope="row">' . esc_html__('Networks', 'gratis-social') . '</th><td><fieldset>';
        foreach (self::NETWORKS as $network) {
            $label = $network_names[$network] ?? ucfirst($network);
            echo '<label style="display:inline-block;margin:0 1em .4em 0;"><input type="checkbox" name="' . esc_attr(self::OPTION) . '[networks][]" value="' . esc_attr($network) . '"' . checked(in_array($network, (array) $settings['networks'], true), true, false) . '> ' . esc_html($label) . '</label>';
        }
        echo '</fieldset></td></tr>';

        // Display mode.
        echo '<tr><th scope="row"><label for="gs_display">' . esc_html__('Display', 'gratis-social') . '</label></th><td>';
        echo '<select id="gs_display" name="' . esc_attr(self::OPTION) . '[display]">';
        $display_options = [
            'inline'   => __('Inline (after post content)', 'gratis-social'),
            'floating' => __('Floating sidebar', 'gratis-social'),
            'both'     => __('Inline + floating sidebar', 'gratis-social'),
            'none'     => __('Shortcode only', 'gratis-social'),
        ];
        foreach ($display_options as $value => $opt_label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($settings['display'], $value, false) . '>' . esc_html($opt_label) . '</option>';
        }
        echo '</select></td></tr>';

        // Post types.
        echo '<tr><th scope="row">' . esc_html__('Show on post types', 'gratis-social') . '</th><td><fieldset>';
        $public_types = get_post_types(['public' => true], 'objects');
        foreach ($public_types as $pt) {
            if ('attachment' === $pt->name) {
                continue;
            }
            echo '<label style="display:inline-block;margin:0 1em .4em 0;"><input type="checkbox" name="' . esc_attr(self::OPTION) . '[post_types][]" value="' . esc_attr($pt->name) . '"' . checked(in_array($pt->name, (array) $settings['post_types'], true), true, false) . '> ' . esc_html($pt->labels->singular_name) . '</label>';
        }
        echo '</fieldset></td></tr>';

        // Icon style.
        echo '<tr><th scope="row">' . esc_html__('Icon style', 'gratis-social') . '</th><td>';
        foreach (['solid' => __('Solid', 'gratis-social'), 'outline' => __('Outline', 'gratis-social')] as $value => $opt_label) {
            echo '<label style="margin-right:1em;"><input type="radio" name="' . esc_attr(self::OPTION) . '[icon_style]" value="' . esc_attr($value) . '"' . checked($settings['icon_style'], $value, false) . '> ' . esc_html($opt_label) . '</label>';
        }
        echo '</td></tr>';

        // Size.
        echo '<tr><th scope="row"><label for="gs_size">' . esc_html__('Size', 'gratis-social') . '</label></th><td>';
        echo '<select id="gs_size" name="' . esc_attr(self::OPTION) . '[size]">';
        foreach (['small' => __('Small', 'gratis-social'), 'medium' => __('Medium', 'gratis-social'), 'large' => __('Large', 'gratis-social')] as $value => $opt_label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($settings['size'], $value, false) . '>' . esc_html($opt_label) . '</option>';
        }
        echo '</select></td></tr>';

        // Colour scheme.
        echo '<tr><th scope="row">' . esc_html__('Colour', 'gratis-social') . '</th><td>';
        foreach (['brand' => __('Brand colours', 'gratis-social'), 'mono' => __('Monochrome (accent)', 'gratis-social')] as $value => $opt_label) {
            echo '<label style="margin-right:1em;"><input type="radio" name="' . esc_attr(self::OPTION) . '[color]" value="' . esc_attr($value) . '"' . checked($settings['color'], $value, false) . '> ' . esc_html($opt_label) . '</label>';
        }
        echo '<br><label>' . esc_html__('Accent colour', 'gratis-social') . ' <input type="text" name="' . esc_attr(self::OPTION) . '[accent]" value="' . esc_attr((string) $settings['accent']) . '" class="regular-text" style="max-width:120px;" placeholder="#1f6feb"></label>';
        echo '<p class="description">' . esc_html__('Accent colour is used for hover (brand) or as the icon colour (monochrome).', 'gratis-social') . '</p>';
        echo '</td></tr>';

        // Label.
        echo '<tr><th scope="row">' . esc_html__('Label', 'gratis-social') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[show_label]" value="1"' . checked((int) $settings['show_label'], 1, false) . '> ' . esc_html__('Show a label before the buttons', 'gratis-social') . '</label> ';
        echo '<input type="text" name="' . esc_attr(self::OPTION) . '[label]" value="' . esc_attr((string) $settings['label']) . '" class="regular-text" style="max-width:160px;">';
        echo '</td></tr>';

        // Share counts.
        echo '<tr><th scope="row">' . esc_html__('Share counts', 'gratis-social') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[show_counts]" value="1"' . checked((int) $settings['show_counts'], 1, false) . '> ' . esc_html__('Display share counts next to buttons', 'gratis-social') . '</label>';
        echo '<p class="description">' . esc_html__('Counts are provided via the gratis_social_share_count filter (no third-party JS). Cached for one hour.', 'gratis-social') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
