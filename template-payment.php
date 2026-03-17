<?php
/**
 * Template Name: Payment (Capstone)
 * Requires checkout login; saves order to wp_orders; simulated payment then thank you.
 */
defined( 'ABSPATH' ) || exit;

if ( empty( $_SESSION['checkout_email'] ) ) {
    wp_safe_redirect( get_permalink( get_page_by_path( 'checkout' ) ) ?: home_url( '/checkout/' ) );
    exit;
}

get_header();

global $wpdb;
$prefix = $wpdb->prefix;
$success   = false;
$order_id  = 0;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $card_name = isset( $_POST['card_name'] ) ? sanitize_text_field( wp_unslash( $_POST['card_name'] ) ) : '';
    $card_no   = isset( $_POST['card_no'] ) ? sanitize_text_field( wp_unslash( $_POST['card_no'] ) ) : '';
    $phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

    if ( $card_name !== '' && $card_no !== '' && $phone !== '' ) {
        $cart   = $_SESSION['cart'] ?? [];
        $products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
        $product_map = [];
        foreach ( $products as $p ) {
            $product_map[ (int) ( $p['id'] ?? 0 ) ] = (float) ( $p['price'] ?? 0 );
        }
        $total = 0.0;
        $items = [];
        foreach ( $cart as $pid => $qty ) {
            $pid = (int) $pid;
            $qty = (int) $qty;
            if ( isset( $product_map[ $pid ] ) && $product_map[ $pid ] > 0 ) {
                $unit = $product_map[ $pid ];
                $total += $unit * $qty;
                $items[] = [ 'product_id' => $pid, 'quantity' => $qty, 'unit_price' => $unit ];
            }
        }
        if ( $total > 0 && ! empty( $items ) ) {
            $user_name = $_SESSION['checkout_name'] ?? $_SESSION['checkout_email'] ?? 'guest';
            $wpdb->insert(
                $prefix . 'orders',
                [
                    'user_name'     => $user_name,
                    'total_amount'  => $total,
                    'contact_name'  => $card_name,
                    'contact_phone' => $phone,
                    'created_at'    => current_time( 'mysql' ),
                ],
                [ '%s', '%f', '%s', '%s', '%s' ]
            );
            $order_id = $wpdb->insert_id;
            if ( $order_id ) {
                foreach ( $items as $item ) {
                    $wpdb->insert(
                        $prefix . 'order_items',
                        [
                            'order_id'   => $order_id,
                            'product_id' => $item['product_id'],
                            'quantity'   => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                        ],
                        [ '%d', '%d', '%d', '%f' ]
                    );
                }
            }
            // Save card for this customer so it appears in the Account page.
            if ( function_exists( 'tennispro_save_customer_card' ) ) {
                $email_for_card = $_SESSION['checkout_email'] ?? '';
                tennispro_save_customer_card( $email_for_card, $card_name, $card_no );
            }
        }
        $success         = true;
        $_SESSION['cart'] = [];
    }
}
// If payment succeeded, go straight to Account / My Orders page with success flag.
if ( $success ) {
    $account_page = get_permalink( get_page_by_path( 'my-orders' ) ) ?: home_url( '/my-orders/' );
    if ( $order_id ) {
        $account_page = add_query_arg(
            [
                'order_success' => '1',
                'order_id'      => $order_id,
            ],
            $account_page
        );
    } else {
        $account_page = add_query_arg( 'order_success', '1', $account_page );
    }
    wp_safe_redirect( $account_page );
    exit;
}
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

<?php get_footer(); ?>
