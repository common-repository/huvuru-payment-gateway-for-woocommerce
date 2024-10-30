<?php 
/**
* Plugin Name: Huvuru Payment Gateway for WooCommerce
* Plugin URI: https://huvuru.com
* Author: Huvuru
* Author URI: https://github.com/huvuru
* Description: Huvuru Payment Gateway for WooCommerce allows you to accept online payments from local and international customers
* Version: 1.0.1
* License: 1.0.0
* License URL: http://www.gnu.org/licenses/gpl-2.0.txt
* text-domain: woohuvuru
*/

add_action( 'plugins_loaded', 'woohuvuru_init' );

function woohuvuru_init(){
  
  load_plugin_textdomain( 'woohuvuru', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
  
  // Huvuru checks if woocommerce is installed and available for use
  $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
  if ( ! in_array( 'woocommerce/woocommerce.php', $active_plugins ) ) {
    if ( ! is_multisite() ) return; // nothing more to do. Plugin not available
    
    $site_wide_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
    
    if ( ! in_array( 'woocommerce/woocommerce.php', $site_wide_plugins ) ) return; // nothing more to do. Plugin not available
    
  };
  
  
  if (class_exists('WC_Payment_Gateway')) {
    
    class WC_Huvuru  {
      /**
      * @var Singleton The reference the *Singleton* instance of this class
      */
      private static $instance;
      
      /**
      * Returns the *Singleton* instance of this class.
      *
      * @return Singleton The *Singleton* instance.
      */
      public static function get_instance() {
        if ( null === self::$instance ) {
          self::$instance = new self();
        }
        return self::$instance;
      }
      
      public function __clone(){}
        
        private function __wakeup() {}
          
          public function __construct()
          {
            $this->init();
          }
          public function init()
          {
            require_once dirname( __FILE__ ) . '/helpers/constants.php';
            require_once dirname( __FILE__ ) . '/classes/class-wc-huvuru-gateway.php';
            //require_once dirname( __FILE__ ) . '/classes/class-wc-gateway-paynow-helper.php';
            
            
            /**
            * Custom currency and currency symbol
            */
            add_filter( 'woocommerce_currencies', 'woohuvuru_add_zwl_currency' );
            
            function woohuvuru_add_zwl_currency( $currencies ) {
              $currencies['ZWL'] = __( 'Zimbabwe', 'woohuvuru' );
              return $currencies;
            }
            
            add_filter('woocommerce_currency_symbol', 'woohuvuru_add_zwl_currency_symbol', 10, 2);
            
            function woohuvuru_add_zwl_currency_symbol( $currency_symbol, $currency ) {
              switch( $currency ) {
                case 'ZWL': $currency_symbol = 'ZWL'; break;
              }
              return $currency_symbol;
            }
            
            add_filter('woocommerce_payment_gateways', array ($this, 'woocommerce_huvuru_add_gateway' ) );
          
          }
          
          /**
          * Add the gateway to Huvuru Payment Gateway for WooCommerce
          *
          * @since 1.0.0
          */
          ////woocommerce_huvuru_add_gateway()
          function woocommerce_huvuru_add_gateway( $methods ) {
            $methods[] = 'WC_Huvuru_Gateway';
            return $methods;
          }
          
        
          
        }
        
        WC_Huvuru::get_instance();
      }
      
    }