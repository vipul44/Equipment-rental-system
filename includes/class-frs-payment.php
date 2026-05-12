<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Payment {

    public static function create_payment_intent( $amount_cents, $order_number ) {
        $secret_key = FRS_Settings::get_stripe_key();
        if ( empty( $secret_key ) ) return array( 'error' => 'Stripe is not configured.' );

        $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount'   => $amount_cents,
                'currency' => 'usd',
                'metadata[order_number]' => $order_number,
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) return array( 'error' => $response->get_error_message() );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error'] ) ) return array( 'error' => $body['error']['message'] );
        return array( 'client_secret' => $body['client_secret'], 'id' => $body['id'] );
    }

    public static function setup_intent() {
        $secret_key = FRS_Settings::get_stripe_key();
        if ( empty( $secret_key ) ) return array( 'error' => 'Stripe is not configured.' );

        $response = wp_remote_post( 'https://api.stripe.com/v1/setup_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => array( 'usage' => 'off_session' ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) return array( 'error' => $response->get_error_message() );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error'] ) ) return array( 'error' => $body['error']['message'] );
        return array( 'client_secret' => $body['client_secret'], 'id' => $body['id'] );
    }
}
