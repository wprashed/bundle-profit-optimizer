<?php

if (! defined('ABSPATH')) {
    exit;
}

class BPO_Admin {
    private BPO_Optimizer $optimizer;

    private BPO_Plugin $plugin;

    public function __construct(BPO_Optimizer $optimizer, BPO_Plugin $plugin) {
        $this->optimizer = $optimizer;
        $this->plugin = $plugin;
    }

    public function register_admin_menu(): void {
        add_submenu_page(
            'woocommerce',
            __('Bundle Profit Optimizer', BPO_TEXT_DOMAIN),
            __('Bundle Optimizer', BPO_TEXT_DOMAIN),
            'manage_woocommerce',
            'bundle-profit-optimizer',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'woocommerce_page_bundle-profit-optimizer') {
            return;
        }

        wp_enqueue_style(
            'bpo-admin',
            BPO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            BPO_VERSION
        );
    }

    public function render_admin_page(): void {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $saved_settings = $this->plugin->get_settings();
        $form_data = $this->build_form_data($saved_settings);
        $result = null;

        if (isset($_POST['bpo_optimize_submit'])) {
            check_admin_referer('bpo_optimize_action', 'bpo_optimize_nonce');
            $form_data = $this->sanitize_form_data(wp_unslash($_POST));
            $result = $this->optimizer->optimize_bundle(
                $form_data['product_ids'],
                $form_data['cost_overrides'],
                $form_data['params']
            );

            if (! empty($form_data['save_as_defaults'])) {
                $sanitized_params = $this->plugin->sanitize_settings($form_data['params']);
                update_option('bpo_settings', $sanitized_params);
                $form_data['params'] = $sanitized_params;
                $saved_settings = $sanitized_params;
            }
        }

        $this->render_page_markup($form_data, $result, $saved_settings);
    }

