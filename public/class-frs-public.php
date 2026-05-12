<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Public {

    public function __construct() {
        add_shortcode( 'forklift_rental', array( $this, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts',           array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_frs_get_price',        array( $this, 'ajax_get_price' ) );
        add_action( 'wp_ajax_nopriv_frs_get_price', array( $this, 'ajax_get_price' ) );
        add_action( 'wp_ajax_frs_create_payment',        array( $this, 'ajax_create_payment' ) );
        add_action( 'wp_ajax_nopriv_frs_create_payment', array( $this, 'ajax_create_payment' ) );
        add_action( 'wp_ajax_frs_submit_order',         array( $this, 'ajax_submit_order' ) );
        add_action( 'wp_ajax_nopriv_frs_submit_order',  array( $this, 'ajax_submit_order' ) );
        add_action( 'wp_ajax_frs_download_agreement',        array( 'FRS_PDF', 'handle_download' ) );
        add_action( 'wp_ajax_nopriv_frs_download_agreement', array( 'FRS_PDF', 'handle_download' ) );
    }

    public function enqueue_scripts() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'forklift_rental' ) ) return;

        wp_enqueue_style( 'frs-public', FRS_PLUGIN_URL . 'public/css/public.css', array(), FRS_VERSION );
        wp_enqueue_script( 'signature-pad', 'https://cdnjs.cloudflare.com/ajax/libs/signature_pad/4.1.7/signature_pad.umd.min.js', array(), '4.1.7', true );
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
        wp_enqueue_script( 'frs-public', FRS_PLUGIN_URL . 'public/js/public.js', array('jquery','signature-pad','stripe-js'), FRS_VERSION, true );
        wp_localize_script( 'frs-public', 'frs_vars', array(
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('frs_public_nonce'),
            'stripe_pub_key'=> FRS_Settings::get_stripe_publishable_key(),
            'deposit'       => FRS_Settings::get_deposit(),
            'company_name'  => get_option('frs_company_name', get_bloginfo('name')),
        ) );
    }

    public function shortcode() {
        ob_start();
        include FRS_PLUGIN_DIR . 'templates/form.php';
        return ob_get_clean();
    }

    public function ajax_get_price() {
        check_ajax_referer('frs_public_nonce','nonce');
        $cap      = sanitize_text_field( (isset($_POST['lift_capacity']) ? $_POST['lift_capacity'] : '') );
        $fuel     = sanitize_text_field( (isset($_POST['fuel_type']) ? $_POST['fuel_type'] : '') );
        $duration = sanitize_text_field( (isset($_POST['duration_type']) ? $_POST['duration_type'] : '') );
        $addons   = isset($_POST['addons']) && is_array($_POST['addons']) ? array_map('sanitize_text_field',$_POST['addons']) : array();

        $base     = FRS_Settings::get_price( $cap, $duration, $fuel );
        $pricing  = FRS_Settings::get_pricing();
        $addons_total = 0;
        foreach ( $addons as $k ) {
            if ( isset($pricing['addons'][$k]['price']) ) $addons_total += floatval($pricing['addons'][$k]['price']);
        }
        $total   = $base + $addons_total;
        $deposit = FRS_Settings::get_deposit();

        wp_send_json_success(array(
            'base'    => number_format($base, 2),
            'addons'  => number_format($addons_total, 2),
            'total'   => number_format($total, 2),
            'deposit' => number_format($deposit, 2),
        ));
    }

    public function ajax_create_payment() {
        check_ajax_referer('frs_public_nonce','nonce');
        $payment_type = sanitize_text_field( (isset($_POST['payment_type']) ? $_POST['payment_type'] : '') );
        $order_number = sanitize_text_field( (isset($_POST['order_number']) ? $_POST['order_number'] : 'PENDING') );

        if ( $payment_type === 'save_card' ) {
            $result = FRS_Payment::setup_intent();
        } else {
            $cap      = sanitize_text_field((isset($_POST['lift_capacity']) ? $_POST['lift_capacity'] : ''));
            $fuel     = sanitize_text_field((isset($_POST['fuel_type']) ? $_POST['fuel_type'] : ''));
            $duration = sanitize_text_field((isset($_POST['duration_type']) ? $_POST['duration_type'] : ''));
            $addons   = isset($_POST['addons']) && is_array($_POST['addons']) ? array_map('sanitize_text_field',$_POST['addons']) : array();
            $base     = FRS_Settings::get_price($cap, $duration, $fuel);
            $pricing  = FRS_Settings::get_pricing();
            $addons_total = 0;
            foreach ($addons as $k) {
                if (isset($pricing['addons'][$k]['price'])) $addons_total += floatval($pricing['addons'][$k]['price']);
            }
            $total = $base + $addons_total;

            if ( $payment_type === 'deposit' ) {
                $amount_cents = intval( FRS_Settings::get_deposit() * 100 );
            } else {
                $amount_cents = intval( $total * 100 );
            }
            $result = FRS_Payment::create_payment_intent( $amount_cents, $order_number );
        }

        if ( isset($result['error']) ) wp_send_json_error($result['error']);
        wp_send_json_success($result);
    }

    public function ajax_submit_order() {
        check_ajax_referer('frs_public_nonce','nonce');

        // Only truly required fields — fuel_type is now optional (electric has none)
        $required = array('lift_capacity','duration_type','customer_name','customer_phone','customer_email','payment_type','signature_data');
        foreach ($required as $field) {
            if ( empty($_POST[$field]) ) {
                wp_send_json_error('Missing required field: ' . $field);
                wp_die();
            }
        }

        $data = array(
            'lift_capacity'    => sanitize_text_field($_POST['lift_capacity']),
            'fuel_type'        => sanitize_text_field(isset($_POST['fuel_type']) ? $_POST['fuel_type'] : 'Not selected'),
            'fuel_summary'     => sanitize_text_field(isset($_POST['fuel_summary']) ? $_POST['fuel_summary'] : ''),
            'fuel_charge'      => floatval(isset($_POST['fuel_charge']) ? $_POST['fuel_charge'] : 0),
            'duration_type'    => sanitize_text_field($_POST['duration_type']),
            'duration_qty'     => intval(isset($_POST['duration_qty']) ? $_POST['duration_qty'] : 1),
            'qty_day'          => intval(isset($_POST['qty_day'])   ? $_POST['qty_day']   : 0),
            'qty_week'         => intval(isset($_POST['qty_week'])  ? $_POST['qty_week']  : 0),
            'qty_month'        => intval(isset($_POST['qty_month']) ? $_POST['qty_month'] : 0),
            'addons'           => isset($_POST['addons']) && is_array($_POST['addons']) ? array_map('sanitize_text_field',$_POST['addons']) : array(),
            'addon_names'      => isset($_POST['addon_names']) && is_array($_POST['addon_names']) ? array_map('sanitize_text_field',$_POST['addon_names']) : array(),
            'customer_name'    => sanitize_text_field($_POST['customer_name']),
            'customer_company' => sanitize_text_field(isset($_POST['customer_company']) ? $_POST['customer_company'] : ''),
            'customer_phone'   => sanitize_text_field($_POST['customer_phone']),
            'customer_email'   => sanitize_email($_POST['customer_email']),
            'jobsite_address'  => sanitize_textarea_field(isset($_POST['jobsite_address']) ? $_POST['jobsite_address'] : ''),
            'rental_notes'     => sanitize_textarea_field(isset($_POST['rental_notes']) ? $_POST['rental_notes'] : ''),
            'payment_type'     => sanitize_text_field($_POST['payment_type']),
            'signature_data'   => $_POST['signature_data'],
        );

        $order_number = FRS_Order::create($data);

        if ($order_number) {
            $order   = FRS_Order::get_by_order_number( $order_number );
            $pdf_url = $order ? FRS_PDF::get_url( $order ) : '';
            wp_send_json_success(array(
                'order_number' => $order_number,
                'pdf_url'      => $pdf_url,
            ));
        } else {
            // Return DB error for easier debugging
            global $wpdb;
            $db_err = $wpdb->last_error ? $wpdb->last_error : 'Unknown DB error';
            wp_send_json_error('Failed to create order: ' . $db_err);
        }
        wp_die();
    }
}
