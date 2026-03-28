<?php
/**
 * Snippet: TennisPro Payment
 * Shortcode: [tennispro_payment]
 * Description: Payment page – card details form. Requires checkout session.
 */

add_shortcode( 'tennispro_payment', function () {
    if ( empty( $_SESSION['checkout_email'] ) ) {
        return '<div class="alert alert-error">Please <a href="' . esc_url( get_permalink( get_page_by_path( 'checkout' ) ) ?: home_url( '/checkout/' ) ) . '">complete checkout</a> first.</div>';
    }

    ob_start();
    ?>
    <h1>Payment</h1>
    <p>Please enter your card details to complete the order.</p>
    <form method="post">
        <div class="form-group">
            <label for="card_name">Card holder name</label>
            <input type="text" id="card_name" name="card_name" required>
        </div>
        <div class="form-group">
            <label for="card_no">Card number</label>
            <input type="text" id="card_no" name="card_no" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone number</label>
            <input type="text" id="phone" name="phone" required>
        </div>
        <button type="submit" class="btn">Confirm payment</button>
    </form>
    <?php
    return ob_get_clean();
} );