    /**
     * @param array<string,float> $settings
     * @return array<string,mixed>
     */
    private function build_form_data(array $settings): array {
        return [
            'product_ids_raw' => '',
            'cost_overrides_raw' => '',
            'save_as_defaults' => false,
            'product_ids' => [],
            'cost_overrides' => [],
            'params' => $settings,
        ];
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function sanitize_form_data(array $post): array {
        $saved_settings = $this->plugin->get_settings();

        $product_ids_raw = sanitize_textarea_field((string) ($post['product_ids'] ?? ''));
        $cost_overrides_raw = sanitize_textarea_field((string) ($post['cost_overrides'] ?? ''));

        return [
            'product_ids_raw' => $product_ids_raw,
            'cost_overrides_raw' => $cost_overrides_raw,
            'save_as_defaults' => ! empty($post['save_as_defaults']),
            'product_ids' => $this->parse_product_ids($product_ids_raw),
            'cost_overrides' => $this->parse_cost_overrides($cost_overrides_raw),
            'params' => [
                'base_conversion_rate' => $this->safe_float($post['base_conversion_rate'] ?? $saved_settings['base_conversion_rate']),
                'price_elasticity' => $this->safe_float($post['price_elasticity'] ?? $saved_settings['price_elasticity']),
                'min_discount' => $this->safe_float($post['min_discount'] ?? $saved_settings['min_discount']),
                'max_discount' => $this->safe_float($post['max_discount'] ?? $saved_settings['max_discount']),
                'discount_step' => $this->safe_float($post['discount_step'] ?? $saved_settings['discount_step']),
                'default_cost_ratio' => $this->safe_float($post['default_cost_ratio'] ?? $saved_settings['default_cost_ratio']),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $form_data
     * @param array<string,mixed>|null $result
     * @param array<string,float> $saved_settings
     */
    private function render_page_markup(array $form_data, ?array $result, array $saved_settings): void {
        $action_url = admin_url('admin.php?page=bundle-profit-optimizer');
        ?>
        <div class="wrap bpo-wrap">
            <h1><?php echo esc_html__('Bundle Profit Optimizer', BPO_TEXT_DOMAIN); ?></h1>
            <p><?php echo esc_html__('Estimate the bundle price that maximizes expected margin per visitor, using your conversion assumptions.', BPO_TEXT_DOMAIN); ?></p>

            <form method="post" action="<?php echo esc_url($action_url); ?>" class="bpo-form">
                <?php wp_nonce_field('bpo_optimize_action', 'bpo_optimize_nonce'); ?>

                <div class="bpo-grid">
                    <section class="bpo-card">
                        <h2><?php echo esc_html__('Bundle Products', BPO_TEXT_DOMAIN); ?></h2>
                        <p class="description"><?php echo esc_html__('Enter product IDs separated by commas, spaces, or new lines.', BPO_TEXT_DOMAIN); ?></p>
                        <textarea name="product_ids" rows="4" class="large-text code" placeholder="<?php echo esc_attr__('12, 44, 93', BPO_TEXT_DOMAIN); ?>"><?php echo esc_textarea((string) $form_data['product_ids_raw']); ?></textarea>
                    </section>

                    <section class="bpo-card">
                        <h2><?php echo esc_html__('Cost Overrides (Optional)', BPO_TEXT_DOMAIN); ?></h2>
                        <p class="description"><?php echo esc_html__('Set specific unit costs per product. One per line: product_id=cost', BPO_TEXT_DOMAIN); ?></p>
                        <textarea name="cost_overrides" rows="4" class="large-text code" placeholder="<?php echo esc_attr__("12=8.5\n44=16.25", BPO_TEXT_DOMAIN); ?>"><?php echo esc_textarea((string) $form_data['cost_overrides_raw']); ?></textarea>
                        <p class="description"><?php echo esc_html__('If omitted, cost uses product meta (_bpo_unit_cost or _wc_cog_cost) and then default cost ratio.', BPO_TEXT_DOMAIN); ?></p>
                    </section>
                </div>

                <section class="bpo-card">
                    <h2><?php echo esc_html__('Optimization Assumptions', BPO_TEXT_DOMAIN); ?></h2>
                    <div class="bpo-fields">
                        <?php $this->render_number_field('base_conversion_rate', __('Base Conversion Rate', BPO_TEXT_DOMAIN), $form_data['params']['base_conversion_rate'], '0.001', '1', '0.001', __('At 0% discount, use decimal format (e.g. 0.06 = 6%).', BPO_TEXT_DOMAIN)); ?>
                        <?php $this->render_number_field('price_elasticity', __('Price Elasticity Factor', BPO_TEXT_DOMAIN), $form_data['params']['price_elasticity'], '0.1', '20', '0.1', __('Higher values increase conversion faster as discount increases.', BPO_TEXT_DOMAIN)); ?>
                        <?php $this->render_number_field('min_discount', __('Min Discount (%)', BPO_TEXT_DOMAIN), $form_data['params']['min_discount'], '0', '95', '0.1'); ?>
                        <?php $this->render_number_field('max_discount', __('Max Discount (%)', BPO_TEXT_DOMAIN), $form_data['params']['max_discount'], '0', '99', '0.1'); ?>
                        <?php $this->render_number_field('discount_step', __('Discount Step (%)', BPO_TEXT_DOMAIN), $form_data['params']['discount_step'], '0.1', '20', '0.1'); ?>
                        <?php $this->render_number_field('default_cost_ratio', __('Default Cost Ratio', BPO_TEXT_DOMAIN), $form_data['params']['default_cost_ratio'], '0.05', '0.95', '0.01', __('Fallback unit cost = regular price * ratio.', BPO_TEXT_DOMAIN)); ?>
                    </div>

                    <label class="bpo-checkbox">
                        <input type="checkbox" name="save_as_defaults" value="1" <?php checked(! empty($form_data['save_as_defaults'])); ?> />
                        <?php echo esc_html__('Save assumptions as defaults', BPO_TEXT_DOMAIN); ?>
                    </label>

                    <p>
                        <button type="submit" name="bpo_optimize_submit" class="button button-primary"><?php echo esc_html__('Run Optimization', BPO_TEXT_DOMAIN); ?></button>
                    </p>
                </section>
            </form>

            <?php if ($result !== null) : ?>
                <?php $this->render_results($result); ?>
            <?php else : ?>
                <section class="bpo-card bpo-muted">
                    <h2><?php echo esc_html__('Current Defaults', BPO_TEXT_DOMAIN); ?></h2>
                    <p>
                        <?php
                        printf(
                            esc_html__('Base conversion: %1$s%%, elasticity: %2$s, discount range: %3$s%%-%4$s%% (step %5$s%%), default cost ratio: %6$s', BPO_TEXT_DOMAIN),
                            esc_html(number_format_i18n((float) $saved_settings['base_conversion_rate'] * 100, 2)),
                            esc_html(number_format_i18n((float) $saved_settings['price_elasticity'], 2)),
                            esc_html(number_format_i18n((float) $saved_settings['min_discount'], 2)),
                            esc_html(number_format_i18n((float) $saved_settings['max_discount'], 2)),
                            esc_html(number_format_i18n((float) $saved_settings['discount_step'], 2)),
                            esc_html(number_format_i18n((float) $saved_settings['default_cost_ratio'], 2))
                        );
                        ?>
                    </p>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $result
     */
    private function render_results(array $result): void {
        if (! empty($result['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html((string) $result['error']) . '</p></div>';
            return;
        }

        $recommended = $result['recommended'];
        $price_html = wp_kses_post(wc_price((float) $recommended['bundle_price']));
        $margin_value_html = wp_kses_post(wc_price((float) $recommended['margin_value']));
        $expected_margin_html = wp_kses_post(wc_price((float) $recommended['expected_margin_per_visitor']));
        $total_regular_html = wp_kses_post(wc_price((float) $result['total_regular_price']));
        $total_cost_html = wp_kses_post(wc_price((float) $result['total_unit_cost']));
        ?>
        <section class="bpo-card bpo-result-card">
            <h2><?php echo esc_html__('Recommended Bundle Price', BPO_TEXT_DOMAIN); ?></h2>
            <div class="bpo-recommendation">
                <div>
                    <span class="bpo-kpi-label"><?php echo esc_html__('Bundle Price', BPO_TEXT_DOMAIN); ?></span>
                    <strong><?php echo wp_kses_post($price_html); ?></strong>
                </div>
                <div>
                    <span class="bpo-kpi-label"><?php echo esc_html__('Discount', BPO_TEXT_DOMAIN); ?></span>
                    <strong><?php echo esc_html(number_format_i18n((float) $recommended['discount'], 2)); ?>%</strong>
                </div>
                <div>
                    <span class="bpo-kpi-label"><?php echo esc_html__('Margin', BPO_TEXT_DOMAIN); ?></span>
                    <strong><?php echo wp_kses_post($margin_value_html); ?> (<?php echo esc_html(number_format_i18n((float) $recommended['margin_percent'], 2)); ?>%)</strong>
                </div>
                <div>
                    <span class="bpo-kpi-label"><?php echo esc_html__('Expected Margin / Visitor', BPO_TEXT_DOMAIN); ?></span>
                    <strong><?php echo wp_kses_post($expected_margin_html); ?></strong>
                </div>
                <div>
                    <span class="bpo-kpi-label"><?php echo esc_html__('Estimated Conversion', BPO_TEXT_DOMAIN); ?></span>
                    <strong><?php echo esc_html(number_format_i18n((float) $recommended['conversion_rate'] * 100, 2)); ?>%</strong>
                </div>
            </div>

            <p class="description">
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: 1: total regular bundle price, 2: total unit cost. */
                        __('Total regular price: %1$s | Total unit cost: %2$s', BPO_TEXT_DOMAIN),
                        $total_regular_html,
                        $total_cost_html
                    )
                );
                ?>
            </p>
        </section>

        <section class="bpo-card">
            <h2><?php echo esc_html__('Scenario Table', BPO_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped bpo-table">
                <thead>
                <tr>
                    <th><?php echo esc_html__('Discount %', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Bundle Price', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Margin', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Margin %', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Conversion %', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Expected Margin / Visitor', BPO_TEXT_DOMAIN); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['scenarios'] as $scenario) : ?>
                    <?php
                    $is_best = abs((float) $scenario['discount'] - (float) $recommended['discount']) < 0.0001;
                    ?>
                    <tr<?php echo $is_best ? ' class="' . esc_attr('bpo-best-row') . '"' : ''; ?>>
                        <td><?php echo esc_html(number_format_i18n((float) $scenario['discount'], 2)); ?></td>
                        <td><?php echo wp_kses_post(wc_price((float) $scenario['bundle_price'])); ?></td>
                        <td><?php echo wp_kses_post(wc_price((float) $scenario['margin_value'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n((float) $scenario['margin_percent'], 2)); ?>%</td>
                        <td><?php echo esc_html(number_format_i18n((float) $scenario['conversion_rate'] * 100, 2)); ?>%</td>
                        <td><strong><?php echo wp_kses_post(wc_price((float) $scenario['expected_margin_per_visitor'])); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="bpo-card">
            <h2><?php echo esc_html__('Bundle Inputs Used', BPO_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php echo esc_html__('Product', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Regular Price', BPO_TEXT_DOMAIN); ?></th>
                    <th><?php echo esc_html__('Unit Cost', BPO_TEXT_DOMAIN); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['products'] as $row) : ?>
                    <tr>
                        <td><?php echo esc_html('#' . (int) $row['id'] . ' ' . (string) $row['name']); ?></td>
                        <td><?php echo wp_kses_post(wc_price((float) $row['regular_price'])); ?></td>
                        <td><?php echo wp_kses_post(wc_price((float) $row['unit_cost'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
    }

    private function render_number_field(string $name, string $label, float $value, string $min, string $max, string $step, string $description = ''): void {
        ?>
        <label class="bpo-field">
            <span><?php echo esc_html($label); ?></span>
            <input
                type="number"
                name="<?php echo esc_attr($name); ?>"
                value="<?php echo esc_attr((string) $value); ?>"
                min="<?php echo esc_attr($min); ?>"
                max="<?php echo esc_attr($max); ?>"
                step="<?php echo esc_attr($step); ?>"
            />
            <?php if ($description !== '') : ?>
                <small><?php echo esc_html($description); ?></small>
            <?php endif; ?>
        </label>
        <?php
    }

    /**
     * @return int[]
     */
    private function parse_product_ids(string $raw): array {
        $parts = preg_split('/[\s,]+/', trim($raw));
        if (! is_array($parts)) {
            return [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $id = absint($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<int,float>
     */
    private function parse_cost_overrides(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        if (! is_array($lines)) {
            return [];
        }

        $overrides = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$id_raw, $cost_raw] = array_map('trim', explode('=', $line, 2));
            $id = absint($id_raw);
            $cost = $this->safe_float($cost_raw);

            if ($id > 0 && $cost >= 0) {
                $overrides[$id] = $cost;
            }
        }

        return $overrides;
    }

    /**
     * @param mixed $value
     */
    private function safe_float($value): float {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }
}
