<?php

if (! defined('ABSPATH')) {
    exit;
}

class BPO_Optimizer {
    /**
     * @param int[] $product_ids
     * @param array<int,float> $cost_overrides
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function optimize_bundle(array $product_ids, array $cost_overrides, array $params): array {
        $products = $this->get_valid_products($product_ids);

        if (empty($products)) {
            return [
                'error' => __('Select at least one valid WooCommerce product.', BPO_TEXT_DOMAIN),
            ];
        }

        $defaults = $this->default_params();
        $params = wp_parse_args($params, $defaults);

        $base_conversion_rate = $this->clamp_float((float) $params['base_conversion_rate'], 0.001, 1);
        $elasticity = $this->clamp_float((float) $params['price_elasticity'], 0.1, 20);
        $min_discount = $this->clamp_float((float) $params['min_discount'], 0, 95);
        $max_discount = $this->clamp_float((float) $params['max_discount'], $min_discount, 99);
        $discount_step = $this->clamp_float((float) $params['discount_step'], 0.1, 20);

        $total_regular_price = 0.0;
        $total_unit_cost = 0.0;
        $product_breakdown = [];

        foreach ($products as $product) {
            $product_id = $product->get_id();
            $regular_price = $this->get_product_regular_price($product);
            $unit_cost = $this->resolve_product_cost($product, $cost_overrides, (float) $params['default_cost_ratio']);

            $total_regular_price += $regular_price;
            $total_unit_cost += $unit_cost;

            $product_breakdown[] = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'regular_price' => $regular_price,
                'unit_cost' => $unit_cost,
            ];
        }

        if ($total_regular_price <= 0) {
            return [
                'error' => __('Selected products have no usable price data for optimization.', BPO_TEXT_DOMAIN),
            ];
        }

        $scenarios = [];
        for ($discount = $min_discount; $discount <= ($max_discount + 0.0001); $discount += $discount_step) {
            $discount = round($discount, 2);
            $discount_decimal = $discount / 100;

            $bundle_price = $total_regular_price * (1 - $discount_decimal);
            $margin_value = $bundle_price - $total_unit_cost;
            $margin_percent = $bundle_price > 0 ? ($margin_value / $bundle_price) * 100 : 0;

            $conversion_rate = $base_conversion_rate * exp($elasticity * $discount_decimal);
            $conversion_rate = $this->clamp_float($conversion_rate, 0.001, 0.999);

            $expected_margin_per_visitor = $margin_value * $conversion_rate;
            $expected_revenue_per_visitor = $bundle_price * $conversion_rate;

            $scenarios[] = [
                'discount' => $discount,
                'bundle_price' => $bundle_price,
                'margin_value' => $margin_value,
                'margin_percent' => $margin_percent,
                'conversion_rate' => $conversion_rate,
                'expected_margin_per_visitor' => $expected_margin_per_visitor,
                'expected_revenue_per_visitor' => $expected_revenue_per_visitor,
            ];
        }

        if (empty($scenarios)) {
            return [
                'error' => __('No scenarios were generated. Check your discount range.', BPO_TEXT_DOMAIN),
            ];
        }

        $recommended = $this->pick_best_scenario($scenarios);

        return [
            'products' => $product_breakdown,
            'total_regular_price' => $total_regular_price,
            'total_unit_cost' => $total_unit_cost,
            'recommended' => $recommended,
            'scenarios' => $scenarios,
            'assumptions' => [
                'base_conversion_rate' => $base_conversion_rate,
                'price_elasticity' => $elasticity,
                'min_discount' => $min_discount,
                'max_discount' => $max_discount,
                'discount_step' => $discount_step,
            ],
        ];
    }

    /**
     * @return array<string,float>
     */
    public function default_params(): array {
        return [
            'base_conversion_rate' => 0.06,
            'price_elasticity' => 4.5,
            'min_discount' => 0,
            'max_discount' => 35,
            'discount_step' => 2.5,
            'default_cost_ratio' => 0.45,
        ];
    }

    /**
     * @param int[] $product_ids
     * @return WC_Product[]
     */
    private function get_valid_products(array $product_ids): array {
        $products = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product((int) $product_id);
            if (! $product instanceof WC_Product) {
                continue;
            }

            if (! $product->is_purchasable()) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    private function get_product_regular_price(WC_Product $product): float {
        $regular = (float) $product->get_regular_price();

        if ($regular > 0) {
            return $regular;
        }

        $price = (float) $product->get_price();
        if ($price > 0) {
            return $price;
        }

        if ($product->is_type('variable') && $product instanceof WC_Product_Variable) {
            $variation_price = (float) $product->get_variation_regular_price('min', true);
            if ($variation_price > 0) {
                return $variation_price;
            }
        }

        return 0.0;
    }

    /**
     * @param array<int,float> $cost_overrides
     */
    private function resolve_product_cost(WC_Product $product, array $cost_overrides, float $default_cost_ratio): float {
        $product_id = $product->get_id();

        if (isset($cost_overrides[$product_id]) && $cost_overrides[$product_id] >= 0) {
            return (float) $cost_overrides[$product_id];
        }

        $meta_cost = (float) get_post_meta($product_id, '_bpo_unit_cost', true);
        if ($meta_cost > 0) {
            return $meta_cost;
        }

        $cogs_cost = (float) get_post_meta($product_id, '_wc_cog_cost', true);
        if ($cogs_cost > 0) {
            return $cogs_cost;
        }

        $regular = $this->get_product_regular_price($product);
        return $regular * $this->clamp_float($default_cost_ratio, 0.05, 0.95);
    }

    /**
     * @param array<int,array<string,float>> $scenarios
     * @return array<string,float>
     */
    private function pick_best_scenario(array $scenarios): array {
        $best = $scenarios[0];

        foreach ($scenarios as $scenario) {
            if ($scenario['expected_margin_per_visitor'] > $best['expected_margin_per_visitor']) {
                $best = $scenario;
                continue;
            }

            if (
                abs($scenario['expected_margin_per_visitor'] - $best['expected_margin_per_visitor']) < 0.00001
                && $scenario['bundle_price'] > $best['bundle_price']
            ) {
                $best = $scenario;
            }
        }

        return $best;
    }

    private function clamp_float(float $value, float $min, float $max): float {
        return max($min, min($max, $value));
    }
}
