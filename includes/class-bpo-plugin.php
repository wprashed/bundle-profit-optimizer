<?php

if (! defined('ABSPATH')) {
    exit;
}

final class BPO_Plugin {
    private const OPTION_KEY = 'bpo_settings';

    private static ?self $instance = null;

    private BPO_Optimizer $optimizer;

    private BPO_Admin $admin;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->optimizer = new BPO_Optimizer();
        $this->admin = new BPO_Admin($this->optimizer, $this);
    }

    public function bootstrap(): void {
        load_plugin_textdomain(BPO_TEXT_DOMAIN, false, dirname(plugin_basename(BPO_PLUGIN_FILE)) . '/languages');

        add_action('admin_init', [$this, 'register_settings']);

        if (! $this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        add_action('admin_menu', [$this->admin, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_assets']);
    }

    public function register_settings(): void {
        register_setting(
            'bpo_settings_group',
            self::OPTION_KEY,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array<string,float>
     */
    public function sanitize_settings($input): array {
        if (! is_array($input)) {
            $input = [];
        }

        $input = map_deep(wp_unslash($input), 'sanitize_text_field');
        $defaults = $this->default_settings();

        return [
            'base_conversion_rate' => $this->clamp_float((float) ($input['base_conversion_rate'] ?? $defaults['base_conversion_rate']), 0.001, 1),
            'price_elasticity' => $this->clamp_float((float) ($input['price_elasticity'] ?? $defaults['price_elasticity']), 0.1, 20),
            'min_discount' => $this->clamp_float((float) ($input['min_discount'] ?? $defaults['min_discount']), 0, 95),
            'max_discount' => $this->clamp_float((float) ($input['max_discount'] ?? $defaults['max_discount']), 0, 99),
            'discount_step' => $this->clamp_float((float) ($input['discount_step'] ?? $defaults['discount_step']), 0.1, 20),
            'default_cost_ratio' => $this->clamp_float((float) ($input['default_cost_ratio'] ?? $defaults['default_cost_ratio']), 0.05, 0.95),
        ];
    }

    /**
     * @return array<string,float>
     */
    public function get_settings(): array {
        $settings = get_option(self::OPTION_KEY, []);
        $settings = is_array($settings) ? $settings : [];

        return wp_parse_args($settings, $this->default_settings());
    }

    public function woocommerce_missing_notice(): void {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html__('Bundle Profit Optimizer requires WooCommerce to be active.', BPO_TEXT_DOMAIN) . '</p></div>';
    }

    private function default_settings(): array {
        return $this->optimizer->default_params();
    }

    private function is_woocommerce_active(): bool {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }

    private function clamp_float(float $value, float $min, float $max): float {
        return max($min, min($max, $value));
    }
}
