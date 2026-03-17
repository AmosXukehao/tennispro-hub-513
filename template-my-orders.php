<?php
/**
 * Template Name: My Orders (Capstone)
 * Shows orders for the currently logged-in customer (FluentCRM-based login).
 */
defined( 'ABSPATH' ) || exit;

// Require customer login via FluentCRM session.
if ( empty( $_SESSION['checkout_email'] ) ) {
    wp_safe_redirect( get_permalink( get_page_by_path( 'login' ) ) ?: home_url( '/login/' ) );
    exit;
}

get_header();

global $wpdb;
$prefix = $wpdb->prefix;

$customer_name  = $_SESSION['checkout_name'] ?? '';
$customer_email = $_SESSION['checkout_email'] ?? '';

// Fetch orders for this customer.
$orders = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, user_name, total_amount, contact_name, contact_phone, created_at
         FROM {$prefix}orders
         WHERE user_name = %s OR user_name = %s
         ORDER BY created_at DESC",
        $customer_name,
        $customer_email
    ),
    ARRAY_A
);

// Optional: fetch items for a specific order when ?order_id=ID is provided.
$current_order      = null;
$current_order_items = [];
if ( isset( $_GET['order_id'] ) ) {
    $order_id = (int) $_GET['order_id'];
    if ( $order_id > 0 ) {
        $current_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_name, total_amount, contact_name, contact_phone, created_at
                 FROM {$prefix}orders
                 WHERE id = %d",
                $order_id
            ),
            ARRAY_A
        );
        if ( $current_order ) {
            $current_order_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT product_id, quantity, unit_price
                     FROM {$prefix}order_items
                     WHERE order_id = %d",
                    $order_id
                ),
                ARRAY_A
            );
        }
    }
}

// Map product names for order items.
$product_names = [];
if ( function_exists( 'tennispro_get_products' ) ) {
    foreach ( tennispro_get_products() as $p ) {
        $id = (int) ( $p['id'] ?? 0 );
        if ( $id > 0 ) {
            $product_names[ $id ] = $p['name'] ?? ( 'Product #' . $id );
        }
    }
}
?>

