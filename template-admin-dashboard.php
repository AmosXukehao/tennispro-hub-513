<?php
/**
 * Template Name: Admin Dashboard (Capstone)
 * Admin-only dashboard showing real order and customer statistics.
 */
defined( 'ABSPATH' ) || exit;

// Protect with custom admin session (admin / admin123 from Admin Login page).
if ( empty( $_SESSION['capstone_admin_logged_in'] ) ) {
    wp_safe_redirect( get_permalink( get_page_by_path( 'admin-login' ) ) ?: home_url( '/' ) );
    exit;
}

get_header();

global $wpdb;
$prefix = $wpdb->prefix;

// Aggregate metrics from orders table.
$total_revenue = (float) $wpdb->get_var( "SELECT SUM(total_amount) FROM {$prefix}orders" );
$total_orders  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}orders" );
$total_customers = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_name) FROM {$prefix}orders" );
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0.0;

// Support tickets: total + open.
$total_tickets = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}support_tickets" );
$open_tickets  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}support_tickets WHERE status = 'open'" );

// Job applications.
$total_applications = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}job_applications" );

// FluentCRM subscribers (if table exists).
$total_subscribers = 0;
$fc_table = $prefix . 'fc_subscribers';
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fc_table}'" ) === $fc_table ) {
    $total_subscribers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fc_table}" );
}

// Recent orders.
$recent_orders = $wpdb->get_results(
    "SELECT id, user_name, total_amount, created_at FROM {$prefix}orders ORDER BY created_at DESC LIMIT 10",
    ARRAY_A
);

// Top selling products from order_items, enriched with product names.
$top_products = $wpdb->get_results(
    "SELECT product_id, SUM(quantity) AS qty, SUM(quantity * unit_price) AS revenue
     FROM {$prefix}order_items
     GROUP BY product_id
     ORDER BY qty DESC
     LIMIT 6",
    ARRAY_A
);

$product_names = [];
if ( function_exists( 'tennispro_get_products' ) ) {
    foreach ( tennispro_get_products() as $p ) {
        $id = (int) ( $p['id'] ?? 0 );
        if ( $id > 0 ) {
            $product_names[ $id ] = $p['name'] ?? 'Product #' . $id;
        }
    }
}
?>

<h1>Admin Dashboard</h1>

<div class="admin-tabs">
    <span class="admin-tab admin-tab-active">Dashboard</span>
    <a class="admin-tab" href="<?php echo esc_url( admin_url( 'admin.php?page=tennispro-products' ) ); ?>">Products JSON</a>
    <a class="admin-tab" href="<?php echo esc_url( get_permalink( get_page_by_path( 'customer-list' ) ) ?: home_url( '/customer-list/' ) ); ?>">Customers</a>
    <a class="admin-tab" href="<?php echo esc_url( get_permalink( get_page_by_path( 'support' ) ) ?: home_url( '/support/' ) ); ?>">Support</a>
    <a class="admin-tab" href="<?php echo esc_url( get_permalink( get_page_by_path( 'jobs' ) ) ?: home_url( '/jobs/' ) ); ?>">Recruitment</a>
</div>

<div class="admin-dashboard-grid">
    <div class="metric-card">
        <h2>Total Revenue</h2>
        <p class="metric-value">$<?php echo number_format( $total_revenue, 2 ); ?></p>
    </div>
    <div class="metric-card">
        <h2>Total Orders</h2>
        <p class="metric-value"><?php echo (int) $total_orders; ?></p>
    </div>
    <div class="metric-card">
        <h2>Total Customers</h2>
        <p class="metric-value"><?php echo (int) $total_customers; ?></p>
    </div>
    <div class="metric-card">
        <h2>Average Order Value</h2>
        <p class="metric-value">$<?php echo number_format( $avg_order_value, 2 ); ?></p>
    </div>
    <div class="metric-card">
        <h2>Support Tickets</h2>
        <p class="metric-value"><?php echo (int) $open_tickets; ?> open / <?php echo (int) $total_tickets; ?> total</p>
    </div>
    <div class="metric-card">
        <h2>Job Applications</h2>
        <p class="metric-value"><?php echo (int) $total_applications; ?></p>
    </div>
    <div class="metric-card">
        <h2>FluentCRM Subscribers</h2>
        <p class="metric-value"><?php echo (int) $total_subscribers; ?></p>
    </div>
</div>

<div class="admin-dashboard-columns">
    <section class="admin-panel">
        <h2>Recent Orders</h2>
        <?php if ( $recent_orders ) : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_orders as $order ) : ?>
                        <tr>
                            <td>#<?php echo (int) $order['id']; ?></td>
                            <td><?php echo esc_html( $order['user_name'] ); ?></td>
                            <td>$<?php echo number_format( (float) $order['total_amount'], 2 ); ?></td>
                            <td><?php echo esc_html( $order['created_at'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No orders have been placed yet.</p>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <h2>Top Selling Products</h2>
        <?php if ( $top_products ) : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $top_products as $row ) : ?>
                        <?php
                        $pid  = (int) $row['product_id'];
                        $name = $product_names[ $pid ] ?? ( 'Product #' . $pid );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $name ); ?></td>
                            <td><?php echo (int) $row['qty']; ?></td>
                            <td>$<?php echo number_format( (float) $row['revenue'], 2 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No products have been sold yet.</p>
        <?php endif; ?>
    </section>
</div>

<?php get_footer(); ?>

