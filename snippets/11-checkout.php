<?php
/**
 * Snippet: TennisPro Checkout
 * Shortcode: [tennispro_checkout]
 * Description: Checkout page (shipping/contact form + order summary sidebar) or order confirmation.
 */

add_shortcode( 'tennispro_checkout', function () {
    if ( isset( $_GET['order_success'] ) && ! empty( $_SESSION['last_order_id'] ) ) {
        ob_start();
        tennispro_render_order_confirmation_body();
        return ob_get_clean();
    }

    ob_start();

    $products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
    $product_map = [];
    foreach ( $products as $p ) {
        $product_map[ (int) ( $p['id'] ?? 0 ) ] = $p;
    }
    $cart  = $_SESSION['cart'] ?? [];
    $total = 0.0;
    foreach ( $cart as $pid => $qty ) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ( isset( $product_map[ $pid ] ) ) {
            $total += (float) ( $product_map[ $pid ]['price'] ?? 0 ) * $qty;
        }
    }

    $error = isset( $_SESSION['checkout_error'] ) ? (string) $_SESSION['checkout_error'] : '';
    unset( $_SESSION['checkout_error'] );
    ?>
    <h1>Checkout</h1>
    <div class="checkout-layout">
        <section class="checkout-main">
            <h2>Shipping &amp; Contact</h2>

            <?php if ( $error ) : ?>
                <div class="alert alert-error"><?php echo wp_kses_post( $error ); ?></div>
            <?php endif; ?>

            <p style="background:#e8f4fd;border:1px solid #b8daff;color:#004085;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px;">
                Your Email and Phone must match your <strong>FluentCRM</strong> registration.
                Not registered? <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'register' ) ) ?: home_url( '/register/' ) ); ?>">Register here</a>.
            </p>
            <form method="post">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name">
                </div>
                <div class="form-group">
                    <label for="email">Email (FluentCRM registered)</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (FluentCRM registered)</label>
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
                        if ( ! isset( $product_map[ $pid ] ) ) continue;
                        $p     = $product_map[ $pid ];
                        $price = (float) ( $p['price'] ?? 0 );
                        $line  = $price * $qty;
                        ?>
                        <li>
                            <span><?php echo esc_html( $p['name'] ?? '' ); ?> &times; <?php echo $qty; ?></span>
                            <span>$<?php echo number_format( $line, 2 ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php $shipping = $total > 0 ? 10.0 : 0.0; $grand = $total + $shipping; ?>
                <ul class="cart-summary-list" style="margin-top:10px;">
                    <li><span>Subtotal</span><span>$<?php echo number_format( $total, 2 ); ?></span></li>
                    <li><span>Shipping</span><span>$<?php echo number_format( $shipping, 2 ); ?></span></li>
                    <li class="cart-summary-total"><span>Total</span><span>$<?php echo number_format( $grand, 2 ); ?></span></li>
                </ul>
            <?php endif; ?>
        </aside>
    </div>

    <?php
    return ob_get_clean();
} );
