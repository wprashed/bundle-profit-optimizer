=== Bundle Profit Optimizer ===
Contributors: wprashed
Tags: woocommerce, bundle pricing, conversion optimization, profit margin, ecommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recommend the best WooCommerce bundle price to maximize expected margin per visitor.

== Description ==
Bundle Profit Optimizer helps WooCommerce merchants estimate the best bundle discount by balancing margin and conversion behavior.

The plugin calculates scenario outcomes across a discount range and recommends the bundle price with the highest expected margin per visitor.

Key features:
- Select bundle items by WooCommerce product IDs.
- Add optional per-product unit cost overrides.
- Configure conversion assumptions (base conversion + price elasticity).
- Evaluate discount ranges using configurable min, max, and step values.
- Save optimization assumptions as reusable defaults.
- View a detailed scenario table with margin and conversion estimates.

Author: Rashed Hossain (https://rashed.im/)

== Installation ==
1. Upload the `bundle-profit-optimizer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Make sure WooCommerce is active.
4. Go to `WooCommerce -> Bundle Optimizer`.
5. Enter product IDs and run optimization.

== Frequently Asked Questions ==
= Does this plugin change live product prices automatically? =
No. It provides pricing recommendations and scenario analysis only.

= How is unit cost determined? =
The plugin uses this order:
1. Cost overrides entered in the optimizer form.
2. Product meta `_bpo_unit_cost`.
3. Product meta `_wc_cog_cost`.
4. Fallback: `regular_price * default_cost_ratio`.

= Is WooCommerce required? =
Yes. The plugin requires WooCommerce to be active.

== Screenshots ==
1. Bundle Optimizer admin page with inputs for products, costs, and assumptions.
2. Recommended bundle price and KPI summary.
3. Scenario comparison table by discount level.

== Changelog ==
= 1.0.0 =
* Initial release.
* WooCommerce admin tool for bundle price optimization.
* Scenario-based margin and conversion analysis.

== Upgrade Notice ==
= 1.0.0 =
Initial stable release.
