<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Installer {

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $orders  = $wpdb->prefix . 'frs_orders';
        $sql = "CREATE TABLE IF NOT EXISTS $orders (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_number  VARCHAR(30)  NOT NULL,
            status        VARCHAR(20)  NOT NULL DEFAULT 'pending',
            lift_capacity VARCHAR(100) NOT NULL,
            fuel_type     VARCHAR(500) NOT NULL DEFAULT '',
            duration_type VARCHAR(100) NOT NULL,
            addons        TEXT,
            base_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
            addons_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
            fuel_charge   DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_price   DECIMAL(10,2) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_type  VARCHAR(30)  NOT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            stripe_payment_intent VARCHAR(100),
            customer_name    VARCHAR(100) NOT NULL,
            customer_company VARCHAR(100),
            customer_phone   VARCHAR(30)  NOT NULL,
            customer_email   VARCHAR(100) NOT NULL,
            jobsite_address  TEXT,
            rental_notes     TEXT,
            signature_data   LONGTEXT,
            agreement_accepted TINYINT(1) NOT NULL DEFAULT 0,
            pdf_path         VARCHAR(255),
            created_at       DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        self::seed_default_settings();
        add_option( 'frs_db_version', FRS_VERSION );
    }

    public static function deactivate() {}

    public static function upgrade() {
        global $wpdb;
        $table = $wpdb->prefix . 'frs_orders';

        // Check if table exists first
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table ) return;

        // Fix lift_capacity column if too short
        $col = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'lift_capacity'");
        if ( $col && strpos($col->Type, 'varchar(10)') !== false ) {
            $wpdb->query("ALTER TABLE $table MODIFY lift_capacity VARCHAR(100) NOT NULL DEFAULT ''");
        }

        // Fix fuel_type column if too short
        $col2 = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'fuel_type'");
        if ( $col2 && ( strpos($col2->Type, 'varchar(20)') !== false || strpos($col2->Type, 'varchar(30)') !== false ) ) {
            $wpdb->query("ALTER TABLE $table MODIFY fuel_type VARCHAR(500) NOT NULL DEFAULT ''");
        }

        // Fix duration_type column if too short
        $col3 = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'duration_type'");
        if ( $col3 && strpos($col3->Type, 'varchar(10)') !== false ) {
            $wpdb->query("ALTER TABLE $table MODIFY duration_type VARCHAR(100) NOT NULL DEFAULT ''");
        }

        // Add fuel_charge column if missing
        $col4 = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'fuel_charge'");
        if ( ! $col4 ) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN fuel_charge DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER addons_price");
        }
    }

    private static function seed_default_settings() {
        $defaults = array(
            'frs_pricing' => array(
                'lift_capacity' => array(
                    '3K'  => array('day'=>150,'week'=>600,'month'=>1800),
                    '4K'  => array('day'=>160,'week'=>640,'month'=>1920),
                    '5K'  => array('day'=>175,'week'=>700,'month'=>2100),
                    '6K'  => array('day'=>190,'week'=>760,'month'=>2280),
                    '8K'  => array('day'=>220,'week'=>880,'month'=>2640),
                    '10K' => array('day'=>260,'week'=>1040,'month'=>3120),
                    '15K' => array('day'=>320,'week'=>1280,'month'=>3840),
                    '20K' => array('day'=>400,'week'=>1600,'month'=>4800),
                    '36K' => array('day'=>550,'week'=>2200,'month'=>6600),
                ),
                'fuel_surcharge' => array(
                    'Electric' => 0,
                    'Propane'  => 15,
                    'Diesel'   => 20,
                ),
                'fuel_charges' => array(
                    'Propane' => array('quarter'=>25,'half'=>45,'three_quarter'=>65,'full'=>85),
                    'Gas'     => array('quarter'=>20,'half'=>40,'three_quarter'=>60,'full'=>80),
                    'Diesel'  => array('quarter'=>30,'half'=>55,'three_quarter'=>80,'full'=>110),
                ),
                'electric_equipment' => array(
                    'Sitdown_3000_4000'   => array('label'=>'Sitdown / Counter Balance Stand Up','capacity'=>'3000 - 4000 lb','day'=>150,'week'=>600,'month'=>1800),
                    'Sitdown_4000_5000'   => array('label'=>'Sitdown / Counter Balance Stand Up','capacity'=>'4000 - 5000 lb','day'=>175,'week'=>700,'month'=>2100),
                    'Standup_Reach'       => array('label'=>'Reach Truck','capacity'=>'3000 - 3500 lb','day'=>160,'week'=>640,'month'=>1920),
                    'Standup_OrderPicker' => array('label'=>'Order Picker','capacity'=>'3000 - 3500 lb','day'=>160,'week'=>640,'month'=>1920),
                    'Pallet_Jack'         => array('label'=>'Electric Pallet Jack','capacity'=>'3000 - 4500 lb','day'=>60,'week'=>240,'month'=>720),
                    'Walkie_Stacker'      => array('label'=>'Walkie Stacker','capacity'=>'3000 - 4500 lb','day'=>80,'week'=>320,'month'=>960),
                ),
                'addons' => array(
                    'battery_charger' => array('label'=>'Battery Charger','price'=>25,'enabled'=>true),
                    'longer_forks'    => array('label'=>'Longer Forks','price'=>20,'enabled'=>true),
                    'tire_chains'     => array('label'=>'Tire Chains','price'=>30,'enabled'=>true),
                ),
                'deposit_amount'    => 50,
            ),
            'frs_agreement_text' => self::default_agreement(),
            'frs_stripe_test_key' => '',
            'frs_stripe_live_key' => '',
            'frs_stripe_mode'     => 'test',
            'frs_admin_email'     => get_option('admin_email'),
            'frs_company_name'    => get_bloginfo('name'),
        );
        foreach ( $defaults as $key => $value ) {
            if ( ! get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    private static function default_agreement() {
        return 'FORKLIFT RENTAL AGREEMENT

This Rental Agreement ("Agreement") is entered into between {company_name} ("Company") and {customer_name} / {customer_company} ("Renter") on {rental_date}.

1. EQUIPMENT
Renter agrees to rent the following equipment: {equipment_details}

2. RENTAL PERIOD
Rental Duration: {duration_type} commencing on the delivery date.

3. PAYMENT
Total Rental Amount: {total_price}
Deposit Paid: {deposit_amount}
Payment Method: {payment_type}

4. USE OF EQUIPMENT
Renter agrees to use the equipment only for lawful purposes and in a safe manner. Renter shall not allow unlicensed or untrained operators to use the equipment.

5. LIABILITY
Renter accepts full responsibility for the equipment during the rental period. Any damage beyond normal wear and tear will be charged to the Renter.

6. RETURN
Equipment must be returned in the same condition as received. Late returns will be charged at the daily rate.

7. ACCEPTANCE
By signing below, Renter agrees to all terms and conditions of this Agreement.

Order Number: {order_number}
Date: {rental_date}';
    }
}
