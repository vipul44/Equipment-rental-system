<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Order {

    public static function generate_order_number() {
        return 'FRK-' . strtoupper( substr( uniqid(), -6 ) ) . '-' . date('Ymd');
    }

    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'frs_orders';

        $order_number = self::generate_order_number();

        $addons        = isset( $data['addons'] ) ? $data['addons'] : array();
        $addon_keys    = is_array( $addons ) ? implode( ',', $addons ) : '';
        $addon_names   = isset( $data['addon_names'] ) ? (array) $data['addon_names'] : array();

        // Calculate add-ons price
        $pricing      = FRS_Settings::get_pricing();
        $addons_price = 0;
        foreach ( $addons as $addon_key ) {
            if ( isset( $pricing['addons'][ $addon_key ]['price'] ) ) {
                $addons_price += floatval( $pricing['addons'][ $addon_key ]['price'] );
            }
        }

        // Calculate equipment base price using qty × rate
        $cap      = $data['lift_capacity'];
        $dur_type = $data['duration_type'];
        $qty_day   = isset($data['qty_day'])   ? intval($data['qty_day'])   : 0;
        $qty_week  = isset($data['qty_week'])  ? intval($data['qty_week'])  : 0;
        $qty_month = isset($data['qty_month']) ? intval($data['qty_month']) : 0;

        // Try to get rates from pricing
        $rate_day   = 0;
        $rate_week  = 0;
        $rate_month = 0;

        if ( isset($pricing['lift_capacity'][$cap]) ) {
            $rates      = $pricing['lift_capacity'][$cap];
            $rate_day   = floatval(isset($rates['day'])   ? $rates['day']   : 0);
            $rate_week  = floatval(isset($rates['week'])  ? $rates['week']  : 0);
            $rate_month = floatval(isset($rates['month']) ? $rates['month'] : 0);
        } elseif ( isset($pricing['electric_equipment'][$cap]) ) {
            $rates      = $pricing['electric_equipment'][$cap];
            $rate_day   = floatval(isset($rates['day'])   ? $rates['day']   : 0);
            $rate_week  = floatval(isset($rates['week'])  ? $rates['week']  : 0);
            $rate_month = floatval(isset($rates['month']) ? $rates['month'] : 0);
        }

        $base_price = ($qty_day * $rate_day) + ($qty_week * $rate_week) + ($qty_month * $rate_month);

        // If qty not provided fall back to single duration
        if ( $base_price == 0 ) {
            $base_price = FRS_Settings::get_price( $cap, $dur_type, isset($data['fuel_type']) ? $data['fuel_type'] : '' );
        }

        // Fuel charge
        $fuel_charge = isset($data['fuel_charge']) ? floatval($data['fuel_charge']) : 0;
        $fuel_summary = isset($data['fuel_summary']) ? $data['fuel_summary'] : '';

        // Build duration label
        $dur_parts = array();
        if ($qty_day   > 0) $dur_parts[] = $qty_day   . ' Day'   . ($qty_day   > 1 ? 's' : '');
        if ($qty_week  > 0) $dur_parts[] = $qty_week  . ' Week'  . ($qty_week  > 1 ? 's' : '');
        if ($qty_month > 0) $dur_parts[] = $qty_month . ' Month' . ($qty_month > 1 ? 's' : '');
        $duration_label = implode(' + ', $dur_parts) ?: ucfirst($dur_type);

        $total_price    = $base_price + $fuel_charge + $addons_price;
        $deposit_amount = FRS_Settings::get_deposit();

        $row = array(
            'order_number'      => $order_number,
            'status'            => 'pending',
            'lift_capacity'     => sanitize_text_field( $cap ),
            'fuel_type'         => sanitize_text_field( $fuel_summary ?: (isset($data['fuel_type']) ? $data['fuel_type'] : 'N/A') ),
            'duration_type'     => sanitize_text_field( $duration_label ),
            'addons'            => sanitize_text_field( $addon_keys ),
            'base_price'        => $base_price,
            'addons_price'      => $addons_price,
            'total_price'       => $total_price,
            'deposit_amount'    => $deposit_amount,
            'payment_type'      => sanitize_text_field( $data['payment_type'] ),
            'payment_status'    => 'pending',
            'customer_name'     => sanitize_text_field( $data['customer_name'] ),
            'customer_company'  => sanitize_text_field( isset($data['customer_company']) ? $data['customer_company'] : '' ),
            'customer_phone'    => sanitize_text_field( $data['customer_phone'] ),
            'customer_email'    => sanitize_email( $data['customer_email'] ),
            'jobsite_address'   => sanitize_textarea_field( isset($data['jobsite_address']) ? $data['jobsite_address'] : '' ),
            'rental_notes'      => sanitize_textarea_field( isset($data['rental_notes']) ? $data['rental_notes'] : '' ),
            'signature_data'    => $data['signature_data'],
            'agreement_accepted'=> 1,
            'created_at'        => current_time('mysql'),
        );

        $wpdb->insert( $table, $row );
        $id = $wpdb->insert_id;

        if ( $id ) {
            $order    = self::get( $id );
            $pdf_path = FRS_PDF::generate( $order );
            $wpdb->update( $table, array('pdf_path' => $pdf_path, 'status' => 'confirmed'), array('id' => $id) );
            $order->pdf_path = $pdf_path;
            FRS_Email::send_confirmation( $order );
            FRS_Email::send_admin_notification( $order );
            return $order_number;
        }

        return false;
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}frs_orders WHERE id = %d", $id
        ) );
    }

    public static function get_by_order_number( $order_number ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}frs_orders WHERE order_number = %s", $order_number
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $defaults = array( 'limit' => 20, 'offset' => 0, 'orderby' => 'created_at', 'order' => 'DESC', 'search' => '' );
        $args     = wp_parse_args( $args, $defaults );

        $where = self::build_search_where( $args['search'] );

        $sql = "SELECT * FROM {$wpdb->prefix}frs_orders {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        return $wpdb->get_results( $wpdb->prepare( $sql, $args['limit'], $args['offset'] ) );
    }

    public static function count( $search = '' ) {
        global $wpdb;
        $where = self::build_search_where( $search );
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}frs_orders {$where}" );
    }

    private static function build_search_where( $search ) {
        global $wpdb;
        if ( empty( $search ) ) return '';
        $like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
        return $wpdb->prepare(
            "WHERE (order_number LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_company LIKE %s OR customer_phone LIKE %s OR lift_capacity LIKE %s OR fuel_type LIKE %s)",
            $like, $like, $like, $like, $like, $like, $like
        );
    }

    public static function update_payment_status( $order_number, $status, $intent_id = '' ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'frs_orders',
            array( 'payment_status' => $status, 'stripe_payment_intent' => $intent_id ),
            array( 'order_number'   => $order_number )
        );
    }
}
