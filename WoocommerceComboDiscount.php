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
    add_action('init', array( $this, 'load_classes' ) );

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

  public function load_classes() {
    require_once(plugin_dir_path( __FILE__ ) . 'classes/Coupons.php');
  }

  public function applyMatchedCoupons() {
    $coupons = new Coupons();

    $isFreeGiftActive = $this->isBetweenDates('2023-05-26 00:00:00', '2023-05-28 23:59:59');
    $freeGiftCartAmountTrigger = 0;
    $freeGiftProductName = 'Boîte de retailles';

    $category2For1Savon = $this->getCategoryBySlug('2pour1-savon');
    $category2For1Retailles = $this->getCategoryBySlug('2pour1-retailles');
    $category2For1Cupcake = $this->getCategoryBySlug('2pour1-cupcake');

    $this->apply2For1CouponIfRequired($coupons, $category2For1Savon);
    $this->apply2For1CouponIfRequired($coupons, $category2For1Retailles);
    $this->apply2For1CouponIfRequired($coupons, $category2For1Cupcake);
    
    $categoryIdComboFr = $this->getCategoryBySlug('savon-en-barre')->term_id;
    $categoryIdComboEn = $this->getCategoryBySlug('bar-soap')->term_id;
    $categoryComboQty = $this->getQuantitesOfProductWithLeastOneCategory(array($categoryIdComboFr, $categoryIdComboEn));
    $conflicting2For1AndComboQty = $this->getQuantitesOfProductWithAllCategories(array($categoryIdComboFr, $category2For1Savon->term_id));
    $this->applyComboCouponIfRequired($coupons, $categoryComboQty - floor($conflicting2For1AndComboQty / 2) * 2, 'combo');

    if($isFreeGiftActive){
      $this->applyFreeGiftCouponIfRequired($coupons, $freeGiftCartAmountTrigger, $freeGiftProductName);
    }
  }

  private function apply2For1CouponIfRequired($coupons, $category2For1){
    $category2For1Qty = $this->getQuantitesOfProductWithLeastOneCategory(array($category2For1->term_id));
    $freeProductQty = floor($category2For1Qty / 2);
    $baseCode = $category2For1->name;
    $couponCode = $baseCode . ' (' . $freeProductQty . 'x)';
    $productUnitPrice = 0;
    $products = $this->getProductsOfCategory($category2For1->slug);
    $product = array_pop($products);
    if($product != null){
      $productUnitPrice = $product->get_price();
    }
    
    $this->applyCouponIfRequired($coupons, $baseCode, $couponCode, $freeProductQty * $productUnitPrice);
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

    $coupons->updateOrCreateCoupon($comboCode, $discount);
    WC()->cart->apply_coupon($comboCode);
  }

  private function isBetweenDates($dateBegin, $dateEnd){
    date_default_timezone_set('America/New_York');

    $today = strtotime(date("Y-m-d H:i:s"));
    $dateBegin = strtotime($dateBegin);
    $dateEnd = strtotime($dateEnd);
        
    return $today >= $dateBegin && $today <= $dateEnd;
  }

  private function getCategoryBySlug($categorySlug){
    return get_term_by( 'slug', $categorySlug, 'product_cat' );
  }

  private function getProductsOfCategory($categorySlug){
    return wc_get_products(array('category' => array($categorySlug)));
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
    foreach ($coupons->getCoupons() as $coupon) {
      if (strpos($coupon->post_title, $baseCode) !== false) {
        WC()->cart->remove_coupon($coupon->post_title);
      }
    }
  }

  private function applyFreeGiftCouponIfRequired($coupons, $freeGiftCartAmountTrigger, $productName) {
    WC()->cart->calculate_totals(); // Required otherwhise the total is missing the last added product
    $orderTotal = WC()->cart->get_cart_contents_total();
    $couponCode = 'Gratuit ' . $productName;

    $coupons->updateOrCreateCoupon($couponCode, 0);

    if ($orderTotal >= $freeGiftCartAmountTrigger) {
      if (!WC()->cart->has_discount($couponCode)) {
        WC()->cart->apply_coupon($couponCode);
      }
    }

    if ($orderTotal < $freeGiftCartAmountTrigger) {
      if (WC()->cart->has_discount($couponCode)) {
        WC()->cart->remove_coupon($couponCode);
      }
    }
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