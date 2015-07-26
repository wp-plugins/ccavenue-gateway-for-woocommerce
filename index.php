<?php

/**
 * Plugin Name: ccAvenue gateway for WooCommerce
 * Plugin URI: http://www.coravity.com/
 * Description: The plugin add ccAvenue, an indian payment gateway, to wooCommerce(2.0.0+) payment gateways list. The plugin is updated with the latest API of ccAvenue (as on June, 2015).
 * Version: 1.0.3
 * Author: Coravity Infotech
 * Author URI: http://www.coravity.com/
 * Developer: Abhishek Sachan
 * Developer URI: http://www.abhisheksachan.com/
 * Text Domain: ccavenue-gateway-for-woocommerce
 *
 * Copyright: © 2009-2015 WooThemes.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


	if (!defined('ABSPATH')){
	    exit;
	}
	add_action('plugins_loaded', 'woocommerce_cc_init', 0);

	function woocommerce_cc_init()
	{
	    
	    if (!class_exists('WC_Payment_Gateway')){
	        return;
	    }
	    
	    class WC_cc extends WC_Payment_Gateway
	    {
	        public function __construct()
	        {
	            $this->id           = 'ccavenue';
	            $this->method_title = __('CCAvenue', 'cc_gateway');
	            $this->icon         = plugins_url('images/ccAvenue_logo.png', __FILE__);
	            $this->has_fields   = false;
	            
	            $this->init_form_fields();
	            $this->init_settings();

	            $this->title       = $this->settings['title'];
	            $this->description = $this->settings['description'];
	            $this->merchant_id = $this->settings['merchant_id'];
	            $this->working_key = $this->settings['working_key'];
	            $this->liveurl    = 'http://www.ccavenue.com/shopzone/cc_details.jsp';
	            $this->notify_url = str_replace('https:', 'http:', home_url('/wc-api/WC_cc'));
	            
	            $this->msg['message'] = "";
	            $this->msg['class']   = "";
	            
	            //update for woocommerce >2.0
	            add_action('woocommerce_api_wc_cc', array(
	                $this,
	                'check_ccavenue_response'
	            ));
	            
	            add_action('valid-ccavenue-request', array(
	                $this,
	                'successful_request'
	            ));
	            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
	                $this,
	                'process_admin_options'
	            ));
	            add_action('woocommerce_receipt_ccavenue', array(
	                $this,
	                'receipt_page'
	            ));
	            add_action('woocommerce_thankyou_ccavenue', array(
	                $this,
	                'thankyou_page'
	            ));
	        }
	        
	        function init_form_fields(){
	            
	            $this->form_fields = array(
	                'enabled' => array(
	                    'title' => __('Enable/Disable', 'cc_gateway'),
	                    'type' => 'checkbox',
	                    'label' => __('Enable CCAvenue Payment Gateway, it will be visible on checkout page.', 'cc_gateway'),
	                    'default' => 'no'
	                ),
	                'title' => array(
	                    'title' => __('Title:', 'cc_gateway'),
	                    'type' => 'text',
	                    'description' => __('The title will be shown to the user during checkout.', 'cc_gateway'),
	                    'default' => __('CCAvenue', 'cc_gateway')
	                ),
	                'description' => array(
	                    'title' => __('Description:', 'cc_gateway'),
	                    'type' => 'textarea',
	                    'description' => __('The description will be shown the user during checkout.', 'cc_gateway'),
	                    'default' => __('Pay by Credit / Debit card / Internet Banking through CCAvenue Secure Servers.', 'cc_gateway')
	                ),
	                'merchant_id' => array(
	                    'title' => __('Merchant ID', 'cc_gateway'),
	                    'type' => 'text',
	                    'description' => __('Mercahnt ID/User ID can be found by clicking "Generate Working Key" of "Settings and Options" at CCAvenue Merchant Login.', 'cc_gateway')
	                ),
	                'working_key' => array(
	                    'title' => __('Working Key', 'cc_gateway'),
	                    'type' => 'text',
	                    'description' => __('Working Key is also written along with Merchant ID.', 'cc_gateway')
	                ),
	                'success_msg' => array(
	                    'title' => __('Success Message', 'cc_gateway'),
	                    'type' => 'textarea',
	                    'description' => __('Enter success message that will be shown to the user after payment success. (AuthDesc = Y)', 'cc_gateway'),
	                    'default' => __('Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.', 'cc_gateway')
	                ),
	                'no_status_msg' => array(
	                    'title' => __('Pending Payment Message', 'cc_gateway'),
	                    'type' => 'textarea',
	                    'description' => __('Enter pending payment message that will be shown to the user after payment. (AuthDesc = B)', 'cc_gateway'),
	                    'default' => __('Thank you for shopping with us. We will keep you posted about the status of your order by e-mails.', 'cc_gateway')
	                ),
	                'declined_msg' => array(
	                    'title' => __('Payment Declined Message', 'cc_gateway'),
	                    'type' => 'textarea',
	                    'description' => __('Enter payment declined message that will be shown to the user after payment is declined. (AuthDesc = N)', 'cc_gateway'),
	                    'default' => __('Thank you for shopping with us. However, the transaction has been declined.', 'cc_gateway')
	                ),
	                'error_msg' => array(
	                    'title' => __('Error Message', 'cc_gateway'),
	                    'type' => 'textarea',
	                    'description' => __('Enter error message that will be shown to the user after payment failure. (error)', 'cc_gateway'),
	                    'default' => __('Thank you for shopping with us. However, the transaction has been declined.', 'cc_gateway')
	                )
	            ); 
	        }

	        public function admin_options()
	        {
	            echo '<h3>' . __('ccAvenue Payment Gateway', 'cc_gateway') . '</h3>';
	            echo '<p>' . __('ccAvenue payment gateway API campatible with ccavenue as on June, 2015.', 'cc_gateway') . '</p>';
	            echo '<table class="form-table">';
	            $this->generate_settings_html();
	            echo '</table>';
	            
	        }

	        function payment_fields()
	        {
	            if ($this->description)
	                echo wpautop(wptexturize($this->description));
	        }

	        function receipt_page($order)
	        {
	            
	            echo '<p>' . __('Thank you for your order, Please wait while ccAvenue Payment Page is loading.', 'cc_gateway') . '</p>';
	            echo $this->generate_ccavenue_form($order);
	        }

	        function process_payment($order_id)
	        {
	            $order = new WC_Order($order_id);
	            return array(
	                'result' => 'success',
	                'redirect' => $order->get_checkout_payment_url(true)
	            );
	        }
	        
	        function check_ccavenue_response()
	        {
	            global $woocommerce;
	            
	            $msg['class']   = 'error';
	            $msg['message'] = $this->settings['error_msg']." - Error: No Response From Payment Gateway.";
	            

	            $AuthDesc="";
	            $MerchantId="";
	            $order_id="";
	            $Amount=0;
	            $Checksum=0;
	            $veriChecksum=false;
	            $bank_ref = '';
	            if (isset($_REQUEST['encResponse'])) {
	                $encResponse = $_REQUEST["encResponse"];
	                $rcvdString  = $this->decrypt($encResponse, $this->working_key);
	                $decryptValues=explode('&', $rcvdString);
	                $dataSize=sizeof($decryptValues);
	                
	                for($i = 0; $i < $dataSize; $i++) 
	                {
	                    $information=explode('=',$decryptValues[$i]);
	                    if($i==0)   $MerchantId=$information[1];    
	                    if($i==1)   $order_id=$information[1];
	                    if($i==2)   $Amount=$information[1];    
	                    if($i==3)   $AuthDesc=$information[1];
	                    if($i==4)   $Checksum=$information[1];  

	                }
	                $bank_ref = $decryptValues['nb_bid'];
	            }elseif(isset($_POST['Order_Id'])){
	                $MerchantId     = $_POST['Merchant_Id'];
	                $AuthDesc       = $_POST['AuthDesc'];
	                $order_id       = $_POST['Order_Id'];
	                $Amount         = $_POST['Amount'];
	                $Checksum       = $_POST['Checksum'];
	                $bank_ref       = $_POST['nb_bid'];
	            }
	            
	            if ($order_id != '') {
	                $rcvdString=$MerchantId.'|'.$order_id.'|'.$Amount.'|'.$AuthDesc.'|'.$this->working_key;
	                $veriChecksum=$this->verifyChecksum($this->genchecksum($rcvdString), $Checksum);
	                $order_id_parts = explode('_', $order_id);
	                $order_id_actual = $order_id_parts[0];
	                $order = new WC_Order($order_id_actual);
	                $transauthorised = false;
	                if($veriChecksum==TRUE && $AuthDesc==="Y")
	                {
	                    $transauthorised = true;
	                    $msg['message']  = $this->settings['success_msg'];
	                    $msg['class']    = 'success';
	                    if ($order->status != 'processing') {
	                        $order->payment_complete($bank_ref);
	                        $order->add_order_note('CCAvenue payment successful<br/>Bank Ref Number: ' . $bank_ref);
	                        $woocommerce->cart->empty_cart();
	                        
	                    }
	                }
	                else if($veriChecksum==TRUE && $AuthDesc==="B")
	                {
	                    $msg['message'] = $this->settings['no_status_msg'];
	                    $order->add_order_note('Payment Pending Please check in ccAvenue account manually.');
	                    $msg['class']   = 'success';
	                }
	                else if($veriChecksum==TRUE && $AuthDesc==="N")
	                {
	                    $msg['class']   = 'error';
	                    $msg['message'] = $this->settings['declined_msg']." - Error: Payment Gateway Declined order.";
	                }
	                else
	                {
	                    $msg['class']   = 'error';
	                    $msg['message'] = $this->settings['error_msg']." - Error: Unknown Error";
	                }

	                if ($transauthorised == false) {
	                    $order->update_status('failed');
	                    $order->add_order_note($msg['message']);
	                }
	            }
	            
	            if (function_exists('wc_add_notice')) {
	                wc_add_notice($msg['message'], $msg['class']);
	                
	            } else {
	                if ($msg['class'] == 'success') {
	                    $woocommerce->add_message($msg['message']);
	                } else {
	                    $woocommerce->add_error($msg['message']);
	                    
	                }
	                $woocommerce->set_messages();
	            }
	            $redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
	            wp_redirect($redirect_url);
	            exit;
	        }

	        public function generate_ccavenue_form($order_id)
	        {
	            global $woocommerce;
	            $order         = new WC_Order($order_id);
	            $order_id      = $order_id.'_'.time();
	            $ccavenue_args = array(
	                'Merchant_Id' => $this->merchant_id,
	                'Amount' => $order->order_total,
	                'Order_Id' => $order_id,
	                'Redirect_Url' => $this->notify_url,
	                'Cancel_url' => $this->notify_url,
	                'billing_cust_name' => $order->billing_first_name . ' ' . $order->billing_last_name,
	                'billing_cust_address' => trim($order->billing_address_1, ','),
	                'billing_cust_country' => wc()->countries->countries[$order->billing_country],
	                'billing_cust_state' => $order->billing_state,
	                'billing_cust_city' => $order->billing_city,
	                'billing_zip_code' => $order->billing_postcode,
	                'billing_cust_tel' => $order->billing_phone,
	                'billing_cust_email' => $order->billing_email,
	                'delivery_cust_name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
	                'delivery_cust_address' => $order->shipping_address_1,
	                'delivery_cust_country' => $order->shipping_country,
	                'delivery_cust_state' => $order->shipping_state,
	                'delivery_cust_tel' => '',
	                'delivery_cust_city' => $order->shipping_city,
	                'delivery_zip_code' => $order->shipping_postcode,
	                'language' => 'EN',
	                'currency' => get_woocommerce_currency(),
	                'Checksum' => $this->getchecksum($this->merchant_id, $order->order_total, $order_id, $this->notify_url, $this->working_key)
	            );
	            
	            foreach ($ccavenue_args as $param => $value) {
	                $paramsJoined[] = "$param=$value";
	            }
	            $merchant_data         = implode('&', $paramsJoined);
	            $encrypted_data        = $this->encrypt($merchant_data, $this->working_key);
	            $ccavenue_args_array   = array();
	            $ccavenue_args_array[] = "<input type='hidden' name='encRequest' value='".$encrypted_data."'/>";
	            $ccavenue_args_array[] = "<input type='hidden' name='Merchant_Id' value='".$this->merchant_id."'/>";
	            
	            wc_enqueue_js('
	                $.blockUI({
	                    message: "' . esc_js(__('You are being redirected to Payment Gateway. Please Wait.', 'woocommerce')) . '",
	                    baseZ: 9999,
	                    overlayCSS:
	                    {
	                        background: "#fff",
	                        opacity: 0.6
	                    },
	                    css: {
	                        padding:        "20px",
	                        zindex:         "10000",
	                        textAlign:      "center",
	                        color:          "#333",
	                        border:         "5px solid rgba(0,0,0,0.8)",
	                        backgroundColor:"#fff",
	                        cursor:         "wait",
	                        lineHeight:     "24px",
	                    }
	                });
	            jQuery("#submit_ccavenue_payment_form").click();
	            ');
	            
	            $form = '<form action="' . $this->liveurl . '" method="post" id="ccavenue_payment_form" target="_top">
	            ' . implode('', $ccavenue_args_array) . '
	            <!-- Button Fallback -->
	            <div class="payment_buttons">
	            <input type="submit" class="button alt" id="submit_ccavenue_payment_form" value="' . __('Pay via CCAvenue', 'woocommerce') . '" />
	            </div>
	            <script type="text/javascript">
	            jQuery(".payment_buttons").hide();
	            </script>
	            </form>';
	            return $form;
	        }

		    /**
		     * Ecryption, decryption and other helping functions from ccAvenue kit.
		     */

	        function encrypt($plainText, $key)
		    {
		        $secretKey  = $this->hextobin(md5($key));
		        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
		        $openMode   = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
		        $blockSize  = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
		        $plainPad   = $this->pkcs5_pad($plainText, $blockSize);
		        if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) {
		            $encryptedText = mcrypt_generic($openMode, $plainPad);
		            mcrypt_generic_deinit($openMode);
		        }
		        return bin2hex($encryptedText);
		    }

		    function decrypt($encryptedText, $key)
		    {
		        $secretKey     = $this->hextobin(md5($key));
		        $initVector    = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
		        $encryptedText = $this->hextobin($encryptedText);
		        $openMode      = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
		        mcrypt_generic_init($openMode, $secretKey, $initVector);
		        $decryptedText = mdecrypt_generic($openMode, $encryptedText);
		        $decryptedText = rtrim($decryptedText, "\0");
		        mcrypt_generic_deinit($openMode);
		        return $decryptedText;
		    }

		    function pkcs5_pad($plainText, $blockSize)
		    {
		        $pad = $blockSize - (strlen($plainText) % $blockSize);
		        return $plainText . str_repeat(chr($pad), $pad);
		    }

		    function hextobin($hexString)
		    {
		        $length    = strlen($hexString);
		        $binString = "";
		        $count     = 0;
		        while ($count < $length) {
		            $subString    = substr($hexString, $count, 2);
		            $packedString = pack("H*", $subString);
		            if ($count == 0) {
		                $binString = $packedString;
		            }
		            
		            else {
		                $binString .= $packedString;
		            }
		            
		            $count += 2;
		        }
		        return $binString;
		    }

		    function getchecksum($MerchantId,$Amount,$OrderId ,$URL,$WorkingKey)
		    {
		        $str ="$MerchantId|$OrderId|$Amount|$URL|$WorkingKey";
		        $adler = 1;
		        $adler = $this->adler32($adler,$str);
		        return $adler;
		    }

		    function genchecksum($str)
		    {
		        $adler = 1;
		        $adler = $this->adler32($adler,$str);
		        return $adler;
		    }

		    function verifyChecksum($getCheck, $avnChecksum)
		    {
		        $verify=false;
		        if($getCheck==$avnChecksum) $verify=true;
		        return $verify;
		    }

		    function adler32($adler , $str)
		    {
		        $BASE =  65521 ;
		        $s1 = $adler & 0xffff ;
		        $s2 = ($adler >> 16) & 0xffff;
		        for($i = 0 ; $i < strlen($str) ; $i++)
		        {
		            $s1 = ($s1 + Ord($str[$i])) % $BASE ;
		            $s2 = ($s2 + $s1) % $BASE ;
		        }
		        return $this->leftshift($s2 , 16) + $s1;
		    }

		    function leftshift($str , $num)
		    {

		        $str = DecBin($str);

		        for( $i = 0 ; $i < (64 - strlen($str)) ; $i++)
		            $str = "0".$str ;

		        for($i = 0 ; $i < $num ; $i++) 
		        {
		            $str = $str."0";
		            $str = substr($str , 1 ) ;
		            //echo "str : $str <BR>";
		        }
		        return $this->cdec($str) ;
		    }

		    function cdec($num)
		    {
		        $dec=0;
		        for ($n = 0 ; $n < strlen($num) ; $n++)
		        {
		           $temp = $num[$n] ;
		           $dec =  $dec + $temp*pow(2 , strlen($num) - $n - 1);
		        }

		        return $dec;
		    }

	    }
	    
	    function woocommerce_add_cc($class)
	    {
	        $class[] = 'WC_cc';
	        return $class;
	    }
	    
	    add_filter('woocommerce_payment_gateways', 'woocommerce_add_cc');

	}
}

?>