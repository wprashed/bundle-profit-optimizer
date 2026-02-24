# Bundle Profit Optimizer

Recommend the best WooCommerce bundle price to maximize expected margin per visitor.

## Overview
Bundle Profit Optimizer is a WooCommerce admin tool that models pricing scenarios for product bundles and recommends the discount/price combination with the highest expected margin per visitor.

## Features
- Bundle-level pricing optimization for WooCommerce products.
- Optional per-product cost overrides.
- Conversion modeling with base conversion rate and price elasticity.
- Configurable discount range (`min`, `max`, `step`).
- Recommended price output plus full scenario table.
- Saved defaults for repeatable optimization runs.

## How It Works
1. Enter bundle product IDs.
2. Optionally provide custom unit costs (`product_id=cost`).
3. Set conversion and discount assumptions.
4. Run optimization.
5. Review the recommended price and scenario comparisons.

## Requirements
- WordPress 6.0+
- PHP 7.4+
- WooCommerce active

## Installation
1. Copy `bundle-profit-optimizer` into `/wp-content/plugins/`.
2. Activate it in **Plugins**.
3. Open **WooCommerce -> Bundle Optimizer**.

## Notes
- The plugin does not auto-update product prices.
- It is an analysis/recommendation tool for pricing decisions.

## Changelog
### 1.0.0
- Initial release with scenario-based bundle pricing optimization.
