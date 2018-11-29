<?php

/*
   Plugin Name: Passerelle de paiement PAYEXPRESSE
   Description: PayExpresse propose une plateforme sécurisée de paiement en ligne pour faciliter les transactions entre les professionnels et leurs clients.
   Version: 1.0
   Author: PayExpresse
   Author URI: https://payexpresse.com
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}




add_action('plugins_loaded', 'woocommerce_payexpresse_init', 0);

function woocommerce_payexpresse_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Payexpresse extends WC_Payment_Gateway {

        public function __construct() {
            $this->payexpresse_errors = new WP_Error();
            /**
             * IPN
             */

            //id de la passerelle
            $this->id = 'payexpresse';
            $this->medthod_title = 'PAYEXPRESSE';
            $this->icon = apply_filters('woocommerce_payexpresse_icon', plugins_url('assets/images/payexpresse.png', __FILE__));
            $this->has_fields = false;
            //charger les champs pour paramètres de la passerelle.
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            //Mes parametres
            $this->api_key =$this->settings['api_key'];
            $this->secret_key =$this->settings['secret_key'];
            $this->env  = $this->settings['env'];
            $this->fee  = $this->settings['fee'];
            $this->devise  = $this->settings['devise'];
            $this->payexpress_host= 'https://payexpresse.com/';
            $this->posturl = $this->payexpress_host.'api/payment/request-payment';
            $this->submit_id = 0;

            //Transaction annulée
            $annulation =$success= $complet =-1;
            if(isset($_GET['cancel']) && $_GET['cancel']==1 )
                $annulation=1;
            if(isset($_GET['success']) && $_GET['success']==1 )
                $success = 1;


            if($annulation===1)
            {
                $message_type='error';
                $message='La transaction a été annulée';
                $wc_order_id = WC()->session->get('payexpresse_wc_oder_id');
                $order = new WC_Order($wc_order_id);
                $order->add_order_note($message);
                $rdi_url =$order->get_cancel_order_url();
            }
            if($success==1){
                $message="Paiement effectué avec succès.Merci d'avoir choisi Päyexpresse";
                $message_type='success';
                $wc_order_id = WC()->session->get('payexpresse_wc_oder_id');
                $order = new WC_Order($wc_order_id);
                $order->add_order_note($message);
                $order->payment_complete();
                $rdi_url=$this->get_return_url($order);
            }

            if($success == 1|| $annulation == 1){
                $notification_message = array(
                    'message' => $message,
                    'message_type' => $message_type
                );
                if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                    $hash = WC()->session->get('payexpresse_wc_hash_key');
                    add_post_meta($wc_order_id, '_payexpresse_hash', $hash, true);
                }
                update_post_meta($wc_order_id, '_payexpresse_wc_message', $notification_message);

                WC()->session->__unset('payexpresse_wc_hash_key');
                WC()->session->__unset('payexpresse_wc_order_id');

                wp_redirect($rdi_url);
                exit;
            }


            //fi dame

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }





        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activer/Désactiver', 'payexpresse'),
                    'type' => 'checkbox',
                    'label' => __('Activer le module de paiement PAYEXPRESSE.', 'payexpresse'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Titre:', 'payexpresse'),
                    'type' => 'text',
                    'description' => __('Texte que verra le client lors du paiement de sa commande.', 'payexpresse'),
                    'default' => __('Paiement avec', 'payexpresse')),
                'description' => array(
                    'title' => __('Description:', 'payexpresse'),
                    'type' => 'textarea',
                    'description' => __('Description que verra le client lors du paiement de sa commande.', 'payexpresse'),
                    'default' => __('Payexpresse est une passerelle qui assure  la sécurité de vos transactions .', 'payexpresse')),

                'api_key' => array(
                    'title' => __("Clé de l'api", 'payexpresse'),
                    'type' => 'text',
                    'description' => __("Clé de l'api fournie par PAYEXPRESSE ")),
                'secret_key' => array(
                    'title' => __("Clé secrete de l'api", 'payexpresse'),
                    'type' => 'text',
                    'description' => __('Clé secrete fournie par PAYEXPRESSE .')),
                'env' => array(
                    'title' => __("Environnement", 'payexpresse'),
                    'description' => __('Votre envirionnement de travail TEST ou PRODUCTION.'),
                    'css'=>'padding:0%;',
                    'type' => 'select',
                    'options'=>array('prod' => 'Production', 'test'=>'Test'),
                ),
                'devise' => array(
                    'title' => __("Dévise", 'payexpresse'),
                    'description' => __('Choisir une dévise.'),
                    'css'=>'padding:0%;',
                    'type' => 'select',
                    'options'=>array('XOF' => 'XOF', 'EUR'=>'EURO','CAD'=>'CAD','GBP'=>'gbp','USD'=>'USD','MAD'=>'MAD'),
                ),
                'fee' => array(
                    'title' => __("Commissions", 'payexpresse'),
                    'description' => __('Choisir qui paye les commissions.'),
                    'css'=>'padding:0%;',
                    'type' => 'select',
                    'options'=>array('1' => 'Commissions payées par Votre structure', '0'=>'Commissions payées par le client'),
                )



            );
        }

        public function admin_options() {
            echo '<h3>' . __('Passerelle de paiement PAYEXPRESSE', 'payexpresse') . '</h3>';
            echo '<p>' . __('PAYEXPRESSE est la meilleure plateforme de paiement en ligne.') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            //   wp_enqueue_script('payexpresse_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
        }

        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        protected function get_payexpresse_args($order, $order_id) {

            global $woocommerce;

            //$order = new WC_Order($order_id);
            $txnid = $order->id . '_' . date("ymds");

            $redirect_url = $woocommerce->cart->get_checkout_url();

            $productinfo = "Commande: " . $order->id;

            $str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
            $hash = hash('sha512', $str);

            WC()->session->set('payexpresse_wc_hash_key', $hash);

            $items = $woocommerce->cart->get_cart();
            //  $payexpresse_items = array();
            $produit="";
            foreach ($items as $item) {
                $produit=$produit.$item["data"]->post->post_title." ";

            }


            $itemsId = [];
            foreach ($order->get_items() as $item_id => $item) {
                array_push($itemsId, $item_id);
            }

            $opt = get_settings('woocommerce_payexpresse_settings', array() );

            //dame arguments
            $postfields = array(
                "item_name"    =>$produit,
                "item_price"   =>$order->order_total,
                "currency"       => $opt['devise'],  //"xof",
                "no_calculate_fee" => $opt['fee'],
                "ref_command"  =>$order->id.'_'.time(),
                "command_name" =>"Paiement de " . $order->order_total . " ".$opt['devise']." pour article(s) achetés sur " . get_bloginfo("name"),
                "env"          => $opt['env'],
                "success_url" =>$redirect_url.'?success=1',
                "ipn_url"=>  get_site_url(null,'','https')  ."/payexpresse/v1/ipn",
                "cancel_url"   =>$redirect_url.'?cancel=1',
                "custom_field"=> json_encode([
                    'order' => $order_id,
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number()
                ]));
            //fin dame arguments

            apply_filters('woocommerce_payexpresse_args', $postfields, $order);

            return $postfields;
        }


        function post($url, $data, $order_id,$header = [])
        {

            $strPostField = http_build_query($data);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $strPostField);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($header, [
                'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
                'Content-Length: ' . mb_strlen($strPostField)
            ]));

            $response = curl_exec($ch);


            $jsonResponse = json_decode($response, true);

            WC()->session->set('payexpresse_wc_oder_id', $order_id);

            if(array_key_exists('token', $jsonResponse))
            {

                return $this->payexpress_host.'payment/checkout/'.$jsonResponse['token'];


            }
            else {
                if(array_key_exists('error', $jsonResponse))
                    wc_add_notice($jsonResponse['error'][0], "error");
                else
                    if(array_key_exists('success',$jsonResponse) && $jsonResponse['success']===-1 )
                        wc_add_notice($jsonResponse['message'], "error");

                    else
                        wc_add_notice("Erreur inconnue", "error");
                return '';
            }


        }

        //fin mon post dame

        function process_payment($order_id) {
            $order = new WC_Order($order_id);


            return array(
                'result' => 'success',
                'redirect' => $this->post($this->posturl, $this->get_payexpresse_args($order, $order_id), $order_id,[
                    "API_KEY: ".$this->api_key,
                    "API_SECRET: ".$this->secret_key
                ])
            );
        }



        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }


        static function add_payexpresse_fcfa_currency($currencies) {
            $currencies['FCFA'] = __('BCEAO XOF', 'woocommerce');
            return $currencies;
        }

        static function add_payexpresse_fcfa_currency_symbol($currency_symbol, $currency) {
            switch (
            $currency) {
                case 'FCFA': $currency_symbol = 'FCFA';
                    break;
            }
            return $currency_symbol;
        }

        static function woocommerce_add_payexpresse_gateway($methods) {
            $methods[] = 'WC_Payexpresse';
            return $methods;
        }

        // Add settings link on plugin page
        static function woocommerce_add_payexpresse_settings_link($links) {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_payexpresse">Paramètres</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

    }



    $plugin = plugin_basename(__FILE__);

    add_filter('woocommerce_currencies', array('WC_Payexpresse', 'add_payexpresse_fcfa_currency'));
    add_filter('woocommerce_currency_symbol', array('WC_Payexpresse', 'add_payexpresse_fcfa_currency_symbol'), 10, 2);

    add_filter("plugin_action_links_$plugin", array('WC_Payexpresse', 'woocommerce_add_payexpresse_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_Payexpresse', 'woocommerce_add_payexpresse_gateway'));


    $pay_express_ipn_confirm = function (){


        if($_SERVER[ 'REQUEST_URI'] === "/payexpresse/v1/ipn" ){

            $options = get_settings('woocommerce_payexpresse_settings', array() );
            if(isset($_POST['type_event']))
            {
                $res = $_POST['type_event'];
                if($res === 'sale_complete' && hash('sha256', $options['api_key']) === $_POST['api_key_sha256'] && hash('sha256', $options['secret_key']) === $_POST['api_secret_sha256'])
                {
                    global $woocommerce;

                    ini_set('display_errors', 1);
                    error_reporting(E_ALL);
                    $custom = json_decode($_POST['custom_field'], true);

                    $order_id = $custom['order_id'];
                    global $wpdb;

                    $prefix = $wpdb->base_prefix;
                    $query = "UPDATE ".$prefix."posts SET `post_status` = REPLACE(post_status, 'pending','completed') WHERE ID =".$order_id;

                    $wpdb->query($query);
                    die('OK');
                }
            }

            die('FAILLED');
        }

    };

    $pay_express_ipn_confirm();


}

