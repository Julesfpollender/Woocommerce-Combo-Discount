=== Custom Functions Plugin ===
Contributors: Jules Favreau-Pollender / EQNOX
Developer URI: https://www.eqnox.ca/
Tags: combo, discount
Requires at least: 3.8
Tested up to: 5.9
Stable tag: 0
WC requires at least: 2.2
WC tested up to: 7.2.2

== Description ==

= Features =

This plugin contains all of my awesome custom functions:

* Combo discount by product category
* Free gift coupon after x cart total

= Credits =

* Jules Favreau-Pollender

== Installation ==

1. Download the plugin.
2. Extract all the files.
3. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
4. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= How to enable combo coupon(s) =

1. Set desired category id(s) `$categoryIdComboFr` and `$categoryIdComboEn` for combo to be applied on this category.
2. Change the amount and condition for combo discount in `getComboDiscount` if desired.

= How to enable free gift coupon =

1. Active with `$isFreeGiftActive` boolean (default=true)
2. Change the `$freeGiftCartAmountTrigger` for free gift (default=59$).
3. Change free product name `$freeGiftProductName` to be displayed on the coupon.

= How to enable 2 for 1 coupon(s) =

1. Set desired category slug(s) for 2 for 1 to be applied on this category.

== Changelog ==

= 0.1.1 (2023-03-10) =

* 2 for 1 Discount by product category

= 0.1.0 (2016-02-22) =

* Combo discount by product category
* Free gift coupon after x cart total
