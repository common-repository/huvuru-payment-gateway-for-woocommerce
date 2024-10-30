<?php
/**
 * 
 */
class WC_Huvuru_Gateway extends WC_Payment_Gateway
{
  
  public $version = WC_Huvuru_Version;
  function __construct(){
   global $woocommerce;
   $this->id		            	= 'woohuvuru';
   $this->method_title        = __( 'Huvuru', 'woohuvuru' );
   $this->method_description  = 'Have your customers pay you using Huvuru payment option.';
   $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
   $this->has_fields 	= true;

   // this is the name of the class. Mainly used in the callback to trigger wc-api handler in this class
   $this->callback		=  strtolower( get_class($this) );

   // Setup available countries.
   $this->available_countries = array( 'ZW' );

   // Setup available currency codes.
   $this->available_currencies = array( 'USD', 'ZWL' ); // nostro / rtgs ?
   // Load the form fields.
   $this->init_form_fields();

   // Load the settings.
   $this->init_settings();

   // Setup default merchant data.
   $this->merchant_id = $this->get_option('merchant_id');
   $this->LivePublicKey = $this->get_option('merchant_live_public_key');
   $this->LiveSecretKey = $this->get_option('merchant_live_secret_key');
   $this->TestPublicKey = $this->get_option('merchant_test_public_key');
   $this->TestSecretKey = $this->get_option('merchant_test_secret_key');
   $this->test = $this->get_option('test');

   
   $this->initiate_transaction_url = WC_Huvuru_Initiate_Url;


   $this->title = WC_Huvuru_Title;

   // initialize payment url for plugin to call
   $this->response_url	= add_query_arg( 'wc-api', $this->callback , home_url( '/' ) );
   
   // register a handler for wc-api calls to this payment method
   add_action( 'woocommerce_api_' . $this->callback , array( &$this, 'huvuru_checkout_return_handler' ) );
   
   /* 1.6.6 */
   add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

   /* 2.0.0 */
   add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

   add_action( 'woocommerce_thankyou', array( $this, 'woohuvuru_order_cancelled_redirect' ), 10 , 1);
   
   add_action( 'woocommerce_receipt_woohuvuru', array( $this, 'receipt_page' ) );
   
  

   // Check if the base currency supports this gateway.
   if ( ! $this->is_valid_for_use() )
     $this->enabled = false;
   }
  
  
  /**
   * Initialise Gateway Settings Form Fields
   *
   * @since 1.0.0
   */
  function init_form_fields () {

    $this->form_fields = array(
      'enabled' => array(
        'title' => __( 'Enable/Disable', 'woohuvuru' ),
        'label' => __( 'Enable Huvuru', 'woohuvuru' ),
        'type' => 'checkbox',
        'description' => __( 'This controls whether or not Huvuru is enabled within WooCommerce.', 'woohuvuru' ),
        'default' => 'yes'
      ),
      'test' => array(
        'title' => __( 'Enable/Disable', 'woohuvuru' ),
        'label' => __( 'Enable Test', 'woohuvuru' ),
        'type' => 'checkbox',
        'description' => __( 'This controls whether or not Huvuru Sandbox is enabled.', 'woohuvuru' ),
        'default' => 'no'
      ),
      'description' => array(
        'title' => __( 'Description', 'woohuvuru' ),
        'type' => 'text',
        'description' => __( 'This controls the description which the user sees during checkout.', 'woohuvuru' ),
        'default' => ''
      ),
      'merchant_id' => array(
        'title' => __( 'Merchant ID', 'woohuvuru' ),
        'type' => 'text',
        'description' => __( 'This is the merchant ID, received from Huvuru dashboard.', 'woohuvuru' ),
        'default' => ''
      ),
      'merchant_live_public_key' => array(
        'title' => __( 'Live Public Key', 'woohuvuru' ),
        'type' => 'text',
        'description' => __( 'This is the Live Public Key, received from Huvuru dashboard.', 'woohuvuru' ),
        'default' => ''
      ),
      'merchant_live_secret_key' => array(
        'title' => __( 'Live Secret Key', 'woohuvuru' ),
        'type' => 'text',
        'description' => __( 'This is the Live Secret Key, received from Huvuru dashboard.', 'woohuvuru' ),
        'default' => ''
      ),
      'merchant_test_public_key' => array(
        'title' => __( 'Sandbox Public Key', 'woohuvuru' ),
        'type' => 'text',
        'description' => __( 'This is the Sandbox Public Key, received from Huvuru dashboard.', 'woohuvuru' ),
        'default' => ''
      ),
      'merchant_test_secret_key' => array(
        'title' => __( 'Sandbox Secret Key', 'woohuvuru' ),
        'type' => 'text',
        'description' => __( 'This is the Sandbox Secret Key, received from Huvuru dashboard.', 'woohuvuru' ),
        'default' => ''
      )
    );

  } // End init_form_fields()
  
  
  /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()
  
  
  /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
     function is_valid_for_use() {
       global $woocommerce;
       
       $is_available = false;
       
       $user_currency = get_woocommerce_currency();
       
       $is_available_currency = in_array( $user_currency, $this->available_currencies );
       

       
       $authSet = false;
       
       $authSet = $this->get_option('merchant_id') != '' && $this->get_option('merchant_live_public_key') != '' && $this->get_option('merchant_live_secret_key') != '' && $this->get_option('merchant_test_secret_key') != '' && $this->get_option('merchant_test_public_key') != '';

       if ( 
         $is_available_currency 
         && $this->enabled == 'yes' 
         && $authSet
         )
         $is_available = true;
         
         return $is_available;
       } // End is_valid_for_use()
  
  
  /**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// $this->log( '' );
		// $this->log( '', true );

    	?>
    	<h3><?php _e( 'Huvuru', 'woohuvuru' ); ?></h3>
    	<p><?php printf( __( 'Huvuru works by redirecting the user/customer to %sHuvuru%s to enter their payment information.', 'woohuvuru' ), '<a href="https://huvuru.com">', '</a>' ); ?></p>

    	<?php
				
    	if ( in_array( get_woocommerce_currency(), $this->available_currencies) ) {
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table--><?php
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woohuvuru' ); ?></strong> <?php echo sprintf( __( 'Choose United States Dollar ($/USD) or Zimbabwean Dollar ($/ZWL) as your store currency in <a href="%s">Pricing Options</a> to enable the Huvuru Gateway.', 'woohuvuru' ), admin_url( '?page=woocommerce&tab=general' ) ); ?></p></div>
		<?php
		} // End check currency
		?>
    	<?php
    } // End admin_options()
    
    
    /**
	 * There are no payment fields for Huvuru, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
   function payment_fields() {
     if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
       echo wpautop( wptexturize( sanitize_text_field($this->settings['description'] ) ) );
     }
   } // End payment_fields()
    
    
    /**
	 * Process the payment and return the result.
	 * @param int $order_id
	 * @param string $from tells process payment whether the method call is from huvuru return (callback) or not
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {
    
		$order = wc_get_order( $order_id );
  
		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);

	}
  
  
  /**
	 * Reciept page.
	 *
	 * Redirect user to Huvuru if everything checks out.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order_id ) {
    
  
		global $woocommerce;
		
		//get current order
		$order = wc_get_order( $order_id ); // added code in Woo Commerce that needs to be changed
		$checkout_url = $order->get_checkout_payment_url( );
		
		// Check payment
		if ( ! $order_id ) {
			wp_redirect($checkout_url);
			exit;
		} else {
			$api_request_url =  WC()->api_request_url( $this->callback );
			$listener_url = add_query_arg( 'order_id' , $order_id, $api_request_url );
						
			// Get the return url
			$return_url = $this->return_url = $this->get_return_url( $order );

			// get currency
			$order_currency = $order->get_currency();
			//var_dump($order_currency);
			// Setup Huvuru API POST arguments

      $MerchantId =     $this->merchant_id;
      
      $Test  =    	$this->test;

      $environment = "sandbox";
      
      if (strtolower($Test)=="no") {
        $environment = "live";
        $PublicKey  =    	$this->LivePublicKey;
        $SecretKey  =    	$this->LiveSecretKey;
      }else{
        $PublicKey  =    	$this->TestPublicKey;
        $SecretKey  =    	$this->TestSecretKey;
      }

			// $this->log('Merchant ID:' . $MerchantId);

			$ConfirmUrl =       $listener_url;
			$ReturnUrl =        $return_url;
			$Reference =        $MerchantId.time().": " . $order->get_order_number();
			$Amount =           $order->get_total();
			$custEmail = 		   $order->get_billing_email();

			//set POST variables
			$values = array(
        'amount' => $Amount,
        'currency' => strtolower($order_currency),
        'email' => $custEmail,
        'environment' => $environment,
        'reference' => $Reference,
        'returnUrl' => $ReturnUrl,
        'resultUrl' => $ConfirmUrl
			);
						
			// should probably use static methods to have WC_huvuru_Helper::CreateMsg($a, $b);
      $fields_string = http_build_query($values);
      
			$url = esc_url_raw($this->initiate_transaction_url);
      $basicauth = 'Basic ' . base64_encode( $MerchantId . ':' . $PublicKey );
      $headers = array( 
		  'Authorization' => $basicauth
		  );
			// send API post request
			$response = wp_remote_request($url, [
				'timeout' => 45,
				'method' => 'POST',
				'body' => $fields_string,
        'headers' => $headers
			]);


			// get the response from huvuru
			$body = wp_remote_retrieve_body($response);
      
      $res = json_decode($body);
  
      if (isset($res->status)) {
          // first check status, take appropriate action
          if (!$res->status) {
            wp_redirect($checkout_url);
        		exit;
          }
          
          if ($res->status) {
            if (isset($res->checkout)) {
                    $theProcessUrl = esc_url_raw($res->checkout);
              			$payment_meta['checkout'] = $theProcessUrl;
              			$payment_meta['reference'] = sanitize_text_field($res->reference);
                    $payment_meta['transaction_uid'] = sanitize_text_field($res->transaction_uid);
              			$payment_meta['amount'] = (float)sanitize_text_field($res->amount);
              			$payment_meta['remark'] = sanitize_text_field($res->status_message);
                    $payment_meta['key'] = sanitize_text_field($PublicKey);
                    // if the post meta does not exist, wp calls add_post_meta
              			update_post_meta( $order_id, '_wc_huvuru_payment_meta', $payment_meta );
            }
          }else{
            $error =  $res->status_message;
          }
      }else
			{
			   $error = "Empty response from network request";
			}
			
      
		    
			//Choose where to go
			if(isset($error))
			{	
				wp_redirect($checkout_url);
				exit;
			}
			else
			{ 
				// redirect user to huvuru 
				wp_redirect($theProcessUrl);
				exit;
			}
		}
	} // End receipt_page()
  
  
  /**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {

		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/huvuru.log', 'a+' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()
  
  
  /**
	 * Process notify from Huvuru
	 * Called from wc-api to process huvuru's response
	 *
	 * @since 1.0.0
	 */
	function huvuru_checkout_return_handler($order_id = 0)
	{
		global $woocommerce;
		
    
    
		// Check the request method is POST
    if (isset($_GET['order_id'])) {
      $order_id = (int)$_GET['order_id'];
    }else{
      $order_id = (int)$order_id;
    }
		
		
		$order = wc_get_order( $order_id );
    if (!$order) {
      return; // no order was found return
    }
		
		$payment_meta = get_post_meta( $order_id, '_wc_huvuru_payment_meta', true );
		if($payment_meta)
		{
      
      $huvuru_lookup_transaction_url = esc_url_raw(WC_Huvuru_Check_Transaction);
      $MerchantId =     $this->merchant_id;
      $PublicKey  =    	$this->LivePublicKey;

			//execute post
      $basicauth = 'Basic ' . base64_encode( $MerchantId . ':' . $PublicKey );
      $headers = array( 
		  'Authorization' => $basicauth
		  );
      
			$response = wp_remote_request($huvuru_lookup_transaction_url.'?uid='.sanitize_text_field($payment_meta['transaction_uid']), [
				'timeout' => 45,
				'method' => 'GET',
        'headers' => $headers
			]);
    
      $body = wp_remote_retrieve_body($response);
      
      //check if response has secret key from Huvuru
      if (isset($_SERVER['HTTP_HTTP_X_HUVURU_SECRET'])) {
        if ($_SERVER['HTTP_HTTP_X_HUVURU_SECRET'] == $this->LiveSecretKey || $_SERVER['HTTP_HTTP_X_HUVURU_SECRET'] == $this->TestSecretKey) {
          // valid key code will continue after if statement
        }else{
          return; //invalid key return here
        }
        
      }else{
        return;// no secret key in header
      }
      $res = json_decode($body);
      
      $payment_meta['remark'] = isset($res->transaction->remark)?sanitize_text_field($res->transaction->remark):"No Remark";
      $payment_meta['status_message'] = isset($res->transaction->status_message)?sanitize_text_field($res->transaction->status_message):"No Message";      
      
      $trans_status = false;
      if ($payment_meta['remark']=="paid") {
        $trans_status = true;
      }

      update_post_meta( $order_id, '_wc_huvuru_payment_meta', $payment_meta );
      if ($trans_status) {
        $order->payment_complete();
        return;
      }else{
        $order->update_status( 'failed', __('Payment failed on Huvuru.', 'woohuvuru' ) );
        $order->save();
        return;
      }
      
		}

	}// End huvuru_checkout_return_handler()
  
  
  /**
	 * Process notify from Huvuru
	 * Called from wc-api to process huvuru's response
	 *
	 * @since 1.0.0
	 */
  function woohuvuru_order_cancelled_redirect( $order_id ){
    global $woocommerce;
    $order = new WC_Order($order_id);
    
    $_SERVER['HTTP_HTTP_X_HUVURU_SECRET'] = $this->LiveSecretKey;
    $this->huvuru_checkout_return_handler($order_id); // check transaction status the second time
    
    
    $payment_meta = get_post_meta( $order_id, '_wc_huvuru_payment_meta', true );
    
    
    //check if meta is available for us to use
    if ($payment_meta) {
        if ( isset($payment_meta['remark']) && strtolower( $payment_meta['remark'] ) == "failed" ) {
            wc_add_notice( __( 'Payment failed.', 'woohuvuru' ), 'error' );
            wp_redirect( $order->get_checkout_payment_url() );
        }elseif ( isset($payment_meta['remark']) && strtolower( $payment_meta['remark'] ) == "initialized" ) {
            wc_add_notice( __( 'Payment cancelled or not paid.', 'woohuvuru' ), 'error' );
            wp_redirect( $order->get_checkout_payment_url() );
        }
    }
    
    return;

  }
}