<?php
/*
Plugin Name: Woocommerce Combo Discount
Plugin URI: https://www.eqnox.ca/
Description: Add cart discount when you reach certains categorie count
Version: 0.1.1
Author: Jules favreau-Pollender
Author URI: https://www.eqnox.ca/
License: GPLv2 or later
*/


/**
 * PLEASE go see the README.txt for usefull info
 */

// don't load directly
if (!defined('ABSPATH')){
  die('-1');
}

class WCExtendComboClass {

  function __construct() {
    // We safely integrate with WooCommerce with this hook
    add_action('init', array($this, 'integrateWithWC'));

    add_action('woocommerce_add_to_cart', array($this, 'applyMatchedCoupons'));
    add_action('woocommerce_cart_item_removed', array($this, 'applyMatchedCoupons'));
    // add_action('woocommerce_before_cart', array( $this,'applyMatchedCoupons'));
    add_action('woocommerce_update_cart_action_cart_updated', array($this, 'applyMatchedCoupons'));
    // add_action('woocommerce_checkout_update_order_review', array( $this,'applyMatchedCoupons'));
    // add_action('woocommerce_before_checkout_form', array( $this,'applyMatchedCoupons'));

    // Load translation file
    add_action('plugins_loaded', array($this, 'loadTranslation'));

    // Register CSS and JS
    add_action('wp_enqueue_scripts', array($this, 'loadCssAndJs'));
  }

