<?php
/**
 * Template Name: Checkout (Capstone)
 * Step 1: Customer details (Email + Phone validated against FluentCRM, plus addresses and payment method selection).
 */
defined( 'ABSPATH' ) || exit;
get_header();

$error = '';

// Basic cart summary for sidebar.
$products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
$product_map = [];
foreach ( $products as $p ) {
    $product_map[ (int) ( $p['id'] ?? 0 ) ] = $p;
}
$cart   = $_SESSION['cart'] ?? [];
$total  = 0.0;
foreach ( $cart as $pid => $qty ) {
    $pid = (int) $pid;
    $qty = (int) $qty;
    if ( isset( $product_map[ $pid ] ) ) {
        $total += (float) ( $product_map[ $pid ]['price'] ?? 0 ) * $qty;
    }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $shipping_address = isset( $_POST['shipping_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shipping_address'] ) ) : '';
    $billing_address  = isset( $_POST['billing_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['billing_address'] ) ) : '';
    $payment_method   = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'credit_card';
    $order_notes      = isset( $_POST['order_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) ) : '';

    $sub   = function_exists( 'tennispro_checkout_verify' ) ? tennispro_checkout_verify( $email, $phone ) : null;
    if ( $sub ) {
        $_SESSION['checkout_email']   = $sub['email'] ?? $email;
        $_SESSION['checkout_user_id'] = $sub['id'] ?? null;
        $_SESSION['checkout_name']    = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
        if ( $_SESSION['checkout_name'] === '' ) {
            $_SESSION['checkout_name'] = $email;
        }
        $_SESSION['checkout_phone']           = $phone;
        $_SESSION['checkout_shipping_address'] = $shipping_address;
        $_SESSION['checkout_billing_address']  = $billing_address;
        $_SESSION['checkout_payment_method']   = $payment_method;
        $_SESSION['checkout_order_notes']      = $order_notes;

        wp_safe_redirect( get_permalink( get_page_by_path( 'payment' ) ) ?: home_url( '/payment/' ) );
        exit;
    }
    $error = 'Email and Phone not found in our customer list. Please register first or check your details.';
}
?>
<h1>Checkout</h1>
<div class="checkout-layout">
    <section class="checkout-main">
        <h2>Shipping & Contact</h2>

        <?php if ( $error ) : ?>
            <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Email (must match customer list)</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" required>
            </div>

            <div class="form-group">
                <label for="shipping_address">Shipping Address</label>
                <textarea id="shipping_address" name="shipping_address" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="billing_address">Billing Address</label>
                <textarea id="billing_address" name="billing_address" rows="3" required></textarea>
            </div>

            <h2>Payment Information</h2>
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" required>
                    <option value="credit_card">Credit Card</option>
                    <option value="debit_card">Debit Card</option>
                    <option value="paypal_demo">PayPal (demo only)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="order_notes">Order Notes (Optional)</label>
                <textarea id="order_notes" name="order_notes" rows="3" placeholder="Any special instructions for your order"></textarea>
            </div>

            <button type="submit" class="btn">Continue to payment</button>
        </form>

        <p style="margin-top:16px;">
            <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'register' ) ) ?: home_url( '/register/' ) ); ?>">Register here</a> if you do not have an account.
        </p>
    </section>

    <aside class="checkout-summary">
        <h2>Order Summary</h2>
        <?php if ( empty( $cart ) ) : ?>
            <p>Your cart is empty.</p>
        <?php else : ?>
            <ul class="cart-summary-list">
                <?php foreach ( $cart as $pid => $qty ) : ?>
                    <?php
                    $pid = (int) $pid;
                    $qty = (int) $qty;
                    if ( ! isset( $product_map[ $pid ] ) ) {
                        continue;
                    }
                    $p      = $product_map[ $pid ];
                    $price  = (float) ( $p['price'] ?? 0 );
                    $line   = $price * $qty;
                    ?>
                    <li>
                        <span><?php echo esc_html( $p['name'] ?? '' ); ?> × <?php echo $qty; ?></span>
                        <span>$<?php echo number_format( $line, 2 ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php
            $shipping = $total > 0 ? 10.0 : 0.0;
            $grand    = $total + $shipping;
            ?>
            <ul class="cart-summary-list" style="margin-top:10px;">
                <li><span>Subtotal</span><span>$<?php echo number_format( $total, 2 ); ?></span></li>
                <li><span>Shipping</span><span>$<?php echo number_format( $shipping, 2 ); ?></span></li>
                <li class="cart-summary-total"><span>Total</span><span>$<?php echo number_format( $grand, 2 ); ?></span></li>
            </ul>
        <?php endif; ?>
    </aside>
</div>

<?php get_footer(); ?>
