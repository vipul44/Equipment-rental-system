<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FRS_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menus' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_frs_save_pricing',        array( $this, 'ajax_save_pricing' ) );
        add_action( 'wp_ajax_frs_delete_order',        array( $this, 'ajax_delete_order' ) );
        add_action( 'wp_ajax_frs_bulk_delete',         array( $this, 'ajax_bulk_delete' ) );
        add_action( 'wp_ajax_frs_upload_equipment_img',array( $this, 'ajax_upload_equipment_img' ) );
        add_action( 'wp_ajax_frs_remove_equipment_img',array( $this, 'ajax_remove_equipment_img' ) );
        add_action( 'wp_ajax_frs_save_agreement',      array( $this, 'ajax_save_agreement' ) );
    }

    public function register_menus() {
        add_menu_page( 'Forklift Rental', 'Forklift Rental', 'manage_options', 'frs-orders', array( $this, 'page_orders' ), 'dashicons-car', 30 );
        add_submenu_page( 'frs-orders', 'All Orders',        'All Orders',        'manage_options', 'frs-orders',    array( $this, 'page_orders' ) );
        add_submenu_page( 'frs-orders', 'Pricing & Add-ons', 'Pricing & Add-ons', 'manage_options', 'frs-pricing',   array( $this, 'page_pricing' ) );
        add_submenu_page( 'frs-orders', 'Settings',          'Settings',          'manage_options', 'frs-settings',  array( $this, 'page_settings' ) );
        add_submenu_page( 'frs-orders', 'Agreement Text',    'Agreement Text',    'manage_options', 'frs-agreement', array( $this, 'page_agreement' ) );
    }

    public function register_settings() {
        register_setting( 'frs_settings_group', 'frs_stripe_test_key' );
        register_setting( 'frs_settings_group', 'frs_stripe_pub_test_key' );
        register_setting( 'frs_settings_group', 'frs_stripe_live_key' );
        register_setting( 'frs_settings_group', 'frs_stripe_pub_live_key' );
        register_setting( 'frs_settings_group', 'frs_stripe_mode' );
        register_setting( 'frs_settings_group', 'frs_admin_email' );
        register_setting( 'frs_settings_group', 'frs_company_name' );
        register_setting( 'frs_agreement_group', 'frs_agreement_text' );
    }

    public function enqueue_scripts( $hook ) {
        $frs_pages = array(
            'toplevel_page_frs-orders',
            'forklift-rental_page_frs-orders',
            'forklift-rental_page_frs-pricing',
            'forklift-rental_page_frs-settings',
            'forklift-rental_page_frs-agreement',
        );
        $is_frs = in_array( $hook, $frs_pages ) || strpos( $hook, 'frs-' ) !== false;
        if ( ! $is_frs ) return;

        // Always load media library so Upload buttons work on pricing page
        wp_enqueue_media();
        wp_enqueue_style( 'frs-admin', FRS_PLUGIN_URL . 'admin/admin.css', array(), FRS_VERSION );
        wp_enqueue_script( 'frs-admin', FRS_PLUGIN_URL . 'admin/admin.js', array( 'jquery' ), FRS_VERSION, true );
        wp_localize_script( 'frs-admin', 'frs_admin', array(
            'nonce'    => wp_create_nonce( 'frs_admin_nonce' ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ) );
    }

    /* =====================================================================
       ORDERS PAGE
    ===================================================================== */
    public function page_orders() {
        $order_number = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
        if ( $order_number ) {
            $order = FRS_Order::get_by_order_number( $order_number );
            if ( $order ) { $this->render_order_detail( $order ); return; }
        }

        $search   = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $per_page = 20;
        $page     = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset   = ($page - 1) * $per_page;
        $orders   = FRS_Order::get_all( array('limit'=>$per_page,'offset'=>$offset,'search'=>$search) );
        $total    = FRS_Order::count( $search );
        $pages    = ceil( $total / $per_page );
        ?>
        <div class="wrap frs-wrap">
            <h1>Forklift Rental Orders <span class="frs-count"><?php echo esc_html($total); ?></span></h1>
            <div class="frs-search-bar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="frs-orders">
                    <div class="frs-search-input-wrap">
                        <span class="frs-search-icon">&#128269;</span>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by order #, name, email, company, phone, equipment...">
                        <?php if ($search): ?><a href="<?php echo esc_url(admin_url('admin.php?page=frs-orders')); ?>" class="frs-search-clear">&times;</a><?php endif; ?>
                    </div>
                    <button type="submit" class="button">Search</button>
                </form>
                <?php if ($search): ?><p class="frs-search-results-info">Showing <strong><?php echo esc_html($total); ?></strong> result<?php echo $total!==1?'s':''; ?> for &ldquo;<strong><?php echo esc_html($search); ?></strong>&rdquo;</p><?php endif; ?>
            </div>
            <div class="frs-bulk-bar" id="frs-bulk-bar" style="display:none">
                <span id="frs-sel-count">0 orders selected</span>
                <button class="button button-link-delete frs-bulk-delete-btn" id="frs-bulk-del">&#128465; Delete Selected</button>
                <button class="button frs-bulk-cancel" id="frs-bulk-cancel">Cancel</button>
            </div>
            <?php if ( empty($orders) ): ?>
            <div class="frs-no-results"><?php if($search): ?>No orders found matching &ldquo;<strong><?php echo esc_html($search); ?></strong>&rdquo;. <a href="<?php echo esc_url(admin_url('admin.php?page=frs-orders')); ?>">View all orders</a><?php else: ?>No orders yet.<?php endif; ?></div>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped frs-table">
                <thead><tr>
                    <th class="frs-col-check"><input type="checkbox" id="frs-chk-all"></th>
                    <th>Order #</th><th>Customer</th><th>Equipment</th><th>Duration</th>
                    <th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>PDF</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $orders as $o ) : ?>
                <tr id="frs-row-<?php echo esc_attr($o->order_number); ?>" data-order="<?php echo esc_attr($o->order_number); ?>">
                    <td class="frs-col-check"><input type="checkbox" class="frs-row-chk" value="<?php echo esc_attr($o->order_number); ?>"></td>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=frs-orders&order='.urlencode($o->order_number))); ?>"><?php echo esc_html($o->order_number); ?></a></td>
                    <td><?php echo esc_html($o->customer_name); ?><br><small><?php echo esc_html($o->customer_email); ?></small></td>
                    <td><?php echo esc_html($o->lift_capacity . ' ' . $o->fuel_type); ?></td>
                    <td><?php echo esc_html(ucfirst($o->duration_type)); ?></td>
                    <td>$<?php echo number_format($o->total_price,2); ?></td>
                    <td><?php echo esc_html($o->payment_type); ?><br><small><?php echo esc_html($o->payment_status); ?></small></td>
                    <td><span class="frs-status frs-status-<?php echo esc_attr($o->status); ?>"><?php echo esc_html($o->status); ?></span></td>
                    <td><?php echo esc_html(date('m-d-Y', strtotime($o->created_at))); ?></td>
                    <td><?php if($o->pdf_path): ?><a href="<?php echo esc_url(FRS_PDF::get_url($o)); ?>" target="_blank">Download</a><?php endif; ?></td>
                    <td><button class="button button-link-delete frs-delete-single" data-order="<?php echo esc_attr($o->order_number); ?>">&#128465; Delete</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?>
            <div class="tablenav bottom"><div class="tablenav-pages">
                <?php for($i=1;$i<=$pages;$i++): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=frs-orders&paged='.$i.($search?'&s='.urlencode($search):'')));?>" class="<?php echo $i==$page?'current':'button';?>"><?php echo $i;?></a>
                <?php endfor; ?>
            </div></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_order_detail( $order ) {
        $pricing = FRS_Settings::get_pricing();
        $addons  = $order->addons ? explode(',', $order->addons) : array();
        $addon_labels = array();
        foreach ($addons as $k) { if (isset($pricing['addons'][$k]['label'])) $addon_labels[] = $pricing['addons'][$k]['label']; }
        ?>
        <div class="wrap frs-wrap">
            <h1>Order: <?php echo esc_html($order->order_number); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=frs-orders')); ?>">&larr; Back to Orders</a>
            <div class="frs-order-detail">
                <div class="frs-detail-section">
                    <h2>Customer Information</h2>
                    <table class="frs-detail-table">
                        <tr><th>Name</th><td><?php echo esc_html($order->customer_name); ?></td></tr>
                        <tr><th>Company</th><td><?php echo esc_html($order->customer_company); ?></td></tr>
                        <tr><th>Email</th><td><?php echo esc_html($order->customer_email); ?></td></tr>
                        <tr><th>Phone</th><td><?php echo esc_html($order->customer_phone); ?></td></tr>
                        <tr><th>Job Site</th><td><?php echo esc_html($order->jobsite_address); ?></td></tr>
                        <tr><th>Notes</th><td><?php echo esc_html($order->rental_notes); ?></td></tr>
                    </table>
                </div>
                <div class="frs-detail-section">
                    <h2>Equipment & Pricing</h2>
                    <table class="frs-detail-table">
                        <tr><th>Lift Capacity</th><td><?php echo esc_html($order->lift_capacity); ?></td></tr>
                        <tr><th>Fuel Type</th><td><?php echo esc_html($order->fuel_type); ?></td></tr>
                        <tr><th>Duration</th><td><?php echo esc_html(ucfirst($order->duration_type)); ?></td></tr>
                        <tr><th>Add-ons</th><td><?php echo esc_html(implode(', ', $addon_labels)?:'None'); ?></td></tr>
                        <tr><th>Base Price</th><td>$<?php echo number_format($order->base_price,2); ?></td></tr>
                        <tr><th>Add-ons Price</th><td>$<?php echo number_format($order->addons_price,2); ?></td></tr>
                        <tr><th>Total Price</th><td><strong>$<?php echo number_format($order->total_price,2); ?></strong></td></tr>
                        <tr><th>Deposit</th><td>$<?php echo number_format($order->deposit_amount,2); ?></td></tr>
                        <tr><th>Payment Type</th><td><?php echo esc_html($order->payment_type); ?></td></tr>
                        <tr><th>Payment Status</th><td><?php echo esc_html($order->payment_status); ?></td></tr>
                        <tr><th>Submitted</th><td><?php echo esc_html(date('m-d-Y', strtotime($order->created_at))); ?></td></tr>
                    </table>
                </div>
                <?php if ($order->signature_data): ?>
                <div class="frs-detail-section"><h2>Customer Signature</h2><div class="frs-sig-preview"><img src="<?php echo esc_attr($order->signature_data); ?>"></div></div>
                <?php endif; ?>
                <?php if ($order->pdf_path): ?>
                <div class="frs-detail-section"><h2>Signed Agreement</h2><a href="<?php echo esc_url(FRS_PDF::get_url($order)); ?>" class="button button-primary" target="_blank">Download PDF Agreement</a></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* =====================================================================
       PRICING CONTROL PANEL
    ===================================================================== */
    public function page_pricing() {
        $pricing        = FRS_Settings::get_pricing();
        $imgs           = get_option( 'frs_equipment_images', array() );
        $cat1           = isset($pricing['lift_capacity'])        ? $pricing['lift_capacity']        : array();
        $elec           = isset($pricing['electric_equipment'])   ? $pricing['electric_equipment']   : array();
        $fuel           = isset($pricing['fuel_charges'])         ? $pricing['fuel_charges']         : array(
            'Propane'=>array('quarter'=>25,'half'=>45,'three_quarter'=>65,'full'=>85),
            'Gas'    =>array('quarter'=>20,'half'=>40,'three_quarter'=>60,'full'=>80),
            'Diesel' =>array('quarter'=>30,'half'=>55,'three_quarter'=>80,'full'=>110),
        );
        $addons   = isset($pricing['addons'])         ? $pricing['addons']         : array();
        $deposit  = isset($pricing['deposit_amount']) ? $pricing['deposit_amount'] : 50;
        if ( empty($elec) ) {
            $elec = array(
                'Sit_3k4k' =>array('label'=>'3000–4000 lb','group'=>'Class 1 – Sitdown / Counter Balance Stand Up','capacity'=>'3000–4000 lb','day'=>150,'week'=>600,'month'=>1800),
                'Sit_4k5k' =>array('label'=>'4000–5000 lb','group'=>'','capacity'=>'4000–5000 lb','day'=>175,'week'=>700,'month'=>2100),
                'Reach'    =>array('label'=>'Reach Truck','group'=>'Class 2 – Standup (3000–3500 lb)','capacity'=>'','day'=>160,'week'=>640,'month'=>1920),
                'Order_Pick'=>array('label'=>'Order Picker','group'=>'','capacity'=>'','day'=>160,'week'=>640,'month'=>1920),
                'PalletJack'=>array('label'=>'Electric Pallet Jack','group'=>'Class 3 (3000–4500 lb)','capacity'=>'','day'=>60,'week'=>240,'month'=>720),
                'Walkie'   =>array('label'=>'Walkie Stacker','group'=>'','capacity'=>'','day'=>80,'week'=>320,'month'=>960),
            );
        }
        ?>
        <div class="wrap frs-wrap frs-pricing-page">
        <form id="frs-pricing-form">
        <?php wp_nonce_field('frs_admin_nonce','frs_nonce'); ?>

        <!-- TOP BAR -->
        <div class="frs-topbar">
            <div class="frs-topbar-left">
                <h1>Pricing Control Panel</h1>
                <p>Manage equipment pricing, fuel charges, add-ons, and deposit amount.</p>
            </div>
            <div style="display:flex;align-items:center;gap:0">
                <button type="submit" class="frs-btn-save">Save All Changes</button>
                <span class="frs-save-msg" id="frs-save-msg"></span>
            </div>
        </div>

        <div class="frs-grid">

            <!-- ROW 1: Half/Half — Cat1 + Electric -->
            <div class="frs-grid-half">

                <!-- CARD 1: Propane/Gas/Diesel -->
                <div class="frs-card" id="frs-card-cat1">
                    <div class="frs-card-head frs-card-head-blue">
                        <div class="frs-icon frs-icon-blue">&#128663;</div>
                        <div class="frs-card-head-info">
                            <h2>1. Equipment Base Pricing (Propane, Gas, Diesel)</h2>
                            <p>Set base rental rates by capacity.</p>
                        </div>
                        <div class="frs-card-head-actions">
                            <button type="button" class="frs-btn-edit" data-card="frs-card-cat1">Edit</button>
                        </div>
                    </div>
                    <div class="frs-card-body">
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr>
                                <th>Capacity</th>
                                <th class="frs-edit-cell">Image</th>
                                <th>Day Rate</th><th>Week Rate</th><th>Month Rate</th>
                                <th class="frs-edit-cell"></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ( $cat1 as $cap => $rates ) :
                                $ikey = 'cat1_' . $cap;
                                // Also check lowercase version for backwards compatibility
                                $iurl = isset($imgs[$ikey]) ? $imgs[$ikey] : ( isset($imgs['cat1_'.strtolower($cap)]) ? $imgs['cat1_'.strtolower($cap)] : '' );
                            ?>
                            <tr class="frs-eq-row" data-key="<?php echo esc_attr($cap); ?>" data-cat="lift_capacity">
                                <td>
                                    <span class="frs-vm"><?php echo esc_html($cap); ?></span>
                                    <input type="text" class="frs-em frs-cap-lbl" name="pricing[lift_capacity_keys][]" value="<?php echo esc_attr($cap); ?>" style="width:72px">
                                </td>
                                <td class="frs-edit-cell">
                                    <div class="frs-img-cell">
                                        <?php if($iurl): ?><img src="<?php echo esc_url($iurl); ?>" class="frs-img-thumb"><?php else: ?><div class="frs-img-ph" data-cat="lift_capacity" data-k="<?php echo esc_attr($cap); ?>">&#128247;</div><?php endif; ?>
                                        <input type="hidden" class="frs-img-val" name="pricing[lift_capacity_images][<?php echo esc_attr($cap); ?>]" value="<?php echo esc_attr($iurl); ?>">
                                        <button type="button" class="frs-btn-img-upload" data-cat="lift_capacity" data-k="<?php echo esc_attr($cap); ?>"><?php echo $iurl?'Change':'Upload'; ?></button>
                                        <?php if($iurl): ?><button type="button" class="frs-btn-img-remove" data-cat="lift_capacity" data-k="<?php echo esc_attr($cap); ?>">&#10005;</button><?php endif; ?>
                                    </div>
                                </td>
                                <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[lift_capacity][<?php echo esc_attr($cap); ?>][day]" value="<?php echo esc_attr((isset($rates['day']) ? $rates['day'] : 0)); ?>"></div></td>
                                <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[lift_capacity][<?php echo esc_attr($cap); ?>][week]" value="<?php echo esc_attr((isset($rates['week']) ? $rates['week'] : 0)); ?>"></div></td>
                                <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[lift_capacity][<?php echo esc_attr($cap); ?>][month]" value="<?php echo esc_attr((isset($rates['month']) ? $rates['month'] : 0)); ?>"></div></td>
                                <td class="frs-edit-cell"><button type="button" class="frs-btn-remove-row">Remove</button></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="frs-add-foot">
                                <tr><td colspan="6" class="frs-add-row-td"><button type="button" class="frs-btn-add-row" data-cat="lift_capacity">+ Add Equipment Row</button></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- CARD 2: Electric -->
                <div class="frs-card" id="frs-card-elec">
                    <div class="frs-card-head frs-card-head-amber">
                        <div class="frs-icon frs-icon-amber">&#9889;</div>
                        <div class="frs-card-head-info">
                            <h2>2. Equipment Base Pricing (Electric)</h2>
                            <p>Set base rental rates by type and capacity for electric equipment.</p>
                        </div>
                        <div class="frs-card-head-actions">
                            <button type="button" class="frs-btn-edit" data-card="frs-card-elec">Edit</button>
                        </div>
                    </div>
                    <div class="frs-card-body">
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr>
                                <th>Type / Category</th><th>Capacity</th>
                                <th class="frs-edit-cell">Image</th>
                                <th>Day Rate</th><th>Week Rate</th><th>Month Rate</th>
                                <th class="frs-edit-cell"></th>
                            </tr></thead>
                            <tbody>
                            <?php $lg='';
                            foreach ( $elec as $ek => $ed ) :
                                $g   = (isset($ed['group']) ? $ed['group'] : '');
                                $iu  = isset($imgs['electric_'.$ek]) ? $imgs['electric_'.$ek] : '';
                                if ( $g && $g !== $lg ) { $lg=$g; ?>
                                    <tr class="frs-group-hdr"><td colspan="7"><?php echo esc_html($g); ?></td></tr>
                                <?php } ?>
                                <tr class="frs-eq-row" data-key="<?php echo esc_attr($ek); ?>" data-cat="electric_equipment">
                                    <td>
                                        <span class="frs-vm"><?php echo esc_html((isset($ed['label']) ? $ed['label'] : $ek)); ?></span>
                                        <input type="text" class="frs-em" name="pricing[electric_equipment][<?php echo esc_attr($ek); ?>][label]" value="<?php echo esc_attr((isset($ed['label']) ? $ed['label'] : '')); ?>" style="width:150px">
                                        <input type="hidden" name="pricing[electric_equipment][<?php echo esc_attr($ek); ?>][group]" value="<?php echo esc_attr((isset($ed['group']) ? $ed['group'] : '')); ?>">
                                    </td>
                                    <td>
                                        <span class="frs-vm"><?php echo esc_html(!empty($ed['capacity'])?$ed['capacity']:'—'); ?></span>
                                        <input type="text" class="frs-em" name="pricing[electric_equipment][<?php echo esc_attr($ek); ?>][capacity]" value="<?php echo esc_attr((isset($ed['capacity']) ? $ed['capacity'] : '')); ?>" style="width:100px">
                                    </td>
                                    <td class="frs-edit-cell">
                                        <div class="frs-img-cell">
                                            <?php if($iu): ?><img src="<?php echo esc_url($iu); ?>" class="frs-img-thumb"><?php else: ?><div class="frs-img-ph" data-cat="electric_equipment" data-k="<?php echo esc_attr($ek); ?>">&#128247;</div><?php endif; ?>
                                            <input type="hidden" class="frs-img-val" name="pricing[electric_equipment_images][<?php echo esc_attr($ek); ?>]" value="<?php echo esc_attr($iu); ?>">
                                            <button type="button" class="frs-btn-img-upload" data-cat="electric_equipment" data-k="<?php echo esc_attr($ek); ?>"><?php echo $iu?'Change':'Upload'; ?></button>
                                            <?php if($iu): ?><button type="button" class="frs-btn-img-remove" data-cat="electric_equipment" data-k="<?php echo esc_attr($ek); ?>">&#10005;</button><?php endif; ?>
                                        </div>
                                    </td>
                                    <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[electric_equipment][<?php echo esc_attr($ek); ?>][day]" value="<?php echo esc_attr((isset($ed['day']) ? $ed['day'] : 0)); ?>"></div></td>
                                    <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[electric_equipment][<?php echo esc_attr($ek); ?>][week]" value="<?php echo esc_attr((isset($ed['week']) ? $ed['week'] : 0)); ?>"></div></td>
                                    <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[electric_equipment][<?php echo esc_attr($ek); ?>][month]" value="<?php echo esc_attr((isset($ed['month']) ? $ed['month'] : 0)); ?>"></div></td>
                                    <td class="frs-edit-cell"><button type="button" class="frs-btn-remove-row">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="frs-add-foot">
                                <tr><td colspan="7" class="frs-add-row-td"><button type="button" class="frs-btn-add-row" data-cat="electric_equipment">+ Add Equipment Row</button></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div><!-- end row 1 -->

            <!-- ROW 2: Fuel Charges full width -->
            <div class="frs-grid-full">
                <div class="frs-card">
                    <div class="frs-card-head frs-card-head-teal">
                        <div class="frs-icon frs-icon-teal">&#9881;</div>
                        <div class="frs-card-head-info">
                            <h2>3. Fuel Charges (Propane, Gas, Diesel)</h2>
                            <p>Set fuel charges based on tank level.</p>
                        </div>
                    </div>
                    <div class="frs-card-body">
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr>
                                <th style="width:200px">Fuel Type</th>
                                <th>&frac14; Tank</th><th>&frac12; Tank</th><th>&frac34; Tank</th><th>Full Tank</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ( $fuel as $ft => $lv ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($ft); ?></strong></td>
                                <?php foreach(array('quarter','half','three_quarter','full') as $l): ?>
                                <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[fuel_charges][<?php echo esc_attr($ft); ?>][<?php echo $l; ?>]" value="<?php echo esc_attr((isset($lv[$l]) ? $lv[$l] : 0)); ?>"></div></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ROW 3: Add-ons + Deposit 50/50 -->
            <div class="frs-grid-half2">

                <!-- CARD 4: Add-ons -->
                <div class="frs-card" id="frs-card-addons">
                    <div class="frs-card-head frs-card-head-violet">
                        <div class="frs-icon frs-icon-violet">&#10010;</div>
                        <div class="frs-card-head-info">
                            <h2>4. Add-ons</h2>
                            <p>Manage optional add-ons and their prices.</p>
                        </div>
                        <div class="frs-card-head-actions">
                            <button type="button" class="frs-btn-edit" data-card="frs-card-addons">Edit</button>
                        </div>
                    </div>
                    <div class="frs-card-body">
                        <table class="frs-table" id="frs-table-addons">
                            <thead>
                                <tr>
                                    <th style="width:150px">Key</th>
                                    <th>Label</th>
                                    <th style="width:130px">Price ($)</th>
                                    <th style="width:70px">Enabled</th>
                                    <th class="frs-edit-cell" style="width:80px"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $addons as $ak => $ad ) :
                                $ai = FRS_Settings::find_image_public( $imgs, array( 'addon_'.$ak, 'addon_'.strtolower($ak) ) );
                            ?>
                            <tr class="frs-addon-row" data-key="<?php echo esc_attr($ak); ?>">
                                <td>
                                    <span class="frs-vm" style="font-size:12px;font-weight:600;color:#555"><?php echo esc_html($ak); ?></span>
                                    <input type="hidden" name="pricing[addons][<?php echo esc_attr($ak); ?>][key]" value="<?php echo esc_attr($ak); ?>" class="frs-addon-key-input">
                                    <div class="frs-img-cell" style="margin-top:5px">
                                        <?php if($ai): ?>
                                            <img src="<?php echo esc_url($ai); ?>" class="frs-img-thumb">
                                        <?php else: ?>
                                            <div class="frs-img-ph" data-cat="addon" data-k="<?php echo esc_attr($ak); ?>">&#128247;</div>
                                        <?php endif; ?>
                                        <input type="hidden" class="frs-img-val" name="pricing[addon_images][<?php echo esc_attr($ak); ?>]" value="<?php echo esc_attr($ai); ?>">
                                        <button type="button" class="frs-btn-img-upload" data-cat="addon" data-k="<?php echo esc_attr($ak); ?>"><?php echo $ai ? 'Change' : 'Upload'; ?></button>
                                        <?php if($ai): ?><button type="button" class="frs-btn-img-remove" data-cat="addon" data-k="<?php echo esc_attr($ak); ?>">&#10005;</button><?php endif; ?>
                                    </div>
                                </td>
                                <td><input type="text" name="pricing[addons][<?php echo esc_attr($ak); ?>][label]" value="<?php echo esc_attr($ad['label']); ?>" style="width:160px" placeholder="Add-on label"></td>
                                <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[addons][<?php echo esc_attr($ak); ?>][price]" value="<?php echo esc_attr($ad['price']); ?>"></div></td>
                                <td><input type="checkbox" name="pricing[addons][<?php echo esc_attr($ak); ?>][enabled]" value="1" <?php checked($ad['enabled']); ?>></td>
                                <td class="frs-edit-cell"><button type="button" class="frs-btn-remove-addon">Remove</button></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="frs-add-foot">
                                <tr><td colspan="5" class="frs-add-row-td">
                                    <button type="button" class="frs-btn-add-row" data-cat="addon" id="frs-add-addon-btn">+ Add New Add-on</button>
                                </td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- CARD 5: Deposit -->
                <div class="frs-card">
                    <div class="frs-card-head frs-card-head-slate">
                        <div class="frs-icon frs-icon-slate">&#128737;</div>
                        <div class="frs-card-head-info">
                            <h2>5. Deposit Amount</h2>
                            <p>Set the default deposit amount.</p>
                        </div>
                    </div>
                    <div class="frs-deposit-body">
                        <label class="frs-deposit-label">Deposit Amount ($)</label>
                        <div class="frs-pi">
                            <span class="frs-pi-sym">$</span>
                            <input type="number" step="0.01" min="0" name="pricing[deposit_amount]" value="<?php echo esc_attr($deposit); ?>">
                        </div>
                        <span class="frs-deposit-hint">This deposit amount will be applied to all rental orders.</span>
                    </div>
                </div>

            </div><!-- end row 3 -->

        </div><!-- .frs-grid -->

        <div class="frs-footer">
            <button type="submit" class="frs-btn-save">Save All Changes</button>
            <span class="frs-save-msg" id="frs-save-msg-foot"></span>
        </div>

        </form>
        </div>

        <!-- Row templates -->
        <script type="text/template" id="frs-tpl-lift_capacity">
        <tr class="frs-eq-row" data-key="NEWKEY" data-cat="lift_capacity">
            <td><span class="frs-vm"></span><input type="text" class="frs-em frs-cap-lbl" name="pricing[lift_capacity_keys][]" value="" placeholder="e.g. 40K" style="width:72px"></td>
            <td class="frs-edit-cell"><div class="frs-img-cell"><div class="frs-img-ph" data-cat="lift_capacity" data-k="NEWKEY">&#128247;</div><input type="hidden" class="frs-img-val" name="pricing[lift_capacity_images][NEWKEY]" value=""><button type="button" class="frs-btn-img-upload" data-cat="lift_capacity" data-k="NEWKEY">Upload</button></div></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[lift_capacity][NEWKEY][day]" value="0"></div></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[lift_capacity][NEWKEY][week]" value="0"></div></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[lift_capacity][NEWKEY][month]" value="0"></div></td>
            <td class="frs-edit-cell"><button type="button" class="frs-btn-remove-row">Remove</button></td>
        </tr>
        </script>
        <script type="text/template" id="frs-tpl-electric_equipment">
        <tr class="frs-eq-row" data-key="NEWKEY" data-cat="electric_equipment">
            <td><span class="frs-vm"></span><input type="text" class="frs-em" name="pricing[electric_equipment][NEWKEY][label]" value="" placeholder="Label" style="width:150px"><input type="hidden" name="pricing[electric_equipment][NEWKEY][group]" value=""></td>
            <td><span class="frs-vm">—</span><input type="text" class="frs-em" name="pricing[electric_equipment][NEWKEY][capacity]" value="" placeholder="Capacity" style="width:100px"></td>
            <td class="frs-edit-cell"><div class="frs-img-cell"><div class="frs-img-ph" data-cat="electric_equipment" data-k="NEWKEY">&#128247;</div><input type="hidden" class="frs-img-val" name="pricing[electric_equipment_images][NEWKEY]" value=""><button type="button" class="frs-btn-img-upload" data-cat="electric_equipment" data-k="NEWKEY">Upload</button></div></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[electric_equipment][NEWKEY][day]" value="0"></div></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[electric_equipment][NEWKEY][week]" value="0"></div></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[electric_equipment][NEWKEY][month]" value="0"></div></td>
            <td class="frs-edit-cell"><button type="button" class="frs-btn-remove-row">Remove</button></td>
        </tr>
        </script>
        <script type="text/template" id="frs-tpl-addon">
        <tr class="frs-addon-row" data-key="NEWKEY">
            <td>
                <input type="text" class="frs-addon-key-input" name="pricing[addons][NEWKEY][key]" value="" placeholder="e.g. forklift_horn" style="width:130px;font-size:12px" title="Unique key (no spaces)">
                <div class="frs-img-cell" style="margin-top:5px">
                    <div class="frs-img-ph" data-cat="addon" data-k="NEWKEY">&#128247;</div>
                    <input type="hidden" class="frs-img-val" name="pricing[addon_images][NEWKEY]" value="">
                    <button type="button" class="frs-btn-img-upload" data-cat="addon" data-k="NEWKEY">Upload</button>
                </div>
            </td>
            <td><input type="text" name="pricing[addons][NEWKEY][label]" value="" placeholder="Display label" style="width:160px"></td>
            <td><div class="frs-pi"><span class="frs-pi-sym">$</span><input type="number" step="0.01" min="0" name="pricing[addons][NEWKEY][price]" value="0"></div></td>
            <td><input type="checkbox" name="pricing[addons][NEWKEY][enabled]" value="1" checked></td>
            <td class="frs-edit-cell"><button type="button" class="frs-btn-remove-addon">Remove</button></td>
        </tr>
        </script>
        <?php
    }
    /* =====================================================================
       AJAX: Save Pricing
    ===================================================================== */
    public function ajax_save_pricing() {

        // Verify nonce
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'frs_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
            wp_die();
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
            wp_die();
        }

        // $_POST['pricing'] is sent as a nested array from form serialization
        $raw      = isset( $_POST['pricing'] ) ? $_POST['pricing'] : array();
        $existing = FRS_Settings::get_pricing();

        // --- Propane/Gas/Diesel (lift_capacity) ---
        $new_cat1 = array();
        $keys     = isset( $raw['lift_capacity_keys'] ) ? array_map( 'sanitize_text_field', (array) $raw['lift_capacity_keys'] ) : array();
        $vals     = isset( $raw['lift_capacity'] ) ? (array) $raw['lift_capacity'] : array();

        foreach ( $keys as $k ) {
            $k = strtoupper( sanitize_text_field( $k ) );
            if ( empty( $k ) ) continue;
            $new_cat1[ $k ] = array(
                'day'   => floatval( isset( $vals[ $k ]['day']   ) ? $vals[ $k ]['day']   : 0 ),
                'week'  => floatval( isset( $vals[ $k ]['week']  ) ? $vals[ $k ]['week']  : 0 ),
                'month' => floatval( isset( $vals[ $k ]['month'] ) ? $vals[ $k ]['month'] : 0 ),
            );
        }
        // Catch any keys from vals not in keys array
        foreach ( $vals as $k => $v ) {
            $k = strtoupper( sanitize_text_field( $k ) );
            if ( empty( $k ) || isset( $new_cat1[ $k ] ) ) continue;
            $new_cat1[ $k ] = array(
                'day'   => floatval( isset( $v['day']   ) ? $v['day']   : 0 ),
                'week'  => floatval( isset( $v['week']  ) ? $v['week']  : 0 ),
                'month' => floatval( isset( $v['month'] ) ? $v['month'] : 0 ),
            );
        }
        $existing['lift_capacity'] = $new_cat1;

        // --- Electric equipment ---
        $new_elec = array();
        $elec_raw = isset( $raw['electric_equipment'] ) ? (array) $raw['electric_equipment'] : array();
        foreach ( $elec_raw as $k => $v ) {
            $k = sanitize_text_field( $k );
            if ( empty( $k ) ) continue;
            $new_elec[ $k ] = array(
                'label'    => sanitize_text_field( isset( $v['label']    ) ? $v['label']    : '' ),
                'group'    => sanitize_text_field( isset( $v['group']    ) ? $v['group']    : '' ),
                'capacity' => sanitize_text_field( isset( $v['capacity'] ) ? $v['capacity'] : '' ),
                'day'      => floatval( isset( $v['day']   ) ? $v['day']   : 0 ),
                'week'     => floatval( isset( $v['week']  ) ? $v['week']  : 0 ),
                'month'    => floatval( isset( $v['month'] ) ? $v['month'] : 0 ),
            );
        }
        $existing['electric_equipment'] = $new_elec;

        // --- Fuel charges ---
        $fuel_raw = isset( $raw['fuel_charges'] ) ? (array) $raw['fuel_charges'] : array();
        foreach ( $fuel_raw as $fuel => $levels ) {
            $fuel = sanitize_text_field( $fuel );
            if ( empty( $fuel ) || ! is_array( $levels ) ) continue;
            foreach ( array( 'quarter', 'half', 'three_quarter', 'full' ) as $lvl ) {
                $existing['fuel_charges'][ $fuel ][ $lvl ] = floatval( isset( $levels[ $lvl ] ) ? $levels[ $lvl ] : 0 );
            }
        }

        // --- Add-ons --- (supports existing + new dynamically added rows)
        $addons_raw = isset( $raw['addons'] ) ? (array) $raw['addons'] : array();
        $new_addons = array();

        foreach ( $addons_raw as $form_key => $ad ) {
            // New rows send a [key] sub-field with the user-typed key
            // Existing rows use the form field key directly
            if ( isset( $ad['key'] ) && ! empty( trim( $ad['key'] ) ) ) {
                $real_key = sanitize_key( trim( $ad['key'] ) );
            } else {
                $real_key = sanitize_key( $form_key );
            }

            // Skip blank or placeholder keys
            if ( empty( $real_key ) ) continue;
            if ( $real_key === 'newkey' || strpos( strtolower( $real_key ), 'newkey' ) !== false ) continue;

            $new_addons[ $real_key ] = array(
                'label'   => sanitize_text_field( isset( $ad['label'] )   ? $ad['label']   : '' ),
                'price'   => floatval(             isset( $ad['price'] )   ? $ad['price']   : 0  ),
                'enabled' => ! empty( $ad['enabled'] ),
            );
        }

        $existing['addons'] = $new_addons;

        // --- Deposit ---
        if ( isset( $raw['deposit_amount'] ) ) {
            $existing['deposit_amount'] = floatval( $raw['deposit_amount'] );
        }

        // --- Equipment images ---
        // Images are saved immediately via ajax_upload_equipment_img when uploaded.
        // We only update image entries from the form's hidden inputs to handle
        // cases where the user clears an image (empty value).
        $stored_imgs = get_option( 'frs_equipment_images', array() );
        $img_groups  = array(
            'lift_capacity_images'        => 'cat1_',
            'electric_equipment_images'   => 'electric_',
            'addon_images'                => 'addon_',
        );
        foreach ( $img_groups as $img_group => $prefix ) {
            if ( isset( $raw[ $img_group ] ) && is_array( $raw[ $img_group ] ) ) {
                foreach ( $raw[ $img_group ] as $k => $url ) {
                    $img_key = $prefix . strtoupper( sanitize_text_field( $k ) );
                    $url     = esc_url_raw( trim( $url ) );
                    if ( $url ) {
                        $stored_imgs[ $img_key ] = $url;
                    } else {
                        // Empty = image was removed
                        unset( $stored_imgs[ $img_key ] );
                        // Also try lowercase key
                        $img_key_lower = $prefix . strtolower( sanitize_text_field( $k ) );
                        unset( $stored_imgs[ $img_key_lower ] );
                    }
                }
            }
        }
        update_option( 'frs_equipment_images', $stored_imgs );
        update_option( 'frs_pricing', $existing );

        wp_send_json_success( 'Pricing saved.' );
        wp_die();
    }

    /* =====================================================================
       AJAX: Upload Equipment Image (via WP Media Library)
    ===================================================================== */
    public function ajax_upload_equipment_img() {
        check_ajax_referer( 'frs_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
            wp_die();
        }
        $url      = esc_url_raw( trim( isset( $_POST['url'] )      ? $_POST['url']      : '' ) );
        $category = sanitize_text_field(  isset( $_POST['category'] ) ? $_POST['category'] : '' );
        $key      = sanitize_text_field(  isset( $_POST['key'] )      ? $_POST['key']      : '' );

        if ( ! $url ) {
            wp_send_json_error( 'No URL provided.' );
            wp_die();
        }

        if ( $category === 'lift_capacity' )        $prefix = 'cat1_';
        elseif ( $category === 'electric_equipment' ) $prefix = 'electric_';
        else                                          $prefix = 'addon_';

        // Store with the key exactly as sent (preserves case like "3K", "Sit_3k4k")
        $imgs = get_option( 'frs_equipment_images', array() );
        $imgs[ $prefix . $key ] = $url;
        update_option( 'frs_equipment_images', $imgs );

        wp_send_json_success( array( 'url' => $url ) );
        wp_die();
    }

    /* =====================================================================
       AJAX: Remove Equipment Image
    ===================================================================== */
    public function ajax_remove_equipment_img() {
        check_ajax_referer('frs_admin_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
        $category = sanitize_key((isset($_POST['category']) ? $_POST['category'] : ''));
        $key      = sanitize_key((isset($_POST['key']) ? $_POST['key'] : ''));
        $prefix   = $category === 'lift_capacity' ? 'cat1_' : ($category === 'electric_equipment' ? 'electric_' : 'addon_');
        $imgs = get_option('frs_equipment_images', array());
        unset($imgs[$prefix . $key]);
        update_option('frs_equipment_images', $imgs);
        wp_send_json_success('removed');
        wp_die();
    }

    /* =====================================================================
       AJAX: Delete Single Order
    ===================================================================== */
    public function ajax_delete_order() {
        check_ajax_referer('frs_admin_nonce','nonce');
        if ( ! current_user_can('manage_options') ) { wp_send_json_error('Unauthorized.'); wp_die(); }
        $order_number = sanitize_text_field(wp_unslash((isset($_POST['order_number']) ? $_POST['order_number'] : '')));
        if ( ! $order_number ) { wp_send_json_error('Missing order number.'); wp_die(); }
        global $wpdb;
        $order = FRS_Order::get_by_order_number($order_number);
        if ($order) {
            if ( !empty($order->pdf_path) && file_exists($order->pdf_path) ) @unlink($order->pdf_path);
            $result = $wpdb->delete($wpdb->prefix.'frs_orders', array('order_number'=>$order_number), array('%s'));
            if ($result !== false) { wp_send_json_success(array('deleted'=>$order_number)); }
            else { wp_send_json_error('Database error.'); }
        } else { wp_send_json_error('Order not found.'); }
        wp_die();
    }

    /* =====================================================================
       AJAX: Bulk Delete Orders
    ===================================================================== */
    public function ajax_bulk_delete() {
        check_ajax_referer('frs_admin_nonce','nonce');
        if ( ! current_user_can('manage_options') ) { wp_send_json_error('Unauthorized.'); wp_die(); }
        $order_numbers = (isset($_POST['order_numbers']) && is_array($_POST['order_numbers']))
            ? array_map('sanitize_text_field', wp_unslash($_POST['order_numbers'])) : array();
        if (empty($order_numbers)) { wp_send_json_error('No orders selected.'); wp_die(); }
        global $wpdb; $deleted = 0;
        foreach ($order_numbers as $on) {
            $order = FRS_Order::get_by_order_number($on);
            if ($order) {
                if (!empty($order->pdf_path) && file_exists($order->pdf_path)) @unlink($order->pdf_path);
                if ($wpdb->delete($wpdb->prefix.'frs_orders', array('order_number'=>$on), array('%s')) !== false) $deleted++;
            }
        }
        wp_send_json_success(array('deleted'=>$deleted));
        wp_die();
    }

    /* =====================================================================
       SETTINGS & AGREEMENT PAGES
    ===================================================================== */
    public function page_settings() { ?>
        <div class="wrap frs-wrap">
            <h1>Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('frs_settings_group'); ?>
                <table class="form-table">
                    <tr><th>Company Name</th><td><input type="text" name="frs_company_name" value="<?php echo esc_attr(get_option('frs_company_name')); ?>" class="regular-text"></td></tr>
                    <tr><th>Admin Email</th><td><input type="email" name="frs_admin_email" value="<?php echo esc_attr(get_option('frs_admin_email')); ?>" class="regular-text"></td></tr>
                    <tr><th colspan="2"><h2 style="margin:0">Stripe Payments</h2></th></tr>
                    <tr><th>Mode</th><td><select name="frs_stripe_mode"><option value="test" <?php selected(get_option('frs_stripe_mode'),'test'); ?>>Test</option><option value="live" <?php selected(get_option('frs_stripe_mode'),'live'); ?>>Live</option></select></td></tr>
                    <tr><th>Test Secret Key</th><td><input type="text" name="frs_stripe_test_key" value="<?php echo esc_attr(get_option('frs_stripe_test_key')); ?>" class="regular-text" placeholder="sk_test_..."></td></tr>
                    <tr><th>Test Publishable Key</th><td><input type="text" name="frs_stripe_pub_test_key" value="<?php echo esc_attr(get_option('frs_stripe_pub_test_key')); ?>" class="regular-text" placeholder="pk_test_..."></td></tr>
                    <tr><th>Live Secret Key</th><td><input type="text" name="frs_stripe_live_key" value="<?php echo esc_attr(get_option('frs_stripe_live_key')); ?>" class="regular-text" placeholder="sk_live_..."></td></tr>
                    <tr><th>Live Publishable Key</th><td><input type="text" name="frs_stripe_pub_live_key" value="<?php echo esc_attr(get_option('frs_stripe_pub_live_key')); ?>" class="regular-text" placeholder="pk_live_..."></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div><?php
    }

    public function page_agreement() {
        $agreement   = get_option( 'frs_agreement_text', '' );
        $company     = get_option( 'frs_company_name', get_bloginfo('name') );
        $placeholders = array(
            '{company_name}'      => $company,
            '{customer_name}'     => 'John Smith',
            '{customer_company}'  => 'ACME Corp',
            '{rental_date}'       => date('m-d-Y'),
            '{equipment_details}' => '5K Propane — Day Rental',
            '{duration_type}'     => 'Day',
            '{total_price}'       => '$175.00',
            '{deposit_amount}'    => '$50.00',
            '{payment_type}'      => 'Deposit Only',
            '{order_number}'      => 'FRK-ABC123-' . date('Ymd'),
        );
        $preview_text = str_replace( array_keys($placeholders), array_values($placeholders), $agreement );
        ?>
        <div class="wrap frs-wrap">

            <!-- Page header -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                <div>
                    <h1 style="margin:0 0 3px">Rental Agreement Text</h1>
                    <p style="margin:0;color:#666;font-size:13px">Edit the agreement template and preview it with sample data in real time.</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span id="frs-agree-saved-msg" style="font-size:13px;font-weight:600;color:#065f46;display:none">&#10003; Saved!</span>
                    <button type="button" class="button button-primary" id="frs-save-agreement-btn" style="padding:8px 20px;font-size:13px;font-weight:600;height:auto">Save Agreement</button>
                </div>
            </div>

            <!-- Placeholders reference -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 16px;margin-bottom:16px;font-size:12px;">
                <strong style="color:#1d4ed8;display:block;margin-bottom:6px">&#128279; Available Placeholders</strong>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ( array_keys($placeholders) as $ph ): ?>
                    <code style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:11px;cursor:pointer" class="frs-ph-insert" data-ph="<?php echo esc_attr($ph); ?>"><?php echo esc_html($ph); ?></code>
                    <?php endforeach; ?>
                </div>
                <p style="margin:8px 0 0;color:#3b82f6;font-size:11px">&#128073; Click a placeholder to insert it at the cursor position in the editor.</p>
            </div>

            <!-- Split pane: Editor + Preview -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">

                <!-- Editor side -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)">
                    <div style="background:#f8f9fa;border-bottom:1px solid #eee;padding:11px 16px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:14px">&#9998;</span>
                        <strong style="font-size:13px;color:#1d2327">Agreement Editor</strong>
                        <span style="margin-left:auto;font-size:11px;color:#999" id="frs-char-count"></span>
                    </div>
                    <div style="padding:0">
                        <textarea id="frs-agreement-editor"
                            style="width:100%;min-height:560px;padding:16px;font-family:'Courier New',monospace;font-size:13px;line-height:1.7;border:none;resize:vertical;outline:none;color:#1d2327;background:#fff;box-sizing:border-box"
                            placeholder="Type your rental agreement here..."><?php echo esc_textarea($agreement); ?></textarea>
                    </div>
                </div>

                <!-- Preview side -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05);position:sticky;top:32px">
                    <div style="background:#f8f9fa;border-bottom:1px solid #eee;padding:11px 16px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:14px">&#128065;</span>
                        <strong style="font-size:13px;color:#1d2327">Live Preview</strong>
                        <span style="margin-left:auto;font-size:11px;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-weight:600">Sample Data</span>
                    </div>

                    <!-- PDF-style preview frame -->
                    <div style="padding:20px;background:#f4f4f4;min-height:100px">
                        <div style="background:#fff;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,.1);padding:32px 36px;min-height:520px">

                            <!-- Agreement letterhead -->
                            <div style="border-bottom:2px solid #1a3c5e;padding-bottom:14px;margin-bottom:20px;display:flex;align-items:flex-start;justify-content:space-between">
                                <div>
                                    <div style="font-size:18px;font-weight:700;color:#1a3c5e"><?php echo esc_html($company); ?></div>
                                    <div style="font-size:12px;color:#888;margin-top:2px">Forklift Rental Agreement</div>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-size:12px;font-weight:700;color:#f59e0b;background:#1a3c5e;padding:4px 12px;border-radius:12px">Order # FRK-ABC123-<?php echo date('Ymd'); ?></div>
                                    <div style="font-size:11px;color:#999;margin-top:4px"><?php echo date('m-d-Y'); ?></div>
                                </div>
                            </div>

                            <!-- Customer summary strip -->
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;background:#f8fafc;border-radius:6px;padding:12px 16px;margin-bottom:20px;font-size:12px">
                                <div><div style="color:#888;margin-bottom:2px">Customer</div><strong>John Smith</strong><div style="color:#666">ACME Corp</div></div>
                                <div><div style="color:#888;margin-bottom:2px">Equipment</div><strong>5K Propane</strong><div style="color:#666">Day Rental</div></div>
                                <div><div style="color:#888;margin-bottom:2px">Total</div><strong style="color:#1a3c5e;font-size:15px">$175.00</strong><div style="color:#f59e0b">Deposit: $50.00</div></div>
                            </div>

                            <!-- Agreement text preview -->
                            <div id="frs-agreement-preview-text"
                                style="font-size:12.5px;line-height:1.8;color:#333;white-space:pre-wrap;font-family:Georgia,serif;min-height:200px"><?php echo esc_html($preview_text); ?></div>

                            <!-- Signature area -->
                            <div style="border-top:1px dashed #ccc;margin-top:24px;padding-top:16px;display:flex;align-items:flex-end;gap:40px">
                                <div style="flex:1">
                                    <div style="height:36px;border-bottom:1px solid #333;margin-bottom:4px;background:repeating-linear-gradient(90deg,#f5f5f5 0,#f5f5f5 3px,transparent 3px,transparent 10px);border-radius:2px"></div>
                                    <div style="font-size:11px;color:#888">Customer Signature</div>
                                </div>
                                <div style="flex:1">
                                    <div style="height:36px;border-bottom:1px solid #333;margin-bottom:4px"></div>
                                    <div style="font-size:11px;color:#888">Date</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- end grid -->

        </div>

        <script>
        jQuery(function($){
            var $editor  = $('#frs-agreement-editor');
            var $preview = $('#frs-agreement-preview-text');
            var $count   = $('#frs-char-count');

            // Placeholder sample values for preview
            var samples = <?php echo json_encode($placeholders); ?>;

            function updatePreview() {
                var text = $editor.val();
                // Replace placeholders with sample values
                $.each(samples, function(ph, val){
                    text = text.split(ph).join(val);
                });
                $preview.text(text);
                $count.text($editor.val().length + ' chars');
            }

            // Live update as you type
            $editor.on('input keyup', updatePreview);
            updatePreview();

            // Click placeholder to insert at cursor
            $(document).on('click', '.frs-ph-insert', function(){
                var ph  = $(this).data('ph');
                var el  = $editor[0];
                var start = el.selectionStart;
                var end   = el.selectionEnd;
                var val   = el.value;
                el.value  = val.substring(0, start) + ph + val.substring(end);
                el.selectionStart = el.selectionEnd = start + ph.length;
                $editor.trigger('input');
                el.focus();
            });

            // Save via AJAX
            $('#frs-save-agreement-btn').on('click', function(){
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var $msg = $('#frs-agree-saved-msg');

                $.post(ajaxurl, {
                    action:   'frs_save_agreement',
                    nonce:    '<?php echo wp_create_nonce("frs_admin_nonce"); ?>',
                    agreement: $editor.val()
                }, function(r){
                    $btn.prop('disabled', false).text('Save Agreement');
                    if(r && r.success){
                        $msg.show().delay(3000).fadeOut(400);
                    } else {
                        alert('Save failed: ' + (r && r.data ? r.data : 'Unknown error'));
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text('Save Agreement');
                    alert('Request failed. Please try again.');
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_save_agreement() {
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'frs_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
            wp_die();
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
            wp_die();
        }
        $text = isset( $_POST['agreement'] ) ? wp_kses_post( wp_unslash( $_POST['agreement'] ) ) : '';
        update_option( 'frs_agreement_text', $text );
        wp_send_json_success( 'Agreement saved.' );
        wp_die();
    }
}
