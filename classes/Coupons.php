<?php

class Coupons {
    private $coupons;

    function __construct() {
        $this->coupons = $this->fetchCoupons();
    }

    public function getCoupons(){
        return $this->coupons;
    }

    public function fetchCoupons() {
        $args = array(
          'posts_per_page' => -1,
          'orderby' => 'title',
          'order' => 'asc',
          'post_type' => 'shop_coupon',
          'post_status' => 'publish',
        );
        return get_posts($args);
      }
    
    public function createCoupon($couponCode, $amount) {
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

    public function updateCoupon($couponId, $amount) {
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

    public function updateOrCreateCoupon($couponCode, $discount){
        $foundComboCoupon = current(array_filter($this->coupons, function($coupon) use ($couponCode) { return $coupon->post_title === $couponCode; }));
        if (!$foundComboCoupon) {
          $this->createCoupon($couponCode, $discount);
          return;
        }
        
        $this->updateCoupon($foundComboCoupon->ID, $discount);
      }
}