  public function integrateWithWC() {
    /**
     * Check if WooCommerce is active
     **/
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      return;
    }
  }

  public function applyMatchedCoupons() {
    $coupons = $this->getCoupons();
    
    $categoryId2For1Savon = 1084;
    $categoryId2For1Retailles = 1085;
    $category2For1SavonQty = $this->getQuantitesOfProductWithLeastOneCategory(array($categoryId2For1Savon));
    $this->apply2For1CouponIfRequired($coupons, floor($category2For1SavonQty / 2), '2 pour 1 - savon', 8.99);
    $category2For1RetailleQty = $this->getQuantitesOfProductWithLeastOneCategory(array($categoryId2For1Retailles));
    $this->apply2For1CouponIfRequired($coupons, floor($category2For1RetailleQty / 2), '2 pour 1 - retailles', 10.49);
    
    $categoryIdComboFr = 264;
    $categoryIdComboEn = 697;
    $categoryComboQty = $this->getQuantitesOfProductWithLeastOneCategory(array($categoryIdComboFr, $categoryIdComboEn));
    $conflicting2For1AndComboQty = $this->getQuantitesOfProductWithAllCategories(array($categoryIdComboFr, $categoryId2For1Savon));
    $this->applyComboCouponIfRequired($coupons, $categoryComboQty - floor($conflicting2For1AndComboQty / 2) * 2, 'combo');

    $cartAmountTrigger = 35;
    $this->applyFreeGiftCouponIfRequired($coupons, $cartAmountTrigger);
  }

  private function apply2For1CouponIfRequired($coupons, $freeProductQty, $baseCode, $productUnitPrice) {
    $comboCode = $baseCode . ' (' . $freeProductQty . 'x)';
    $this->applyCouponIfRequired($coupons, $baseCode, $comboCode, $freeProductQty * $productUnitPrice);
  }

  private function applyComboCouponIfRequired($coupons, $comboQty, $baseCode) {
    $comboCode = $baseCode . ' ' . $comboQty;
    $this->applyCouponIfRequired($coupons, $baseCode, $comboCode, $this->getComboDiscount($comboQty));
  }

  private function applyCouponIfRequired($coupons, $baseCode, $comboCode, $discount) {
    $this->removePreviousSameTypeDiscount($coupons, $baseCode);

    if($discount == 0){
      return;
    }

    $this->updateOrCreateCoupon($coupons, $comboCode, $discount);
    WC()->cart->apply_coupon($comboCode);
  }

  private function updateOrCreateCoupon($coupons, $comboCode, $discount){
    $foundComboCoupon = current(array_filter($coupons, function($coupon) use ($comboCode) { return $coupon->post_title === $comboCode; }));
    if (!$foundComboCoupon) {
      $this->createCoupon($comboCode, $discount);
      return;
    }
    
    $this->updateCoupon($foundComboCoupon->ID, $discount);
  }

  private function getQuantitesOfProductWithLeastOneCategory($categoryIds) {
    $quantities = WC()->cart->get_cart_item_quantities();
    if ($quantities == 0) {
      return 0;
    }

    $categoryQty = 0;

    // Loop through cart items
    foreach ($quantities as $productId => $quantity) {
      $productCategories = get_the_terms($productId, 'product_cat');
      if (is_array($productCategories) || is_object($productCategories)) {
        foreach ($productCategories as $productCategory) {
          if (in_array($productCategory->term_id, $categoryIds)) {
            $categoryQty += $quantity;
            break;
          }
        }
      }
    }

    return $categoryQty;
  }

  private function getQuantitesOfProductWithAllCategories($categoryIds) {
    $quantities = WC()->cart->get_cart_item_quantities();
    if ($quantities == 0) {
      return 0;
    }

    $categoryQty = 0;

    // Loop through cart items
    foreach ($quantities as $productId => $quantity) {
      $productCategories = get_the_terms($productId, 'product_cat');
      if (is_array($productCategories) || is_object($productCategories)) {
        $allFound = true;
        foreach ($categoryIds as $categoryId) {
          $found = current(array_filter($productCategories, function($productCategory) use($categoryId) { return $productCategory->term_id == $categoryId; }));
          if (!$found) {
            $allFound = false;
          }
        }

        if($allFound){
          $categoryQty += $quantity;
        }
      }
    }

    return $categoryQty;
  }

  private function getComboDiscount($quantity) {
    if ($quantity < 3) {
      return 0;
    }

    if ($quantity <= 4) {
      return 0.66 * $quantity;
    }

    return 1 * $quantity;
  }

  private function removePreviousSameTypeDiscount($coupons, $baseCode) {
    foreach ($coupons as $coupon) {
      if (strpos($coupon->post_title, $baseCode) !== false) {
        WC()->cart->remove_coupon($coupon->post_title);
      }
    }
  }

  private function applyFreeGiftCouponIfRequired($coupons, $cartAmountTrigger) {
    $orderTotal = WC()->cart->get_subtotal();
    $couponCode = 'Cadeau';

    $this->updateOrCreateCoupon($coupons, $couponCode, 0);

    if ($orderTotal >= $cartAmountTrigger) {
      if (!WC()->cart->has_discount($couponCode)) {
        WC()->cart->apply_coupon($couponCode);
      }
    }

    if ($orderTotal < $cartAmountTrigger) {
      if (WC()->cart->has_discount($couponCode)) {
        WC()->cart->remove_coupon($couponCode);
      }
    }
  }

  private function getCoupons() {
    $args = array(
      'posts_per_page' => -1,
      'orderby' => 'title',
      'order' => 'asc',
      'post_type' => 'shop_coupon',
      'post_status' => 'publish',
    );
    return get_posts($args);
  }

  private function createCoupon($couponCode, $amount) {
    $coupon = array(
      'post_title' => $couponCode,
      'post_content' => '',
      'post_status' => 'publish',
      'post_author' => 1,
      'post_type' => 'shop_coupon'
    );

    $newCouponId = wp_insert_post($coupon);
    $this->updateCoupon($newCouponId, $amount);
  }

  private function updateCoupon($couponId, $amount) {
    $discountType = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product

    // Add meta
    update_post_meta($couponId, 'discount_type', $discountType);
    update_post_meta($couponId, 'coupon_amount', $amount);
    update_post_meta($couponId, 'individual_use', 'no');
    update_post_meta($couponId, 'product_ids', '');
    update_post_meta($couponId, 'exclude_product_ids', '');
    update_post_meta($couponId, 'usage_limit', '');
    update_post_meta($couponId, 'expiry_date', '');
    update_post_meta($couponId, 'apply_before_tax', 'yes');
    update_post_meta($couponId, 'free_shipping', 'no');
  }

  public function loadTranslation() {
    load_plugin_textdomain('extend-wc-comboDiscount', false, dirname(plugin_basename(__FILE__)) . '/lang/');
  }

  public function loadCssAndJs() {
    wp_register_style('vc_extend_style', plugins_url('/css/style.css', __FILE__));
    wp_enqueue_style('vc_extend_style');

    wp_enqueue_script('vc_extend_js', plugins_url('/js/custom_script.js', __FILE__), array('jquery'));
  }
}

new WCExtendComboClass();
?>