<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_PDF {

    public static function generate( $order ) {
        $upload_dir = wp_upload_dir();
        $pdf_dir    = $upload_dir['basedir'] . '/frs-agreements/';

        if ( ! file_exists( $pdf_dir ) ) {
            wp_mkdir_p( $pdf_dir );
            file_put_contents( $pdf_dir . 'index.php', '<?php // silence' );
            // Block direct access to this folder
            file_put_contents( $pdf_dir . '.htaccess', "Options -Indexes\ndeny from all\n" );
        }

        $tcpdf_path = FRS_PLUGIN_DIR . 'includes/tcpdf/tcpdf.php';

        if ( file_exists( $tcpdf_path ) ) {
            // True PDF via TCPDF
            $filename = 'agreement-' . sanitize_file_name( $order->order_number ) . '.pdf';
            $filepath = $pdf_dir . $filename;
            require_once $tcpdf_path;
            self::generate_with_tcpdf( $order, $filepath );
        } else {
            // Fallback: save as .html (browsers can open/print this)
            $filename = 'agreement-' . sanitize_file_name( $order->order_number ) . '.html';
            $filepath = $pdf_dir . $filename;
            self::generate_html_fallback( $order, $filepath );
        }

        return $pdf_dir . $filename;
    }

    private static function generate_with_tcpdf( $order, $filepath ) {
        $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
        $pdf->SetCreator( 'Forklift Rental System' );
        $pdf->SetAuthor( get_option( 'frs_company_name' ) );
        $pdf->SetTitle( 'Rental Agreement - ' . $order->order_number );
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );
        $pdf->AddPage();
        $pdf->SetFont( 'helvetica', '', 11 );
        $html = self::build_pdf_html( $order );
        $pdf->writeHTML( $html, true, false, true, false, '' );

        if ( ! empty( $order->signature_data ) && strpos( $order->signature_data, 'data:image' ) === 0 ) {
            $img_data = base64_decode( preg_replace( '#^data:image/\w+;base64,#i', '', $order->signature_data ) );
            $tmp      = tempnam( sys_get_temp_dir(), 'sig_' ) . '.png';
            file_put_contents( $tmp, $img_data );
            $pdf->Image( $tmp, 20, $pdf->GetY() + 5, 80, 30 );
            unlink( $tmp );
        }

        $pdf->Output( $filepath, 'F' );
    }

    private static function generate_html_fallback( $order, $filepath ) {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
        <title>Rental Agreement - ' . esc_html( $order->order_number ) . '</title>
        <style>
            @media print { .no-print { display:none; } }
            body { font-family: Arial, sans-serif; font-size: 13px; color: #222; max-width: 800px; margin: 0 auto; padding: 30px; }
            .header { background: #1a3c5e; color: #fff; padding: 24px 30px; border-radius: 8px; margin-bottom: 24px; }
            .header h1 { margin: 0 0 4px; font-size: 20px; }
            .header p { margin: 0; opacity: 0.7; font-size: 13px; }
            .order-badge { display: inline-block; background: #f59e0b; color: #fff; padding: 4px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; margin-bottom: 20px; }
            h2 { font-size: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; color: #1a3c5e; margin-top: 24px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            td, th { padding: 9px 12px; border: 1px solid #e2e8f0; font-size: 13px; }
            th { background: #f8fafc; font-weight: 600; color: #64748b; width: 35%; text-align: left; }
            .total-row td { font-weight: 700; font-size: 15px; background: #eef3f9; color: #1a3c5e; }
            .agreement-text { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; white-space: pre-wrap; font-size: 12px; line-height: 1.8; color: #444; margin-bottom: 20px; }
            .sig-box { border: 2px dashed #cbd5e1; border-radius: 8px; padding: 16px; text-align: center; margin-top: 10px; }
            .sig-box img { max-width: 320px; max-height: 100px; display: block; margin: 0 auto; }
            .sig-label { font-size: 12px; color: #94a3b8; margin-top: 8px; }
            .print-btn { display: inline-block; background: #1a3c5e; color: #fff; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; border: none; margin-bottom: 20px; }
            .footer { margin-top: 30px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; }
        </style>
        </head><body>';

        $html .= '<button class="print-btn no-print" onclick="window.print()">&#128424; Print / Save as PDF</button>';
        $html .= '<div class="header"><h1>' . esc_html( get_option('frs_company_name') ) . '</h1><p>Forklift Rental Agreement</p></div>';
        $html .= '<div class="order-badge">Order # ' . esc_html( $order->order_number ) . '</div>';
        $html .= self::build_pdf_html( $order );

        if ( ! empty( $order->signature_data ) ) {
            $html .= '<h2>Customer Signature</h2>';
            $html .= '<div class="sig-box"><img src="' . esc_attr( $order->signature_data ) . '"><div class="sig-label">Signed electronically on ' . date( 'm-d-Y', strtotime( $order->created_at ) ) . '</div></div>';
        }

        $html .= '<div class="footer">This document was generated automatically by ' . esc_html( get_option('frs_company_name') ) . ' &mdash; Order ' . esc_html( $order->order_number ) . ' &mdash; ' . date('Y') . '</div>';
        $html .= '</body></html>';

        file_put_contents( $filepath, $html );
    }

    private static function build_pdf_html( $order ) {
        $pricing      = FRS_Settings::get_pricing();
        $addons       = $order->addons ? explode( ',', $order->addons ) : array();
        $addon_labels = array();
        foreach ( $addons as $k ) {
            if ( isset( $pricing['addons'][ $k ]['label'] ) ) {
                $addon_labels[] = $pricing['addons'][ $k ]['label'];
            }
        }

        $agreement    = FRS_Settings::get_agreement_text();
        $replacements = array(
            '{company_name}'      => esc_html( get_option('frs_company_name') ),
            '{customer_name}'     => esc_html( $order->customer_name ),
            '{customer_company}'  => esc_html( $order->customer_company ),
            '{rental_date}'       => date( 'm-d-Y', strtotime( $order->created_at ) ),
            '{equipment_details}' => esc_html( $order->lift_capacity . ' ' . $order->fuel_type . ' - ' . ucfirst( $order->duration_type ) . ' Rental' ),
            '{duration_type}'     => esc_html( ucfirst( $order->duration_type ) ),
            '{total_price}'       => '$' . number_format( $order->total_price, 2 ),
            '{deposit_amount}'    => '$' . number_format( $order->deposit_amount, 2 ),
            '{payment_type}'      => esc_html( $order->payment_type ),
            '{order_number}'      => esc_html( $order->order_number ),
        );
        $agreement = str_replace( array_keys( $replacements ), array_values( $replacements ), $agreement );

        $html  = '<h2>Customer Information</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Name</th><td>' . esc_html( $order->customer_name ) . '</td></tr>';
        $html .= '<tr><th>Company</th><td>' . esc_html( $order->customer_company ) . '</td></tr>';
        $html .= '<tr><th>Email</th><td>' . esc_html( $order->customer_email ) . '</td></tr>';
        $html .= '<tr><th>Phone</th><td>' . esc_html( $order->customer_phone ) . '</td></tr>';
        $html .= '<tr><th>Job Site Address</th><td>' . esc_html( $order->jobsite_address ) . '</td></tr>';
        $html .= '</table>';

        $html .= '<h2>Equipment & Pricing</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Lift Capacity</th><td>' . esc_html( $order->lift_capacity ) . '</td></tr>';
        $html .= '<tr><th>Fuel Type</th><td>' . esc_html( $order->fuel_type ) . '</td></tr>';
        $html .= '<tr><th>Rental Duration</th><td>' . esc_html( ucfirst( $order->duration_type ) ) . '</td></tr>';
        $html .= '<tr><th>Add-ons</th><td>' . esc_html( implode( ', ', $addon_labels ) ?: 'None' ) . '</td></tr>';
        $html .= '<tr><th>Base Price</th><td>$' . number_format( $order->base_price, 2 ) . '</td></tr>';
        $html .= '<tr><th>Add-ons Price</th><td>$' . number_format( $order->addons_price, 2 ) . '</td></tr>';
        $html .= '<tr class="total-row"><th>Total Price</th><td>$' . number_format( $order->total_price, 2 ) . '</td></tr>';
        $html .= '<tr><th>Deposit Paid</th><td>$' . number_format( $order->deposit_amount, 2 ) . '</td></tr>';
        $html .= '<tr><th>Payment Method</th><td>' . esc_html( $order->payment_type ) . '</td></tr>';
        $html .= '</table>';

        if ( $order->rental_notes ) {
            $html .= '<h2>Rental Notes</h2><p>' . esc_html( $order->rental_notes ) . '</p>';
        }

        $html .= '<h2>Rental Agreement Terms</h2>';
        $html .= '<div class="agreement-text">' . esc_html( $agreement ) . '</div>';

        return $html;
    }

    /**
     * Get the public download URL for the agreement.
     * Uses a secure WordPress AJAX endpoint instead of direct file URL.
     */
    public static function get_url( $order ) {
        return add_query_arg( array(
            'action'       => 'frs_download_agreement',
            'order_number' => urlencode( $order->order_number ),
            'nonce'        => wp_create_nonce( 'frs_download_' . $order->order_number ),
        ), admin_url( 'admin-ajax.php' ) );
    }

    /**
     * Serve the file via AJAX download handler (registered in FRS_Public).
     */
    public static function handle_download() {
        $order_number = isset( $_GET['order_number'] ) ? sanitize_text_field( $_GET['order_number'] ) : '';
        $nonce        = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

        if ( ! $order_number || ! wp_verify_nonce( $nonce, 'frs_download_' . $order_number ) ) {
            wp_die( 'Invalid or expired download link. Please request a new copy from your confirmation email.', 403 );
        }

        $order = FRS_Order::get_by_order_number( $order_number );
        if ( ! $order || empty( $order->pdf_path ) ) {
            wp_die( 'Agreement file not found for this order.', 404 );
        }

        $filepath = $order->pdf_path;

        if ( ! file_exists( $filepath ) ) {
            // Try regenerating
            $filepath = self::generate( $order );
            if ( ! file_exists( $filepath ) ) {
                wp_die( 'Could not generate the agreement file. Please contact support.', 500 );
            }
        }

        $ext      = pathinfo( $filepath, PATHINFO_EXTENSION );
        $filename = 'rental-agreement-' . $order_number . '.' . $ext;

        if ( $ext === 'pdf' ) {
            header( 'Content-Type: application/pdf' );
        } else {
            header( 'Content-Type: text/html; charset=UTF-8' );
        }

        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $filepath ) );
        header( 'Cache-Control: private, no-cache' );
        header( 'Pragma: no-cache' );

        readfile( $filepath );
        exit;
    }
}
