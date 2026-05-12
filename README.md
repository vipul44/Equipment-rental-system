# Forklift Rental Reservation System - WordPress Plugin

A complete forklift rental reservation system for WordPress/WooCommerce-free sites.

---

## Installation

1. Upload the `forklift-rental` folder to `/wp-content/plugins/`
2. Activate the plugin in **WordPress Admin → Plugins**
3. The database table is created automatically on activation

---

## Usage

Add the shortcode to any page or post:

```
[forklift_rental]
```

---

## Configuration

### Stripe Payments
1. Go to **Forklift Rental → Settings**
2. Enter your Stripe Publishable Key and Secret Key
3. Set mode to **Test** while testing, **Live** when ready
4. Get your keys from https://dashboard.stripe.com/apikeys

### Pricing
1. Go to **Forklift Rental → Pricing & Add-ons**
2. Update any equipment price, fuel surcharge, add-on price, or deposit amount
3. Click **Save Pricing** — no developer needed

### Agreement Text
1. Go to **Forklift Rental → Agreement Text**
2. Edit the rental agreement
3. Available placeholders:
   - `{company_name}` `{customer_name}` `{customer_company}`
   - `{rental_date}` `{equipment_details}` `{duration_type}`
   - `{total_price}` `{deposit_amount}` `{payment_type}` `{order_number}`

---

## PDF Generation

### Option A: HTML Fallback (default, no install needed)
PDFs are generated as HTML files saved to `/wp-content/uploads/frs-agreements/`.
They display correctly in browsers and are emailed as download links.

### Option B: TCPDF (recommended for true PDF)
1. Download TCPDF from https://tcpdf.org or install via Composer
2. Place the `tcpdf` folder inside `forklift-rental/includes/tcpdf/`
3. The plugin automatically detects and uses TCPDF

---

## Admin Features

- **All Orders**: View all reservations, filter by status, click any order for full details
- **Signed Agreement**: Each order has a downloadable PDF with the customer's signature
- **Email Notifications**: Customer gets confirmation + PDF link; admin gets full order details instantly

---

## Customer Flow

1. Select lift capacity, fuel type, rental duration
2. Choose optional add-ons (live price updates)
3. Enter customer and job site information
4. Choose payment: Deposit / Full Payment / Save Card for Later
5. Enter card details (Stripe)
6. Review and e-sign the rental agreement
7. Submit → receive order number + confirmation email with PDF

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- SSL certificate (required for Stripe payments)
- wp_mail() configured (SMTP plugin recommended for reliable email)

---

## Recommended Companion Plugins

- **WP Mail SMTP** – ensures confirmation emails are delivered reliably
- **Smash Balloon** or similar – not required, just notes
- **Wordfence** – security for protecting the orders data

---

## File Structure

```
forklift-rental/
├── forklift-rental.php          # Main plugin file
├── includes/
│   ├── class-frs-installer.php  # DB setup & default settings
│   ├── class-frs-settings.php   # Pricing & config helpers
│   ├── class-frs-order.php      # Order CRUD
│   ├── class-frs-pdf.php        # PDF generation
│   ├── class-frs-email.php      # Email notifications
│   ├── class-frs-payment.php    # Stripe API calls
│   └── tcpdf/                   # (optional) Place TCPDF here
├── admin/
│   ├── class-frs-admin.php      # Admin pages & AJAX
│   ├── admin.css
│   └── admin.js
├── public/
│   ├── class-frs-public.php     # Shortcode & public AJAX
│   ├── css/public.css
│   └── js/public.js
└── templates/
    └── form.php                 # Multi-step reservation form
```