<div class="my-orders-layout">
    <aside class="my-orders-sidebar">
        <h2>Account</h2>
        <p class="my-orders-user">
            Logged in as<br>
            <strong><?php echo esc_html( $customer_name !== '' ? $customer_name : $customer_email ); ?></strong>
        </p>
        <nav class="my-orders-menu">
            <a href="<?php echo esc_url( get_permalink() ); ?>" class="active">Overview</a>
            <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'cart' ) ) ?: home_url( '/cart/' ) ); ?>">View Cart</a>
            <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'products' ) ) ?: home_url( '/products/' ) ); ?>">Browse Products</a>
            <a href="<?php echo esc_url( add_query_arg( 'logout', '1', get_permalink( get_page_by_path( 'login' ) ) ?: home_url( '/login/' ) ) ); ?>">Logout</a>
        </nav>
    </aside>

    <main class="my-orders-main">
        <?php if ( isset( $_GET['order_success'] ) ) : ?>
            <div class="alert alert-success">
                Order placed successfully! Your order has been confirmed. You will receive an email confirmation shortly.
            </div>
        <?php endif; ?>

        <section class="account-section">
            <h1>Account overview</h1>
            <p>Email: <strong><?php echo esc_html( $customer_email ); ?></strong></p>
            <?php if ( $customer_name ) : ?>
                <p>Name: <strong><?php echo esc_html( $customer_name ); ?></strong></p>
            <?php endif; ?>
        </section>

        <section class="account-section">
            <h2>Saved cards</h2>
            <?php
            $cards = function_exists( 'tennispro_get_customer_cards' ) ? tennispro_get_customer_cards( $customer_email ) : [];
            ?>
            <?php if ( $cards ) : ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Holder</th>
                            <th>Brand</th>
                            <th>Number</th>
                            <th>Added at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $cards as $card ) : ?>
                            <tr>
                                <td><?php echo esc_html( $card['holder_name'] ); ?></td>
                                <td><?php echo esc_html( $card['card_brand'] ); ?></td>
                                <td>**** **** **** <?php echo esc_html( $card['card_last4'] ); ?></td>
                                <td><?php echo esc_html( $card['created_at'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3 style="margin-top:16px;">Add a new card</h3>
            <?php
            $card_msg = '';
            if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_add_card'] ) && check_admin_referer( 'tennispro_add_card', 'tennispro_add_card_nonce' ) ) {
                $holder = isset( $_POST['card_holder'] ) ? sanitize_text_field( wp_unslash( $_POST['card_holder'] ) ) : '';
                $number = isset( $_POST['card_number'] ) ? sanitize_text_field( wp_unslash( $_POST['card_number'] ) ) : '';
                if ( function_exists( 'tennispro_save_customer_card' ) && tennispro_save_customer_card( $customer_email, $holder, $number ) ) {
                    $card_msg = 'Card saved successfully.';
                    $cards    = tennispro_get_customer_cards( $customer_email );
                } else {
                    $card_msg = 'Failed to save card. Please check the details.';
                }
            }
            ?>
            <?php if ( $card_msg ) : ?>
                <div class="alert alert-success"><?php echo esc_html( $card_msg ); ?></div>
            <?php endif; ?>
            <form method="post" class="account-card-form">
                <?php wp_nonce_field( 'tennispro_add_card', 'tennispro_add_card_nonce' ); ?>
                <input type="hidden" name="tennispro_add_card" value="1">
                <div class="form-group">
                    <label for="card_holder">Card holder name</label>
                    <input type="text" id="card_holder" name="card_holder" required>
                </div>
                <div class="form-group">
                    <label for="card_number">Card number</label>
                    <input type="text" id="card_number" name="card_number" required>
                </div>
                <button type="submit" class="btn">Save card</button>
            </form>
        </section>

        <section class="account-section">
            <h2>My Orders</h2>

            <?php if ( ! $orders ) : ?>
                <p>You have not placed any orders yet.</p>
            <?php else : ?>
                <?php foreach ( $orders as $order ) : ?>
                    <article class="order-card">
                        <header>
                            <div>
                                <span class="order-number">Order #<?php echo (int) $order['id']; ?></span>
                                <span class="order-date"><?php echo esc_html( $order['created_at'] ); ?></span>
                            </div>
                            <span class="order-total">$<?php echo number_format( (float) $order['total_amount'], 2 ); ?></span>
                        </header>
                        <div class="order-meta">
                            <span>Contact: <?php echo esc_html( $order['contact_name'] ); ?></span>
                            <span>Phone: <?php echo esc_html( $order['contact_phone'] ); ?></span>
                            <a class="btn btn-secondary" href="<?php echo esc_url( add_query_arg( 'order_id', (int) $order['id'], get_permalink() ) ); ?>">View details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <?php if ( $current_order && $current_order_items ) : ?>
            <section class="order-details">
                <h3>Order #<?php echo (int) $current_order['id']; ?> details</h3>
                <p>
                    Placed on <?php echo esc_html( $current_order['created_at'] ); ?>,
                    total $<?php echo number_format( (float) $current_order['total_amount'], 2 ); ?>.
                </p>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit price</th>
                            <th>Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $current_order_items as $item ) : ?>
                            <?php
                            $pid   = (int) $item['product_id'];
                            $name  = $product_names[ $pid ] ?? ( 'Product #' . $pid );
                            $qty   = (int) $item['quantity'];
                            $price = (float) $item['unit_price'];
                            ?>
                            <tr>
                                <td><?php echo esc_html( $name ); ?></td>
                                <td><?php echo $qty; ?></td>
                                <td>$<?php echo number_format( $price, 2 ); ?></td>
                                <td>$<?php echo number_format( $price * $qty, 2 ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>

