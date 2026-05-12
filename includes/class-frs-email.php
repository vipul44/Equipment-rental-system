<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Email {

    private static function fmt_date( $datetime ) {
        return date( 'm-d-Y', strtotime( $datetime ) );
    }

    public static function send_confirmation( $order ) {
        $to      = $order->customer_email;
        $subject = 'Your Forklift Rental Confirmation - Order #' . $order->order_number;
        $pdf_url = FRS_PDF::get_url( $order );

        $message  = '<html><body style="font-family:Arial,sans-serif;color:#333;">';
        $message .= '<h2 style="color:#e65c00;">Rental Confirmed!</h2>';
        $message .= '<p>Dear ' . esc_html($order->customer_name) . ',</p>';
        $message .= '<p>Thank you for your rental reservation. Your order has been confirmed.</p>';
        $message .= '<table style="border-collapse:collapse;width:100%;margin:16px 0;">';
        $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;"><strong>Order Number</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html($order->order_number) . '</td></tr>';
        $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;"><strong>Equipment</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html($order->lift_capacity . ' ' . $order->fuel_type) . '</td></tr>';
        $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;"><strong>Duration</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html(ucfirst($order->duration_type)) . '</td></tr>';
        $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;"><strong>Total</strong></td><td style="padding:8px;border:1px solid #ddd;">$' . number_format($order->total_price,2) . '</td></tr>';
        $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;"><strong>Payment</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html($order->payment_type) . '</td></tr>';
        $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;"><strong>Date Submitted</strong></td><td style="padding:8px;border:1px solid #ddd;">' . self::fmt_date($order->created_at) . '</td></tr>';
        $message .= '</table>';
        $message .= '<p><a href="' . esc_url($pdf_url) . '" style="background:#e65c00;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;">Download Signed Agreement (PDF)</a></p>';
        $message .= '<p>If you have any questions, please contact us with your order number.</p>';
        $message .= '<p>Thank you,<br>' . esc_html(get_option('frs_company_name')) . '</p>';
        $message .= '</body></html>';

        self::send( $to, $subject, $message );
    }

    public static function send_admin_notification( $order ) {
        $to      = get_option('frs_admin_email', get_option('admin_email'));
        $subject = 'New Forklift Rental Order #' . $order->order_number;
        $pdf_url = FRS_PDF::get_url( $order );

        $message  = '<html><body style="font-family:Arial,sans-serif;color:#333;">';
        $message .= '<h2>New Rental Order Received</h2>';
        $message .= '<table style="border-collapse:collapse;width:100%;margin:16px 0;">';
        $fields = array(
            'Order Number'   => $order->order_number,
            'Customer'       => $order->customer_name . ' (' . $order->customer_company . ')',
            'Email'          => $order->customer_email,
            'Phone'          => $order->customer_phone,
            'Job Site'       => $order->jobsite_address,
            'Equipment'      => $order->lift_capacity . ' ' . $order->fuel_type,
            'Duration'       => ucfirst($order->duration_type),
            'Add-ons'        => $order->addons ?: 'None',
            'Total Price'    => '$' . number_format($order->total_price, 2),
            'Deposit'        => '$' . number_format($order->deposit_amount, 2),
            'Payment Type'   => $order->payment_type,
            'Payment Status' => $order->payment_status,
            'Notes'          => $order->rental_notes,
            'Submitted'      => self::fmt_date( $order->created_at ),
        );
        foreach ( $fields as $label => $value ) {
            $message .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;width:160px;"><strong>' . esc_html($label) . '</strong></td>';
            $message .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($value) . '</td></tr>';
        }
        $message .= '</table>';
        $message .= '<p><a href="' . esc_url($pdf_url) . '" style="background:#333;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;">Download Signed Agreement (PDF)</a></p>';
        $message .= '<p><a href="' . esc_url(admin_url('admin.php?page=frs-orders&order=' . $order->order_number)) . '">View in Admin Panel</a></p>';
        $message .= '</body></html>';

        self::send( $to, $subject, $message );
    }

    private static function send( $to, $subject, $message ) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('frs_company_name') . ' <' . get_option('admin_email') . '>',
        );
        wp_mail( $to, $subject, $message, $headers );
    }
}
