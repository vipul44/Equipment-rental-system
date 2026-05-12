<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$pricing   = FRS_Settings::get_pricing();
$all_equip = FRS_Settings::get_all_equipment();
$eq_imgs   = FRS_Settings::get_equipment_images();
$addons    = FRS_Settings::get_addons();
$agreement = FRS_Settings::get_agreement_text();
$deposit   = FRS_Settings::get_deposit();

// Fuel charges from backend
$fuel_charges = isset($pricing['fuel_charges']) ? $pricing['fuel_charges'] : array();

// Build equipment data for JS
$equip_js = array();
foreach ( $all_equip as $eq ) {
    $equip_js[ $eq['key'] ] = array(
        'label'    => $eq['label'],
        'category' => $eq['category'],
        'image'    => isset($eq['image']) ? $eq['image'] : '',
        'capacity' => isset($eq['capacity']) ? $eq['capacity'] : '',
        'rates'    => $eq['rates'],
    );
}
?>
<div id="frs-form-wrap" class="frs-form-wrap">

    <!-- Header -->
    <div class="frs-header">
        <div class="frs-header-title">
            <div class="frs-header-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 17h2M19 17h2M5 17V9l4-4h6l4 4v8M9 17v-4h6v4" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="7" cy="19" r="2" stroke="#f59e0b" stroke-width="1.8"/>
                    <circle cx="17" cy="19" r="2" stroke="#f59e0b" stroke-width="1.8"/>
                </svg>
            </div>
            <div>
                <h1>Forklift Rental Reservation</h1>
                <p>Complete the form below to reserve your equipment</p>
            </div>
        </div>
        <div class="frs-header-badge">Online Booking</div>
    </div>

    <!-- Step Indicator -->
    <div class="frs-steps">
        <div class="frs-step active" data-step="1"><div class="frs-step-num">1</div> Equipment</div>
        <div class="frs-step" data-step="2"><div class="frs-step-num">2</div> Your Info</div>
        <div class="frs-step" data-step="3"><div class="frs-step-num">3</div> Payment</div>
        <div class="frs-step" data-step="4"><div class="frs-step-num">4</div> Agreement</div>
    </div>

    <!-- STEP 1: Equipment -->
    <div class="frs-step-content active" id="frs-step-1">
        <h2>Select Your Equipment</h2>
        <p class="frs-step-subtitle">Choose your equipment, rental duration, quantity, and fuel level below.</p>

        <!-- Equipment Selection Grid -->
        <div class="frs-section-card">
            <div class="frs-section-label">Select Equipment</div>
            <div class="frs-equip-grid" id="frs-equip-grid">
                <?php foreach ( $all_equip as $eq ) :
                    $img_url = isset($eq['image']) ? $eq['image'] : '';
                ?>
                <div class="frs-equip-card" data-key="<?php echo esc_attr($eq['key']); ?>" data-label="<?php echo esc_attr($eq['label']); ?>">
                    <?php if ($img_url): ?>
                    <div class="frs-equip-img"><img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($eq['label']); ?>"></div>
                    <?php else: ?>
                    <div class="frs-equip-img frs-equip-img-placeholder">
                        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" width="40" height="40">
                            <path d="M6 34h4M38 34h4M10 34V18l8-8h12l8 8v16M18 34v-8h12v8" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="14" cy="38" r="4" stroke="#94a3b8" stroke-width="2.5"/>
                            <circle cx="34" cy="38" r="4" stroke="#94a3b8" stroke-width="2.5"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                    <div class="frs-equip-info">
                        <div class="frs-equip-name"><?php echo esc_html($eq['label']); ?></div>
                        <?php if (!empty($eq['capacity'])): ?>
                        <div class="frs-equip-cap"><?php echo esc_html($eq['capacity']); ?></div>
                        <?php endif; ?>
                        <div class="frs-equip-cat frs-equip-cat-<?php echo esc_attr($eq['category']); ?>">
                            <?php echo $eq['category'] === 'electric' ? '&#9889; Electric' : '&#9981; Propane/Gas/Diesel'; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="frs-lift-capacity" name="lift_capacity">

            <!-- Equipment preview bar after selection -->
            <div class="frs-equip-preview" id="frs-equip-preview" style="display:none">
                <img id="frs-equip-preview-img" src="" alt="">
                <div class="frs-equip-preview-info">
                    <strong id="frs-equip-preview-name"></strong>
                    <span id="frs-equip-preview-cap"></span>
                </div>
                <button type="button" class="frs-btn frs-btn-sm" id="frs-change-equip">Change Equipment</button>
            </div>
        </div>

        <!-- ============================================================
             PRICING RATE TABLE — shown after equipment selected
             Shows Day / Week / Month rates so client knows cost upfront
             ============================================================ -->
        <div class="frs-section-card" id="frs-rate-card" style="display:none">
            <div class="frs-section-label">Rental Rates</div>
            <table class="frs-rate-table">
                <thead>
                    <tr>
                        <th>Duration</th>
                        <th>Rate</th>
                        <th>How many?</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Per Day</strong></td>
                        <td id="frs-rate-day">—</td>
                        <td>
                            <div class="frs-qty-wrap">
                                <button type="button" class="frs-qty-btn" data-dur="day" data-action="minus">&#8722;</button>
                                <input type="number" id="frs-qty-day" class="frs-qty-input" data-dur="day" value="0" min="0" max="365">
                                <button type="button" class="frs-qty-btn" data-dur="day" data-action="plus">&#43;</button>
                            </div>
                        </td>
                        <td id="frs-sub-day" class="frs-subtotal">$0.00</td>
                    </tr>
                    <tr>
                        <td><strong>Per Week</strong></td>
                        <td id="frs-rate-week">—</td>
                        <td>
                            <div class="frs-qty-wrap">
                                <button type="button" class="frs-qty-btn" data-dur="week" data-action="minus">&#8722;</button>
                                <input type="number" id="frs-qty-week" class="frs-qty-input" data-dur="week" value="0" min="0" max="52">
                                <button type="button" class="frs-qty-btn" data-dur="week" data-action="plus">&#43;</button>
                            </div>
                        </td>
                        <td id="frs-sub-week" class="frs-subtotal">$0.00</td>
                    </tr>
                    <tr>
                        <td><strong>Per Month</strong></td>
                        <td id="frs-rate-month">—</td>
                        <td>
                            <div class="frs-qty-wrap">
                                <button type="button" class="frs-qty-btn" data-dur="month" data-action="minus">&#8722;</button>
                                <input type="number" id="frs-qty-month" class="frs-qty-input" data-dur="month" value="0" min="0" max="24">
                                <button type="button" class="frs-qty-btn" data-dur="month" data-action="plus">&#43;</button>
                            </div>
                        </td>
                        <td id="frs-sub-month" class="frs-subtotal">$0.00</td>
                    </tr>
                </tbody>
            </table>
            <p class="frs-rate-note">Enter the number of days, weeks, and/or months you need. Quantities can be combined.</p>
            <!-- Hidden fields for duration/qty submitted with order -->
            <input type="hidden" id="frs-duration-type" name="duration_type" value="day">
            <input type="hidden" id="frs-duration-qty" name="duration_qty" value="1">
        </div>

        <!-- ============================================================
             FUEL LEVEL — for Propane/Gas/Diesel equipment only
             Fuel charges from backend pricing
             ============================================================ -->
        <div class="frs-section-card" id="frs-fuel-level-card" style="display:none">
            <div class="frs-section-label">Fuel Level Required</div>
            <p class="frs-rate-note" style="margin-bottom:12px">Select the fuel level you need. The charge will be added to your rental total.</p>
            <div class="frs-fuel-level-grid">
                <?php
                // Show fuel charges for the three fuel types
                $fuel_labels = array('Propane','Gas','Diesel');
                $level_labels = array('quarter'=>'1/4 Tank','half'=>'1/2 Tank','three_quarter'=>'3/4 Tank','full'=>'Full Tank');
                foreach ( $fuel_labels as $ftype ):
                    if ( !isset($fuel_charges[$ftype]) ) continue;
                    $fc = $fuel_charges[$ftype];
                ?>
                <div class="frs-fuel-type-group" data-fueltype="<?php echo esc_attr($ftype); ?>">
                    <div class="frs-fuel-type-label"><?php echo esc_html($ftype); ?></div>
                    <div class="frs-fuel-levels">
                        <?php foreach ( $level_labels as $level_key => $level_name ):
                            $price = isset($fc[$level_key]) ? floatval($fc[$level_key]) : 0;
                        ?>
                        <label class="frs-fuel-level-option">
                            <input type="radio" name="fuel_level_<?php echo esc_attr($ftype); ?>" class="frs-fuel-level-radio"
                                data-fueltype="<?php echo esc_attr($ftype); ?>"
                                data-level="<?php echo esc_attr($level_key); ?>"
                                data-price="<?php echo esc_attr($price); ?>"
                                value="<?php echo esc_attr($level_key); ?>">
                            <div class="frs-fuel-level-card">
                                <span class="frs-fuel-level-name"><?php echo esc_html($level_name); ?></span>
                                <span class="frs-fuel-level-price">+$<?php echo number_format($price, 2); ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        <label class="frs-fuel-level-option">
                            <input type="radio" name="fuel_level_<?php echo esc_attr($ftype); ?>" class="frs-fuel-level-radio"
                                data-fueltype="<?php echo esc_attr($ftype); ?>"
                                data-level="none"
                                data-price="0"
                                value="none" checked>
                            <div class="frs-fuel-level-card">
                                <span class="frs-fuel-level-name">No Fuel</span>
                                <span class="frs-fuel-level-price">$0.00</span>
                            </div>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="frs-fuel-level" name="fuel_level" value="">
            <input type="hidden" id="frs-fuel-level-type" name="fuel_level_type" value="">
            <input type="hidden" id="frs-fuel-charge" name="fuel_charge" value="0">
        </div>

        <!-- Add-ons -->
        <?php if (!empty($addons)): ?>
        <div class="frs-section-card" id="frs-addons-card" style="display:none">
            <div class="frs-section-label">Optional Add-ons</div>
            <div class="frs-addon-grid">
                <?php foreach ($addons as $key => $addon): ?>
                <?php if (!empty($addon['enabled'])): ?>
                <?php $addon_img = FRS_Settings::find_image_public( $eq_imgs, array( 'addon_'.$key, 'addon_'.strtolower($key) ) ); ?>
                <label class="frs-addon-item">
                    <input type="checkbox" class="frs-addon-check" name="addons[]" value="<?php echo esc_attr($key); ?>">
                    <?php if ($addon_img): ?>
                    <img src="<?php echo esc_url($addon_img); ?>" class="frs-addon-img" alt="<?php echo esc_attr($addon['label']); ?>">
                    <?php endif; ?>
                    <span class="frs-addon-label"><?php echo esc_html($addon['label']); ?></span>
                    <span class="frs-addon-price">+$<?php echo number_format($addon['price'],2); ?></span>
                </label>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Price Summary — rows injected dynamically by JS -->
        <div class="frs-price-summary" id="frs-price-summary" style="display:none">
            <div class="frs-price-row"><span>Equipment Rental</span><span id="frs-base-price">$0.00</span></div>
            <!-- Fuel rows inserted here by JS -->
            <!-- Add-on rows inserted here by JS -->
            <div class="frs-price-row frs-total-row"><span>Total</span><span id="frs-total-price">$0.00</span></div>
            <div class="frs-price-row frs-deposit-row"><span>Deposit option available: $<?php echo number_format($deposit,2); ?></span></div>
        </div>

        <div class="frs-step-nav">
            <span></span>
            <button class="frs-btn frs-btn-primary" id="frs-to-step-2">Continue &rarr;</button>
        </div>
        <div class="frs-error" id="frs-error-1"></div>
    </div>

    <!-- STEP 2: Customer Info -->
    <div class="frs-step-content" id="frs-step-2">
        <h2>Your Information</h2>
        <p class="frs-step-subtitle">We need a few details to complete your reservation.</p>
        <div class="frs-section-card">
            <div class="frs-section-label">Contact Details</div>
            <div class="frs-form-grid">
                <div class="frs-field-group"><label>Full Name <span class="req">*</span></label><input type="text" id="frs-customer-name" placeholder="John Smith" required></div>
                <div class="frs-field-group"><label>Company Name</label><input type="text" id="frs-customer-company" placeholder="ACME Corp"></div>
                <div class="frs-field-group"><label>Phone Number <span class="req">*</span></label><input type="tel" id="frs-customer-phone" placeholder="(555) 000-0000" required></div>
                <div class="frs-field-group"><label>Email Address <span class="req">*</span></label><input type="email" id="frs-customer-email" placeholder="john@example.com" required></div>
                <div class="frs-field-group frs-full-width"><label>Job Site Address</label><input type="text" id="frs-jobsite-address" placeholder="123 Main St, City, State"></div>
                <div class="frs-field-group frs-full-width"><label>Rental Notes</label><textarea id="frs-rental-notes" rows="3" placeholder="Any special requirements or notes..."></textarea></div>
            </div>
        </div>
        <div class="frs-step-nav">
            <button class="frs-btn frs-btn-secondary" id="frs-back-1">&larr; Back</button>
            <button class="frs-btn frs-btn-primary" id="frs-to-step-3">Continue &rarr;</button>
        </div>
        <div class="frs-error" id="frs-error-2"></div>
    </div>

    <!-- STEP 3: Payment -->
    <div class="frs-step-content" id="frs-step-3">
        <h2>Payment Method</h2>
        <p class="frs-step-subtitle">Review your order and choose how you'd like to pay.</p>
        <div class="frs-order-summary">
            <h3>Order Summary</h3>
            <div id="frs-summary-equipment"></div>
            <div class="frs-summary-total-row"><span>Total</span><span id="frs-summary-total"></span></div>
        </div>
        <div class="frs-section-card">
            <div class="frs-section-label">Payment Option</div>
            <div class="frs-payment-options">
                <label class="frs-payment-option"><input type="radio" name="payment_type" value="deposit" checked>
                    <div class="frs-payment-card"><strong>Deposit Only</strong><span>Pay $<?php echo number_format($deposit,2); ?> now — balance collected on delivery</span></div>
                    <span class="frs-payment-badge frs-badge-popular">Most Popular</span>
                </label>
                <label class="frs-payment-option"><input type="radio" name="payment_type" value="full_payment">
                    <div class="frs-payment-card"><strong>Full Payment</strong><span id="frs-full-payment-label">Pay the full rental amount now</span></div>
                </label>
                <label class="frs-payment-option"><input type="radio" name="payment_type" value="save_card">
                    <div class="frs-payment-card"><strong>Save Card for Later</strong><span>Card saved securely — billed on delivery</span></div>
                    <span class="frs-payment-badge frs-badge-save">No charge now</span>
                </label>
            </div>
        </div>
        <div id="frs-stripe-element-wrap" class="frs-section-card">
            <div class="frs-section-label">Card Information</div>
            <div id="frs-stripe-element" class="frs-stripe-input"></div>
            <div id="frs-stripe-error" class="frs-error"></div>
        </div>
        <div class="frs-step-nav">
            <button class="frs-btn frs-btn-secondary" id="frs-back-2">&larr; Back</button>
            <button class="frs-btn frs-btn-primary" id="frs-to-step-4">Continue &rarr;</button>
        </div>
        <div class="frs-error" id="frs-error-3"></div>
    </div>

    <!-- STEP 4: Agreement + Signature -->
    <div class="frs-step-content" id="frs-step-4">
        <h2>Rental Agreement</h2>
        <p class="frs-step-subtitle">Please read the agreement carefully, then sign below to confirm.</p>
        <div class="frs-agreement-box" id="frs-agreement-text"><?php echo nl2br(esc_html($agreement)); ?></div>
        <div class="frs-section-card">
            <div class="frs-section-label">Your Signature</div>
            <div class="frs-sig-wrap">
                <div class="frs-sig-header">Sign below using your mouse or finger</div>
                <canvas id="frs-signature-pad"></canvas>
                <div class="frs-sig-actions"><button type="button" class="frs-btn frs-btn-sm" id="frs-clear-sig">Clear</button></div>
            </div>
        </div>
        <div class="frs-section-card" style="margin-top:0">
            <label class="frs-checkbox-label"><input type="checkbox" id="frs-agree-check"> I have read and agree to all terms and conditions in the rental agreement above</label>
        </div>
        <div class="frs-step-nav">
            <button class="frs-btn frs-btn-secondary" id="frs-back-3">&larr; Back</button>
            <button class="frs-btn frs-btn-submit" id="frs-submit-order">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Submit Reservation
            </button>
        </div>
        <div class="frs-error" id="frs-error-4"></div>
        <div class="frs-loading" id="frs-loading" style="display:none">
            <div class="frs-spinner"></div>
            <p>Processing your reservation&hellip;</p>
        </div>
    </div>

    <!-- STEP 5: Confirmation -->
    <div class="frs-step-content" id="frs-step-5">
        <div class="frs-confirmation">
            <div class="frs-confirm-icon">&#10003;</div>
            <h2>Reservation Confirmed!</h2>
            <p>Thank you for your reservation. A confirmation email with your signed agreement PDF has been sent to your email address.</p>
            <div class="frs-confirm-order">
                <strong>Your Order Number</strong>
                <div class="frs-order-number" id="frs-confirm-order-number"></div>
            </div>
            <p class="frs-confirm-note">Please keep this order number for your records. Our team will be in touch to confirm delivery details.</p>
            <div style="margin-top:20px">
                <a href="#" id="frs-pdf-download-link" class="frs-pdf-download" target="_blank">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a1 1 0 001 1h16a1 1 0 001-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Download Signed Agreement
                </a>
            </div>
        </div>
    </div>

</div>

<script>
var frs_equipment_data = <?php echo json_encode($equip_js); ?>;
</script>
