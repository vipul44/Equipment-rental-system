<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Settings {

    public static function get_pricing() {
        return get_option( 'frs_pricing', array() );
    }

    public static function get_price( $capacity, $duration, $fuel ) {
        $pricing = self::get_pricing();

        // Check propane/gas/diesel category
        if ( isset( $pricing['lift_capacity'][ $capacity ][ $duration ] ) ) {
            $base = floatval( $pricing['lift_capacity'][ $capacity ][ $duration ] );
            $fuel_surcharge = isset( $pricing['fuel_surcharge'][ $fuel ] )
                ? floatval( $pricing['fuel_surcharge'][ $fuel ] ) : 0;
            return $base + $fuel_surcharge;
        }

        // Check electric equipment category
        if ( isset( $pricing['electric_equipment'][ $capacity ][ $duration ] ) ) {
            return floatval( $pricing['electric_equipment'][ $capacity ][ $duration ] );
        }

        return 0;
    }

    public static function get_all_equipment() {
        $pricing = self::get_pricing();
        $imgs    = self::get_equipment_images();
        $items   = array();

        // Propane/Gas/Diesel
        if ( ! empty( $pricing['lift_capacity'] ) ) {
            foreach ( $pricing['lift_capacity'] as $cap => $rates ) {
                // Try multiple key formats to find the image
                $img = self::find_image( $imgs, array( 'cat1_' . $cap, 'cat1_' . strtolower($cap), 'cat1_' . strtoupper($cap) ) );
                $items[] = array(
                    'key'      => $cap,
                    'label'    => $cap,
                    'category' => 'propane',
                    'image'    => $img,
                    'rates'    => $rates,
                );
            }
        }

        // Electric
        if ( ! empty( $pricing['electric_equipment'] ) ) {
            foreach ( $pricing['electric_equipment'] as $key => $data ) {
                $img = self::find_image( $imgs, array( 'electric_' . $key, 'electric_' . strtolower($key), 'electric_' . strtoupper($key) ) );
                $items[] = array(
                    'key'      => $key,
                    'label'    => isset($data['label']) ? $data['label'] : $key,
                    'capacity' => isset($data['capacity']) ? $data['capacity'] : '',
                    'category' => 'electric',
                    'image'    => $img,
                    'rates'    => array(
                        'day'   => isset($data['day'])   ? $data['day']   : 0,
                        'week'  => isset($data['week'])  ? $data['week']  : 0,
                        'month' => isset($data['month']) ? $data['month'] : 0,
                    ),
                );
            }
        }

        return $items;
    }

    /**
     * Try multiple key variants and return the first image URL found.
     */
    private static function find_image( $imgs, $keys ) {
        foreach ( $keys as $k ) {
            if ( ! empty( $imgs[ $k ] ) ) return $imgs[ $k ];
        }
        // Last resort: case-insensitive scan
        foreach ( $imgs as $stored_key => $url ) {
            foreach ( $keys as $k ) {
                if ( strtolower($stored_key) === strtolower($k) ) return $url;
            }
        }
        return '';
    }

    /**
     * Public wrapper for find_image — usable from templates.
     */
    public static function find_image_public( $imgs, $keys ) {
        return self::find_image( $imgs, $keys );
    }

    public static function get_equipment_images() {
        return get_option( 'frs_equipment_images', array() );
    }

    public static function get_equipment_image( $key ) {
        $imgs = self::get_equipment_images();
        return isset( $imgs[ $key ] ) ? $imgs[ $key ] : '';
    }

    public static function get_addons() {
        $pricing = self::get_pricing();
        return isset( $pricing['addons'] ) ? $pricing['addons'] : array();
    }

    public static function get_deposit() {
        $pricing = self::get_pricing();
        return isset( $pricing['deposit_amount'] ) ? floatval( $pricing['deposit_amount'] ) : 50;
    }

    public static function get_agreement_text() {
        return get_option( 'frs_agreement_text', '' );
    }

    public static function get_stripe_key() {
        $mode = get_option( 'frs_stripe_mode', 'test' );
        return $mode === 'live'
            ? get_option( 'frs_stripe_live_key', '' )
            : get_option( 'frs_stripe_test_key', '' );
    }

    public static function get_stripe_publishable_key() {
        $mode = get_option( 'frs_stripe_mode', 'test' );
        return $mode === 'live'
            ? get_option( 'frs_stripe_pub_live_key', '' )
            : get_option( 'frs_stripe_pub_test_key', '' );
    }

    public static function capacities() {
        $pricing = self::get_pricing();
        $caps = array();
        if ( ! empty( $pricing['lift_capacity'] ) ) $caps = array_merge( $caps, array_keys( $pricing['lift_capacity'] ) );
        if ( ! empty( $pricing['electric_equipment'] ) ) $caps = array_merge( $caps, array_keys( $pricing['electric_equipment'] ) );
        return $caps ?: array('3K','4K','5K','6K','8K','10K','15K','20K','36K');
    }

    public static function fuel_types() {
        return array('Electric','Propane','Diesel');
    }

    public static function duration_types() {
        return array('day'=>'Day','week'=>'Week','month'=>'Month');
    }
}
