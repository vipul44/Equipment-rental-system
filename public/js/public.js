jQuery(function($){

    var signaturePad     = null;
    var stripe           = null;
    var stripeElements   = null;
    var stripeCardElement = null;
    var paymentType      = 'deposit';
    var selectedEquipKey = '';
    var currentRates     = { day:0, week:0, month:0 };
    var currentFuelCharge = 0;
    var isElectric       = false;

    /* ============================================================
       INIT
       ============================================================ */
    function init() {
        if (typeof Stripe !== 'undefined' && frs_vars.stripe_pub_key) {
            stripe = Stripe(frs_vars.stripe_pub_key);
        }
        initSignaturePad();
        bindEvents();
    }

    /* ============================================================
       SIGNATURE PAD
       ============================================================ */
    function initSignaturePad() {
        var canvas = document.getElementById('frs-signature-pad');
        if (!canvas) return;
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255,255,255,0)',
            penColor: 'rgb(0,0,0)'
        });
        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width  = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        }
        window.addEventListener('resize', resizeCanvas);
        setTimeout(resizeCanvas, 100);
    }

    /* ============================================================
       BIND EVENTS
       ============================================================ */
    function bindEvents() {

        // Equipment card click
        $(document).on('click', '.frs-equip-card', function(){
            selectEquipment($(this).data('key'), $(this).data('label'));
        });

        // Change equipment
        $(document).on('click', '#frs-change-equip', function(){
            deselectEquipment();
        });

        // Qty +/- buttons on rate table
        $(document).on('click', '.frs-qty-btn', function(){
            var dur    = $(this).data('dur');
            var action = $(this).data('action');
            var $input = $('#frs-qty-' + dur);
            var val    = parseInt($input.val()) || 0;
            if (action === 'plus')  val = Math.min(val + 1, parseInt($input.attr('max')) || 999);
            if (action === 'minus') val = Math.max(val - 1, 0);
            $input.val(val);
            recalcTotal();
        });

        // Qty input typed directly
        $(document).on('input', '.frs-qty-input', function(){
            var val = parseInt($(this).val()) || 0;
            if (val < 0) $(this).val(0);
            recalcTotal();
        });

        // Fuel level radio selection — each fuel type is independent
        $(document).on('change', '.frs-fuel-level-radio', function(){
            var price    = parseFloat($(this).data('price')) || 0;
            var fueltype = $(this).data('fueltype');
            var level    = $(this).data('level');

            // Highlight selected card within this fuel type group only
            $('.frs-fuel-type-group[data-fueltype="' + fueltype + '"] .frs-fuel-level-card').removeClass('selected');
            $(this).next('.frs-fuel-level-card').addClass('selected');

            recalcTotal();
        });

        // Add-on checkboxes
        $(document).on('change', '.frs-addon-check', function(){
            $(this).closest('.frs-addon-item').toggleClass('selected', $(this).is(':checked'));
            recalcTotal();
        });

        // Navigation
        $('#frs-to-step-2').on('click',  function(){ goToStep(2); });
        $('#frs-to-step-3').on('click',  function(){ goToStep(3); });
        $('#frs-to-step-4').on('click',  function(){ goToStep(4); });
        $('#frs-back-1').on('click',     function(){ goToStep(1); });
        $('#frs-back-2').on('click',     function(){ goToStep(2); });
        $('#frs-back-3').on('click',     function(){ goToStep(3); });
        $('#frs-submit-order').on('click', submitOrder);
        $('#frs-clear-sig').on('click',  function(){ if (signaturePad) signaturePad.clear(); });

        $(document).on('change', 'input[name="payment_type"]', function(){
            paymentType = $(this).val();
        });
    }

    /* ============================================================
       EQUIPMENT SELECTION
       ============================================================ */
    function selectEquipment(key, label) {
        selectedEquipKey = key;
        $('.frs-equip-card').removeClass('selected');
        $('.frs-equip-card[data-key="' + key + '"]').addClass('selected');
        $('#frs-lift-capacity').val(key);

        var equip = (typeof frs_equipment_data !== 'undefined' && frs_equipment_data[key])
            ? frs_equipment_data[key] : null;

        isElectric = equip && equip.category === 'electric';

        // Show preview bar
        var $preview = $('#frs-equip-preview');
        if (equip && equip.image) {
            $preview.find('#frs-equip-preview-img').attr('src', equip.image).show();
        } else {
            $preview.find('#frs-equip-preview-img').hide();
        }
        $preview.find('#frs-equip-preview-name').text(equip ? equip.label : label);
        $preview.find('#frs-equip-preview-cap').text(equip && equip.capacity ? equip.capacity : '');
        $preview.slideDown(200);

        // Hide grid
        $('#frs-equip-grid').slideUp(200);

        // Populate rate table with this equipment's rates
        if (equip && equip.rates) {
            currentRates.day   = parseFloat(equip.rates.day)   || 0;
            currentRates.week  = parseFloat(equip.rates.week)  || 0;
            currentRates.month = parseFloat(equip.rates.month) || 0;
        } else {
            currentRates = { day:0, week:0, month:0 };
        }

        $('#frs-rate-day').text(currentRates.day   > 0 ? '$' + formatMoney(currentRates.day)   + ' / day'   : 'N/A');
        $('#frs-rate-week').text(currentRates.week  > 0 ? '$' + formatMoney(currentRates.week)  + ' / week'  : 'N/A');
        $('#frs-rate-month').text(currentRates.month > 0 ? '$' + formatMoney(currentRates.month) + ' / month' : 'N/A');

        // Reset qty inputs
        $('.frs-qty-input').val(0);
        $('.frs-subtotal').text('$0.00');
        currentFuelCharge = 0;

        // Show rate card
        $('#frs-rate-card').slideDown(300);

        // Show fuel level card only for propane/gas/diesel, hide for electric
        if (!isElectric) {
            $('#frs-fuel-level-card').slideDown(300);
        } else {
            $('#frs-fuel-level-card').slideUp(200);
            currentFuelCharge = 0;
            $('#frs-fuel-level').val('');
            $('#frs-fuel-charge').val('0');
        }

        // Show add-ons
        $('#frs-addons-card').slideDown(300);

        // Hide old price summary until qty entered
        $('#frs-price-summary').hide();

        // Reset fuel radio selections
        $('.frs-fuel-level-radio[data-level="none"]').prop('checked', true);
        $('.frs-fuel-level-card').removeClass('selected');

        $('#frs-error-1').text('');
    }

    function deselectEquipment() {
        selectedEquipKey = '';
        $('#frs-lift-capacity').val('');
        $('.frs-equip-card').removeClass('selected');
        $('#frs-equip-preview').slideUp(200);
        $('#frs-equip-grid').slideDown(200);
        $('#frs-rate-card').slideUp(200);
        $('#frs-fuel-level-card').slideUp(200);
        $('#frs-addons-card').slideUp(200);
        $('#frs-price-summary').hide();
        currentRates = { day:0, week:0, month:0 };
        currentFuelCharge = 0;
    }

    /* ============================================================
       RECALCULATE TOTAL
       Called whenever qty, fuel level, or add-ons change
       ============================================================ */
    function recalcTotal() {
        var qDay   = parseInt($('#frs-qty-day').val())   || 0;
        var qWeek  = parseInt($('#frs-qty-week').val())  || 0;
        var qMonth = parseInt($('#frs-qty-month').val()) || 0;

        var subDay   = qDay   * currentRates.day;
        var subWeek  = qWeek  * currentRates.week;
        var subMonth = qMonth * currentRates.month;

        $('#frs-sub-day').text('$' + formatMoney(subDay));
        $('#frs-sub-week').text('$' + formatMoney(subWeek));
        $('#frs-sub-month').text('$' + formatMoney(subMonth));

        var equipTotal = subDay + subWeek + subMonth;

        // --- Collect ALL selected fuel charges (one per fuel type group) ---
        var levelNames = { quarter:'1/4 Tank', half:'1/2 Tank', three_quarter:'3/4 Tank', full:'Full Tank' };
        var fuelItems  = [];
        var totalFuelCharge = 0;
        $('.frs-fuel-type-group').each(function(){
            var fueltype = $(this).data('fueltype');
            var $checked = $(this).find('.frs-fuel-level-radio:checked');
            if ($checked.length) {
                var level = $checked.data('level');
                var price = parseFloat($checked.data('price')) || 0;
                if (level !== 'none' && price > 0) {
                    fuelItems.push({ type: fueltype, level: level, price: price,
                                     label: fueltype + ' ' + (levelNames[level] || level) });
                    totalFuelCharge += price;
                }
            }
        });
        currentFuelCharge = totalFuelCharge;

        // --- Collect each add-on individually ---
        var addonItems  = [];
        var addonsTotal = 0;
        $('.frs-addon-check:checked').each(function(){
            var $item  = $(this).closest('.frs-addon-item');
            var name   = $item.find('.frs-addon-label').text().trim();
            var priceText = $item.find('.frs-addon-price').text().replace('+$','').replace(',','');
            var price  = parseFloat(priceText) || 0;
            addonItems.push({ name: name, price: price });
            addonsTotal += price;
        });

        var grandTotal = equipTotal + totalFuelCharge + addonsTotal;

        // --- Rebuild price summary rows dynamically ---
        // Remove all previously injected rows
        $('#frs-price-summary .frs-dynamic-row').remove();

        // Build new rows and insert before the Total row
        var $totalRow = $('#frs-price-summary .frs-total-row');

        // Equipment base
        $('#frs-base-price').text('$' + formatMoney(equipTotal));

        // Fuel rows — one per selected fuel type
        $.each(fuelItems, function(i, f){
            $('<div class="frs-price-row frs-dynamic-row" style="color:rgba(255,255,255,0.85)">' +
                '<span>Fuel: ' + f.label + '</span>' +
                '<span>$' + formatMoney(f.price) + '</span>' +
              '</div>').insertBefore($totalRow);
        });

        // Add-on rows — one per selected addon by name
        $.each(addonItems, function(i, a){
            $('<div class="frs-price-row frs-dynamic-row" style="color:rgba(255,255,255,0.85)">' +
                '<span>' + a.name + '</span>' +
                '<span>+$' + formatMoney(a.price) + '</span>' +
              '</div>').insertBefore($totalRow);
        });

        $('#frs-total-price').text('$' + formatMoney(grandTotal));
        $('#frs-full-payment-label').text('Pay full amount $' + formatMoney(grandTotal) + ' now');

        // Show summary as soon as any item is selected
        if (grandTotal > 0) {
            $('#frs-price-summary').slideDown(200);
        } else {
            $('#frs-price-summary').hide();
        }

        updateOrderSummary(equipTotal, fuelItems, addonItems, grandTotal);

        if (qMonth > 0) { $('#frs-duration-type').val('month'); $('#frs-duration-qty').val(qMonth); }
        else if (qWeek > 0) { $('#frs-duration-type').val('week'); $('#frs-duration-qty').val(qWeek); }
        else if (qDay > 0)  { $('#frs-duration-type').val('day'); $('#frs-duration-qty').val(qDay); }
    }

    function updateOrderSummary(equipTotal, fuelItems, addonItems, grandTotal) {
        var cap       = $('#frs-lift-capacity').val();
        var equip     = (typeof frs_equipment_data !== 'undefined' && frs_equipment_data[cap]) ? frs_equipment_data[cap] : null;
        var equipLabel = equip ? equip.label : cap;

        var qDay   = parseInt($('#frs-qty-day').val())   || 0;
        var qWeek  = parseInt($('#frs-qty-week').val())  || 0;
        var qMonth = parseInt($('#frs-qty-month').val()) || 0;

        var durationStr = '';
        if (qDay   > 0) durationStr += qDay   + ' day'   + (qDay   > 1 ? 's' : '') + ' ';
        if (qWeek  > 0) durationStr += qWeek  + ' week'  + (qWeek  > 1 ? 's' : '') + ' ';
        if (qMonth > 0) durationStr += qMonth + ' month' + (qMonth > 1 ? 's' : '') + ' ';
        durationStr = durationStr.trim() || 'Not selected';

        var html = '<div class="frs-summary-line"><span>Equipment</span><span>' + equipLabel + '</span></div>';
        html    += '<div class="frs-summary-line"><span>Duration</span><span>' + durationStr + '</span></div>';
        html    += '<div class="frs-summary-line"><span>Equipment Rental</span><span>$' + formatMoney(equipTotal) + '</span></div>';

        // Each fuel line individually
        $.each(fuelItems, function(i, f){
            html += '<div class="frs-summary-line"><span>Fuel: ' + f.label + '</span><span>$' + formatMoney(f.price) + '</span></div>';
        });

        // Each add-on individually by name
        $.each(addonItems, function(i, a){
            html += '<div class="frs-summary-line"><span>' + a.name + '</span><span>+$' + formatMoney(a.price) + '</span></div>';
        });

        $('#frs-summary-equipment').html(html);
        $('#frs-summary-total').text('$' + formatMoney(grandTotal));
    }

    function formatMoney(n) {
        return parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /* ============================================================
       STEP NAVIGATION
       ============================================================ */
    function goToStep(step) {
        if (step === 2 && !validateStep1()) return;
        if (step === 3 && !validateStep2()) return;

        $('.frs-step-content').removeClass('active');
        $('#frs-step-' + step).addClass('active');

        $('.frs-step').each(function(){
            var s = parseInt($(this).data('step'));
            if (s < step) $(this).addClass('done').removeClass('active');
            else if (s === step) $(this).addClass('active').removeClass('done');
            else $(this).removeClass('active done');
        });

        if (step === 3) setupStripe();
        if (step === 4) prefillAgreement();

        $('html,body').animate({ scrollTop: $('#frs-form-wrap').offset().top - 40 }, 300);
    }

    /* ============================================================
       VALIDATION
       ============================================================ */
    function validateStep1() {
        var cap   = $('#frs-lift-capacity').val();
        var qDay  = parseInt($('#frs-qty-day').val())   || 0;
        var qWeek = parseInt($('#frs-qty-week').val())  || 0;
        var qMon  = parseInt($('#frs-qty-month').val()) || 0;

        if (!cap) {
            $('#frs-error-1').text('Please select an equipment type.');
            return false;
        }
        if (qDay === 0 && qWeek === 0 && qMon === 0) {
            $('#frs-error-1').text('Please enter at least 1 day, week, or month for your rental.');
            return false;
        }
        $('#frs-error-1').text('');
        return true;
    }

    function validateStep2() {
        var name  = $('#frs-customer-name').val().trim();
        var phone = $('#frs-customer-phone').val().trim();
        var email = $('#frs-customer-email').val().trim();
        if (!name || !phone || !email) {
            $('#frs-error-2').text('Please fill in your name, phone, and email.');
            return false;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $('#frs-error-2').text('Please enter a valid email address.');
            return false;
        }
        $('#frs-error-2').text('');
        return true;
    }

    /* ============================================================
       STRIPE SETUP
       ============================================================ */
    function setupStripe() {
        if (!stripe) { $('#frs-stripe-element-wrap').hide(); return; }
        if (stripeElements) return;
        stripeElements    = stripe.elements();
        stripeCardElement = stripeElements.create('card', {
            style: {
                base:    { fontSize:'16px', color:'#32325d', fontFamily:'-apple-system,sans-serif' },
                invalid: { color:'#dc3545' }
            }
        });
        stripeCardElement.mount('#frs-stripe-element');
        stripeCardElement.on('change', function(e){
            $('#frs-stripe-error').text(e.error ? e.error.message : '');
        });
    }

    /* ============================================================
       AGREEMENT PREFILL — replace placeholders with real data
       ============================================================ */
    function prefillAgreement() {
        var cap       = $('#frs-lift-capacity').val();
        var equip     = (typeof frs_equipment_data !== 'undefined' && frs_equipment_data[cap]) ? frs_equipment_data[cap] : null;
        var equipLabel = equip ? equip.label : cap;

        var qDay   = parseInt($('#frs-qty-day').val())   || 0;
        var qWeek  = parseInt($('#frs-qty-week').val())  || 0;
        var qMonth = parseInt($('#frs-qty-month').val()) || 0;

        var durationParts = [];
        if (qDay   > 0) durationParts.push(qDay   + ' Day'   + (qDay   > 1 ? 's' : ''));
        if (qWeek  > 0) durationParts.push(qWeek  + ' Week'  + (qWeek  > 1 ? 's' : ''));
        if (qMonth > 0) durationParts.push(qMonth + ' Month' + (qMonth > 1 ? 's' : ''));
        var durationStr = durationParts.join(' + ') || 'Not specified';

        // Collect add-on names
        var addonNames = [];
        $('.frs-addon-check:checked').each(function(){
            addonNames.push($(this).closest('.frs-addon-item').find('.frs-addon-label').text().trim());
        });

        // Collect fuel selections
        var levelNames = { quarter:'1/4 Tank', half:'1/2 Tank', three_quarter:'3/4 Tank', full:'Full Tank' };
        var fuelParts  = [];
        $('.frs-fuel-type-group').each(function(){
            var fueltype = $(this).data('fueltype');
            var $checked = $(this).find('.frs-fuel-level-radio:checked');
            if ($checked.length) {
                var level = $checked.data('level');
                if (level && level !== 'none') {
                    fuelParts.push(fueltype + ' ' + (levelNames[level] || level));
                }
            }
        });

        // Build equipment details string
        var equipDetails = equipLabel;
        if (fuelParts.length) equipDetails += ' | Fuel: ' + fuelParts.join(', ');
        if (addonNames.length) equipDetails += ' | Add-ons: ' + addonNames.join(', ');

        // Build total
        var total = parseFloat($('#frs-total-price').text().replace('$','').replace(',','')) || 0;

        // Today's date
        var today = new Date();
        var mm    = String(today.getMonth()+1).padStart(2,'0');
        var dd    = String(today.getDate()).padStart(2,'0');
        var yyyy  = today.getFullYear();
        var dateStr = mm + '-' + dd + '-' + yyyy;

        // Get company name from localized vars or fallback
        var companyName = (typeof frs_vars !== 'undefined' && frs_vars.company_name)
            ? frs_vars.company_name : 'Rental Company';

        // Replacement map — matches {placeholders} in agreement text
        var replacements = {
            '{company_name}'      : companyName,
            '{customer_name}'     : $('#frs-customer-name').val()    || '[Customer Name]',
            '{customer_company}'  : $('#frs-customer-company').val() || '',
            '{rental_date}'       : dateStr,
            '{equipment_details}' : equipDetails,
            '{duration_type}'     : durationStr,
            '{total_price}'       : '$' + formatMoney(total),
            '{deposit_amount}'    : '$' + formatMoney(parseFloat(frs_vars.deposit) || 0),
            '{payment_type}'      : $('input[name="payment_type"]:checked').val() || 'Deposit Only',
            '{order_number}'      : '[Generated on submission]'
        };

        // Get raw agreement text and replace all placeholders
        var rawText = $('#frs-agreement-text').data('raw-text') || $('#frs-agreement-text').text();

        // Store raw text on first call so we always replace from the original
        if (!$('#frs-agreement-text').data('raw-text')) {
            $('#frs-agreement-text').data('raw-text', rawText);
        } else {
            rawText = $('#frs-agreement-text').data('raw-text');
        }

        var filledText = rawText;
        $.each(replacements, function(placeholder, value){
            filledText = filledText.split(placeholder).join(value);
        });

        $('#frs-agreement-text').text(filledText);

        // Resize signature canvas
        var canvas = document.getElementById('frs-signature-pad');
        if (canvas) {
            setTimeout(function(){
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width  = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                if (signaturePad) signaturePad.clear();
            }, 100);
        }
    }

    /* ============================================================
       ORDER SUBMISSION
       ============================================================ */
    function submitOrder() {
        if (signaturePad && signaturePad.isEmpty()) {
            $('#frs-error-4').text('Please sign the rental agreement before submitting.');
            return;
        }
        if (!$('#frs-agree-check').is(':checked')) {
            $('#frs-error-4').text('Please check the agreement acceptance box.');
            return;
        }
        $('#frs-error-4').text('');
        $('#frs-loading').show();
        $('#frs-submit-order').prop('disabled', true);
        paymentType = $('input[name="payment_type"]:checked').val() || 'deposit';

        if (stripe && stripeCardElement) {
            processWithStripe();
        } else {
            doSubmitOrder('no_payment', '');
        }
    }

    function processWithStripe() {
        var qDay   = parseInt($('#frs-qty-day').val())   || 0;
        var qWeek  = parseInt($('#frs-qty-week').val())  || 0;
        var qMonth = parseInt($('#frs-qty-month').val()) || 0;
        var total  = (qDay * currentRates.day) + (qWeek * currentRates.week) + (qMonth * currentRates.month)
                   + currentFuelCharge;

        // Add addon prices
        $('.frs-addon-check:checked').each(function(){
            var p = parseFloat($(this).closest('.frs-addon-item').find('.frs-addon-price').text().replace('+$','')) || 0;
            total += p;
        });

        var amountCents = Math.round(total * 100);

        $.post(frs_vars.ajax_url, {
            action:       'frs_create_payment',
            nonce:        frs_vars.nonce,
            payment_type: paymentType,
            amount_cents: amountCents
        }, function(res){
            if (!res.success) { showError(res.data || 'Payment error.'); return; }
            var clientSecret = res.data.client_secret;
            if (paymentType === 'save_card') {
                stripe.confirmCardSetup(clientSecret, { payment_method:{ card:stripeCardElement } })
                    .then(function(r){ if (r.error) { showError(r.error.message); return; } doSubmitOrder('save_card', r.setupIntent.id); });
            } else {
                stripe.confirmCardPayment(clientSecret, { payment_method:{ card:stripeCardElement } })
                    .then(function(r){ if (r.error) { showError(r.error.message); return; } doSubmitOrder(paymentType, r.paymentIntent.id); });
            }
        });
    }

    function doSubmitOrder(payType, intentId) {
        var addons      = [];
        var addonNames  = [];
        $('.frs-addon-check:checked').each(function(){
            addons.push($(this).val());
            addonNames.push($(this).closest('.frs-addon-item').find('.frs-addon-label').text().trim());
        });

        // Collect all fuel selections
        var levelNames  = { quarter:'1/4 Tank', half:'1/2 Tank', three_quarter:'3/4 Tank', full:'Full Tank' };
        var fuelSummary = [];
        $('.frs-fuel-type-group').each(function(){
            var fueltype = $(this).data('fueltype');
            var $checked = $(this).find('.frs-fuel-level-radio:checked');
            if ($checked.length) {
                var level = $checked.data('level');
                var price = parseFloat($checked.data('price')) || 0;
                if (level !== 'none' && price > 0) {
                    fuelSummary.push(fueltype + ' ' + (levelNames[level] || level) + ' $' + price.toFixed(2));
                }
            }
        });

        var sigData = (signaturePad && !signaturePad.isEmpty()) ? signaturePad.toDataURL() : '';

        $.post(frs_vars.ajax_url, {
            action:           'frs_submit_order',
            nonce:            frs_vars.nonce,
            lift_capacity:    $('#frs-lift-capacity').val(),
            fuel_type:        isElectric ? 'Electric' : (fuelSummary.length ? fuelSummary.join('; ') : 'Not selected'),
            duration_type:    $('#frs-duration-type').val(),
            duration_qty:     $('#frs-duration-qty').val(),
            qty_day:          $('#frs-qty-day').val(),
            qty_week:         $('#frs-qty-week').val(),
            qty_month:        $('#frs-qty-month').val(),
            fuel_summary:     fuelSummary.join('; '),
            fuel_charge:      currentFuelCharge,
            addons:           addons,
            addon_names:      addonNames,
            customer_name:    $('#frs-customer-name').val(),
            customer_company: $('#frs-customer-company').val(),
            customer_phone:   $('#frs-customer-phone').val(),
            customer_email:   $('#frs-customer-email').val(),
            jobsite_address:  $('#frs-jobsite-address').val(),
            rental_notes:     $('#frs-rental-notes').val(),
            payment_type:     payType || paymentType,
            stripe_intent:    intentId,
            signature_data:   sigData
        }, function(res){
            $('#frs-loading').hide();
            if (res.success) {
                $('#frs-confirm-order-number').text(res.data.order_number);
                if (res.data.pdf_url) {
                    $('#frs-pdf-download-link').attr('href', res.data.pdf_url);
                } else {
                    $('#frs-pdf-download-link').hide();
                }
                $('.frs-step-content').removeClass('active');
                $('#frs-step-5').addClass('active');
                $('.frs-steps').hide();
                $('html,body').animate({ scrollTop: $('#frs-form-wrap').offset().top - 40 }, 300);
            } else {
                showError(res.data || 'Submission failed. Please try again.');
            }
        });
    }

    function showError(msg) {
        $('#frs-loading').hide();
        $('#frs-submit-order').prop('disabled', false);
        $('#frs-error-4').text(msg);
    }

    init();
});
