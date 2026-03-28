<?php
/**
 * Snippet: TennisPro Admin Dashboard
 * Shortcode: [tennispro_admin_dashboard]
 * Description: Full admin dashboard with tabs: Dashboard, Products, Recruitment, Support, Users, Orders.
 */

add_shortcode( 'tennispro_admin_dashboard', function () {
    if ( empty( $_SESSION['capstone_admin_logged_in'] ) ) {
        $login = get_permalink( get_page_by_path( 'admin-login' ) ) ?: home_url( '/admin-login/' );
        return '<div class="alert alert-error">Admin access required. <a href="' . esc_url( $login ) . '">Log in</a>.</div>';
    }

    ob_start();

    global $wpdb;
    $prefix = $wpdb->prefix;

    // ── Handle inline product CRUD ──
    $product_message = '';
    if ( isset( $_POST['tennispro_product_action'] ) && wp_verify_nonce( ( $_POST['tennispro_product_nonce'] ?? '' ), 'tennispro_product_crud' ) ) {
        $action        = sanitize_text_field( $_POST['tennispro_product_action'] );
        $products_list = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];

        if ( $action === 'create' ) {
            $p_name  = sanitize_text_field( $_POST['p_name'] ?? '' );
            $p_desc  = sanitize_textarea_field( $_POST['p_desc'] ?? '' );
            $p_price = floatval( $_POST['p_price'] ?? 0 );
            $p_img   = sanitize_text_field( $_POST['p_image'] ?? '' );
            if ( $p_name !== '' && $p_price > 0 ) {
                $max_id = 0;
                foreach ( $products_list as $pp ) {
                    $ppid = (int) ( $pp['id'] ?? 0 );
                    if ( $ppid > $max_id ) $max_id = $ppid;
                }
                $products_list[] = [
                    'id'          => $max_id + 1,
                    'name'        => $p_name,
                    'description' => $p_desc,
                    'price'       => $p_price,
                    'image_path'  => $p_img,
                ];
                $product_message = ( function_exists( 'tennispro_save_products' ) && tennispro_save_products( $products_list ) )
                    ? 'Product created successfully.' : 'Failed to save product.';
            } else {
                $product_message = 'Name and positive price are required.';
            }
        } elseif ( $action === 'update' ) {
            $p_id    = (int) ( $_POST['p_id'] ?? 0 );
            $p_name  = sanitize_text_field( $_POST['p_name'] ?? '' );
            $p_desc  = sanitize_textarea_field( $_POST['p_desc'] ?? '' );
            $p_price = floatval( $_POST['p_price'] ?? 0 );
            $p_img   = sanitize_text_field( $_POST['p_image'] ?? '' );
            if ( $p_id > 0 && $p_name !== '' && $p_price > 0 ) {
                foreach ( $products_list as $i => $pp ) {
                    if ( (int) ( $pp['id'] ?? 0 ) === $p_id ) {
                        $products_list[ $i ] = array_merge( $pp, [
                            'name'        => $p_name,
                            'description' => $p_desc,
                            'price'       => $p_price,
                            'image_path'  => $p_img,
                        ] );
                        break;
                    }
                }
                $product_message = ( function_exists( 'tennispro_save_products' ) && tennispro_save_products( $products_list ) )
                    ? 'Product updated.' : 'Failed to update product.';
            }
        } elseif ( $action === 'delete' ) {
            $p_id = (int) ( $_POST['p_id'] ?? 0 );
            if ( $p_id > 0 ) {
                $products_list = array_values( array_filter( $products_list, function ( $pp ) use ( $p_id ) {
                    return (int) ( $pp['id'] ?? 0 ) !== $p_id;
                } ) );
                $product_message = ( function_exists( 'tennispro_save_products' ) && tennispro_save_products( $products_list ) )
                    ? 'Product deleted.' : 'Failed to delete product.';
            }
        }
    }

    // ── Handle inline job status updates ──
    $jobs_message = '';
    if ( isset( $_POST['tennispro_job_update'] ) && wp_verify_nonce( ( $_POST['tennispro_job_nonce'] ?? '' ), 'tennispro_job_update' ) ) {
        $job_id = (int) ( $_POST['application_id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $allowed = [ 'received', 'in_review', 'shortlisted', 'hired', 'rejected' ];
        if ( $job_id > 0 && in_array( $status, $allowed, true ) ) {
            $updated = $wpdb->update( $prefix . 'job_applications', [ 'status' => $status ], [ 'application_id' => $job_id ], [ '%s' ], [ '%d' ] );
            $jobs_message = $updated !== false ? 'Status updated.' : 'Failed to update.';
        }
    }

    // ── Handle inline support ticket status updates ──
    $ticket_message = '';
    if ( isset( $_POST['tennispro_ticket_update'] ) && wp_verify_nonce( ( $_POST['tennispro_ticket_nonce'] ?? '' ), 'tennispro_ticket_update' ) ) {
        $tk_id     = (int) ( $_POST['ticket_id'] ?? 0 );
        $tk_status = sanitize_text_field( $_POST['ticket_status'] ?? '' );
        $tk_allowed = [ 'open', 'in_progress', 'resolved', 'closed' ];
        if ( $tk_id > 0 && in_array( $tk_status, $tk_allowed, true ) ) {
            $updated = $wpdb->update( $prefix . 'support_tickets', [ 'status' => $tk_status ], [ 'ticket_id' => $tk_id ], [ '%s' ], [ '%d' ] );
            $ticket_message = $updated !== false ? 'Ticket status updated.' : 'Failed to update.';
        }
    }

    // ── Dashboard metrics ──
    $total_revenue      = (float) $wpdb->get_var( "SELECT SUM(total_amount) FROM {$prefix}orders" );
    $total_orders       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}orders" );
    $total_customers    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_name) FROM {$prefix}orders" );
    $avg_order_value    = $total_orders > 0 ? $total_revenue / $total_orders : 0.0;
    $total_tickets      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}support_tickets" );
    $open_tickets       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}support_tickets WHERE status = 'open'" );
    $total_applications = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}job_applications" );

    $total_subscribers = 0;
    $fc_table = $prefix . 'fc_subscribers';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fc_table}'" ) === $fc_table ) {
        $total_subscribers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fc_table}" );
    }

    $recent_orders = $wpdb->get_results(
        "SELECT id, user_name, total_amount, created_at FROM {$prefix}orders ORDER BY created_at DESC LIMIT 10", ARRAY_A
    );
    $top_products = $wpdb->get_results(
        "SELECT product_id, SUM(quantity) AS qty, SUM(quantity * unit_price) AS revenue
         FROM {$prefix}order_items GROUP BY product_id ORDER BY qty DESC LIMIT 6", ARRAY_A
    );

    $product_names = [];
    if ( function_exists( 'tennispro_get_products' ) ) {
        foreach ( tennispro_get_products() as $p ) {
            $id = (int) ( $p['id'] ?? 0 );
            if ( $id > 0 ) $product_names[ $id ] = $p['name'] ?? 'Product #' . $id;
        }
    }

    $job_applications = $wpdb->get_results(
        "SELECT application_id, full_name, email, phone, position, cover_letter, cv_file_path, submitted_at, status
         FROM {$prefix}job_applications ORDER BY submitted_at DESC LIMIT 50", ARRAY_A
    );

    $all_products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];

    $all_subscribers = [];
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fc_table}'" ) === $fc_table ) {
        $all_subscribers = $wpdb->get_results(
            "SELECT id, first_name, last_name, email, phone, status, created_at FROM {$fc_table} ORDER BY created_at DESC LIMIT 200", ARRAY_A
        );
    }

    $all_orders = $wpdb->get_results(
        "SELECT id, user_name, total_amount, contact_name, contact_phone, created_at
         FROM {$prefix}orders ORDER BY created_at DESC LIMIT 200", ARRAY_A
    );

    $all_tickets = $wpdb->get_results(
        "SELECT ticket_id, customer_name, customer_email, subject, message, submitted_at, status
         FROM {$prefix}support_tickets ORDER BY submitted_at DESC LIMIT 200", ARRAY_A
    );
    ?>

    <h1>Admin Dashboard</h1>

    <?php
    $tab_style = 'border:none;outline:none;box-shadow:none;border-width:0;padding:8px 16px;border-radius:6px;background:#dde5cf;color:#263026;font-size:14px;cursor:pointer;font-weight:500;text-decoration:none;display:inline-block;';
    $tab_active_style = str_replace( ['background:#dde5cf','color:#263026'], ['background:#588157','color:#f6fff4'], $tab_style );
    $tab_logout_style = str_replace( ['background:#dde5cf','color:#263026'], ['background:#e8e0d4','color:#5a3e2b'], $tab_style ) . 'margin-left:auto;';
    ?>
    <div class="admin-tabs" id="admin-tabs" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;align-items:center;">
        <button type="button" class="admin-tab admin-tab-active" data-tab="dashboard" style="<?php echo $tab_active_style; ?>">Dashboard</button>
        <button type="button" class="admin-tab" data-tab="products" style="<?php echo $tab_style; ?>">Products JSON</button>
        <button type="button" class="admin-tab" data-tab="recruitment" style="<?php echo $tab_style; ?>">Recruitment</button>
        <button type="button" class="admin-tab" data-tab="support" style="<?php echo $tab_style; ?>">Support</button>
        <button type="button" class="admin-tab" data-tab="all-users" style="<?php echo $tab_style; ?>">All Users</button>
        <button type="button" class="admin-tab" data-tab="all-orders" style="<?php echo $tab_style; ?>">All Orders</button>
        <a class="admin-tab admin-tab-logout" href="<?php echo esc_url( add_query_arg( 'capstone_logout', '1', home_url( '/' ) ) ); ?>" style="<?php echo $tab_logout_style; ?>">Admin Logout</a>
    </div>

    <!-- TAB: Dashboard -->
    <div class="admin-tab-panel" id="panel-dashboard">
        <div class="admin-dashboard-grid">
            <div class="metric-card"><h2>Total Revenue</h2><p class="metric-value">$<?php echo number_format( $total_revenue, 2 ); ?></p></div>
            <div class="metric-card"><h2>Total Orders</h2><p class="metric-value"><?php echo (int) $total_orders; ?></p></div>
            <div class="metric-card"><h2>Total Customers</h2><p class="metric-value"><?php echo (int) $total_customers; ?></p></div>
            <div class="metric-card"><h2>Average Order Value</h2><p class="metric-value">$<?php echo number_format( $avg_order_value, 2 ); ?></p></div>
            <div class="metric-card"><h2>Support Tickets</h2><p class="metric-value"><?php echo (int) $open_tickets; ?> open / <?php echo (int) $total_tickets; ?> total</p></div>
            <div class="metric-card"><h2>Job Applications</h2><p class="metric-value"><?php echo (int) $total_applications; ?></p></div>
            <div class="metric-card"><h2>FluentCRM Subscribers</h2><p class="metric-value"><?php echo (int) $total_subscribers; ?></p></div>
        </div>
        <div class="admin-dashboard-columns">
            <section class="admin-panel">
                <h2>Recent Orders</h2>
                <?php if ( $recent_orders ) : ?>
                    <table class="admin-table">
                        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Date</th></tr></thead>
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
                    <p>No orders yet.</p>
                <?php endif; ?>
            </section>
            <section class="admin-panel">
                <h2>Top Selling Products</h2>
                <?php if ( $top_products ) : ?>
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
                        <tbody>
                        <?php foreach ( $top_products as $row ) : ?>
                            <?php $pid = (int) $row['product_id']; $name = $product_names[ $pid ] ?? ( 'Product #' . $pid ); ?>
                            <tr>
                                <td><?php echo esc_html( $name ); ?></td>
                                <td><?php echo (int) $row['qty']; ?></td>
                                <td>$<?php echo number_format( (float) $row['revenue'], 2 ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No products sold yet.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- TAB: Products JSON -->
    <div class="admin-tab-panel" id="panel-products" style="display:none;">
        <section class="admin-panel">
            <h2>Product Management (<?php echo count( $all_products ); ?> products)</h2>
            <p>View, create, update, or delete products stored in <code>products.json</code>.</p>
            <?php if ( $product_message ) : ?>
                <div class="alert alert-success"><?php echo esc_html( $product_message ); ?></div>
            <?php endif; ?>
            <h3>Existing products</h3>
            <?php if ( $all_products ) : ?>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Image</th><th>Description</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ( $all_products as $prod ) : ?>
                        <?php $pid = (int) ( $prod['id'] ?? 0 ); ?>
                        <tr>
                            <td><?php echo $pid; ?></td>
                            <td>
                                <form method="post" style="display:inline;" id="pf-<?php echo $pid; ?>">
                                    <?php wp_nonce_field( 'tennispro_product_crud', 'tennispro_product_nonce' ); ?>
                                    <input type="hidden" name="tennispro_product_action" value="update">
                                    <input type="hidden" name="p_id" value="<?php echo $pid; ?>">
                                    <input type="text" name="p_name" value="<?php echo esc_attr( $prod['name'] ?? '' ); ?>" style="width:140px;">
                            </td>
                            <td><input type="number" name="p_price" step="0.01" value="<?php echo esc_attr( $prod['price'] ?? 0 ); ?>" style="width:80px;"></td>
                            <td><input type="text" name="p_image" value="<?php echo esc_attr( $prod['image_path'] ?? $prod['image'] ?? '' ); ?>" style="width:120px;"></td>
                            <td><textarea name="p_desc" rows="1" style="width:160px;vertical-align:middle;"><?php echo esc_textarea( $prod['description'] ?? '' ); ?></textarea></td>
                            <td style="white-space:nowrap;">
                                    <button type="submit" class="btn" style="padding:4px 10px;font-size:12px;">Update</button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                    <?php wp_nonce_field( 'tennispro_product_crud', 'tennispro_product_nonce' ); ?>
                                    <input type="hidden" name="tennispro_product_action" value="delete">
                                    <input type="hidden" name="p_id" value="<?php echo $pid; ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else : ?>
                <p>No products found in products.json.</p>
            <?php endif; ?>
            <h3 style="margin-top:24px;">Add new product</h3>
            <form method="post" style="max-width:600px;margin-bottom:20px;">
                <?php wp_nonce_field( 'tennispro_product_crud', 'tennispro_product_nonce' ); ?>
                <input type="hidden" name="tennispro_product_action" value="create">
                <div class="form-group"><label>Name</label><input type="text" name="p_name" required></div>
                <div class="form-group"><label>Description</label><textarea name="p_desc" rows="2"></textarea></div>
                <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><label>Price</label><input type="number" name="p_price" step="0.01" required></div>
                    <div><label>Image path</label><input type="text" name="p_image" placeholder="image/product1.jpg"></div>
                </div>
                <button type="submit" class="btn">Create product</button>
            </form>
        </section>
    </div>

    <!-- TAB: Recruitment -->
    <div class="admin-tab-panel" id="panel-recruitment" style="display:none;">
        <section class="admin-panel">
            <h2>Job Applications (<?php echo (int) $total_applications; ?>)</h2>
            <p>View all submitted job applications and update their status.</p>
            <?php if ( $jobs_message ) : ?>
                <div class="alert alert-success"><?php echo esc_html( $jobs_message ); ?></div>
            <?php endif; ?>
            <?php if ( $job_applications ) : ?>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Position</th><th>Submitted</th><th>Cover Letter</th><th>CV</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ( $job_applications as $job ) : ?>
                        <tr>
                            <td>#<?php echo (int) $job['application_id']; ?></td>
                            <td><?php echo esc_html( $job['full_name'] ); ?></td>
                            <td><?php echo esc_html( $job['email'] ); ?></td>
                            <td><?php echo esc_html( $job['phone'] ); ?></td>
                            <td><?php echo esc_html( $job['position'] ); ?></td>
                            <td><?php echo esc_html( $job['submitted_at'] ); ?></td>
                            <td style="max-width:200px;font-size:12px;"><?php echo nl2br( esc_html( mb_strimwidth( $job['cover_letter'] ?? '', 0, 120, '…' ) ) ); ?></td>
                            <td>
                                <?php if ( ! empty( $job['cv_file_path'] ) ) :
                                    $cv_url = content_url( 'uploads/' . $job['cv_file_path'] ); ?>
                                    <a href="<?php echo esc_url( $cv_url ); ?>" target="_blank" class="btn" style="padding:3px 8px;font-size:12px;">Download</a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:flex;gap:4px;align-items:center;">
                                    <?php wp_nonce_field( 'tennispro_job_update', 'tennispro_job_nonce' ); ?>
                                    <input type="hidden" name="tennispro_job_update" value="1">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $job['application_id']; ?>">
                                    <select name="status">
                                        <option value="received" <?php selected( $job['status'], 'received' ); ?>>Received</option>
                                        <option value="in_review" <?php selected( $job['status'], 'in_review' ); ?>>In Review</option>
                                        <option value="shortlisted" <?php selected( $job['status'], 'shortlisted' ); ?>>Shortlisted</option>
                                        <option value="hired" <?php selected( $job['status'], 'hired' ); ?>>Hired</option>
                                        <option value="rejected" <?php selected( $job['status'], 'rejected' ); ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="btn" style="padding:4px 10px;">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else : ?>
                <p>No job applications have been submitted yet.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- TAB: Support -->
    <div class="admin-tab-panel" id="panel-support" style="display:none;">
        <section class="admin-panel">
            <h2>Support Tickets (<?php echo count( $all_tickets ); ?>)</h2>
            <p>All customer support tickets. Update status to manage resolution.</p>
            <?php if ( $ticket_message ) : ?>
                <div class="alert alert-success"><?php echo esc_html( $ticket_message ); ?></div>
            <?php endif; ?>
            <?php if ( $all_tickets ) : ?>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Submitted</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ( $all_tickets as $tk ) : ?>
                        <tr>
                            <td>#<?php echo (int) $tk['ticket_id']; ?></td>
                            <td><?php echo esc_html( $tk['customer_name'] ); ?></td>
                            <td><?php echo esc_html( $tk['customer_email'] ); ?></td>
                            <td><?php echo esc_html( $tk['subject'] ); ?></td>
                            <td style="max-width:240px;font-size:12px;"><?php echo nl2br( esc_html( mb_strimwidth( $tk['message'] ?? '', 0, 150, '…' ) ) ); ?></td>
                            <td><?php echo esc_html( $tk['submitted_at'] ); ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:4px;align-items:center;">
                                    <?php wp_nonce_field( 'tennispro_ticket_update', 'tennispro_ticket_nonce' ); ?>
                                    <input type="hidden" name="tennispro_ticket_update" value="1">
                                    <input type="hidden" name="ticket_id" value="<?php echo (int) $tk['ticket_id']; ?>">
                                    <select name="ticket_status">
                                        <option value="open" <?php selected( $tk['status'], 'open' ); ?>>Open</option>
                                        <option value="in_progress" <?php selected( $tk['status'], 'in_progress' ); ?>>In Progress</option>
                                        <option value="resolved" <?php selected( $tk['status'], 'resolved' ); ?>>Resolved</option>
                                        <option value="closed" <?php selected( $tk['status'], 'closed' ); ?>>Closed</option>
                                    </select>
                                    <button type="submit" class="btn" style="padding:4px 10px;">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else : ?>
                <p>No support tickets have been submitted yet.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- TAB: All Users -->
    <div class="admin-tab-panel" id="panel-all-users" style="display:none;">
        <section class="admin-panel">
            <h2>All Registered Users (<?php echo count( $all_subscribers ); ?>)</h2>
            <p>All contacts from FluentCRM subscriber list.</p>
            <?php if ( $all_subscribers ) : ?>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Registered</th></tr></thead>
                    <tbody>
                    <?php foreach ( $all_subscribers as $sub ) : ?>
                        <tr>
                            <td><?php echo (int) $sub['id']; ?></td>
                            <td><?php echo esc_html( $sub['first_name'] ); ?></td>
                            <td><?php echo esc_html( $sub['last_name'] ); ?></td>
                            <td><?php echo esc_html( $sub['email'] ); ?></td>
                            <td><?php echo esc_html( $sub['phone'] ?: 'N/A' ); ?></td>
                            <td><?php echo esc_html( ucfirst( $sub['status'] ) ); ?></td>
                            <td><?php echo esc_html( $sub['created_at'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else : ?>
                <p>No registered users found.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- TAB: All Orders -->
    <div class="admin-tab-panel" id="panel-all-orders" style="display:none;">
        <section class="admin-panel">
            <h2>All Orders (<?php echo count( $all_orders ); ?>)</h2>
            <p>Complete order history across all customers.</p>
            <?php if ( $all_orders ) : ?>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead><tr><th>Order #</th><th>Customer</th><th>Contact</th><th>Phone</th><th>Total</th><th>Date</th><th>Items</th></tr></thead>
                    <tbody>
                    <?php foreach ( $all_orders as $o ) : ?>
                        <?php
                        $oid = (int) $o['id'];
                        $order_items = $wpdb->get_results(
                            $wpdb->prepare( "SELECT product_id, quantity, unit_price FROM {$prefix}order_items WHERE order_id = %d", $oid ), ARRAY_A
                        );
                        $items_desc = [];
                        foreach ( $order_items as $oi ) {
                            $pname = $product_names[ (int) $oi['product_id'] ] ?? ( 'Product #' . $oi['product_id'] );
                            $items_desc[] = $pname . ' &times;' . (int) $oi['quantity'];
                        }
                        ?>
                        <tr>
                            <td>#<?php echo $oid; ?></td>
                            <td><?php echo esc_html( $o['user_name'] ); ?></td>
                            <td><?php echo esc_html( $o['contact_name'] ); ?></td>
                            <td><?php echo esc_html( $o['contact_phone'] ?: 'N/A' ); ?></td>
                            <td>$<?php echo number_format( (float) $o['total_amount'], 2 ); ?></td>
                            <td><?php echo esc_html( $o['created_at'] ); ?></td>
                            <td style="font-size:12px;"><?php echo implode( ', ', $items_desc ) ?: '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else : ?>
                <p>No orders yet.</p>
            <?php endif; ?>
        </section>
    </div>

    <script>
    (function(){
        var tabs = document.querySelectorAll('#admin-tabs .admin-tab[data-tab]');
        var panels = document.querySelectorAll('.admin-tab-panel');
        var normalStyle = 'border:none;outline:none;box-shadow:none;border-width:0;padding:8px 16px;border-radius:6px;background:#dde5cf;color:#263026;font-size:14px;cursor:pointer;font-weight:500;text-decoration:none;display:inline-block;';
        var activeStyle = 'border:none;outline:none;box-shadow:none;border-width:0;padding:8px 16px;border-radius:6px;background:#588157;color:#f6fff4;font-size:14px;cursor:pointer;font-weight:500;text-decoration:none;display:inline-block;';

        function activateTab(name) {
            tabs.forEach(function(t){ t.classList.remove('admin-tab-active'); t.style.cssText = normalStyle; });
            panels.forEach(function(p){ p.style.display = 'none'; });
            var btn = document.querySelector('#admin-tabs .admin-tab[data-tab="' + name + '"]');
            var panel = document.getElementById('panel-' + name);
            if(btn){ btn.classList.add('admin-tab-active'); btn.style.cssText = activeStyle; }
            if(panel) panel.style.display = 'block';
        }

        tabs.forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                activateTab(btn.getAttribute('data-tab'));
            });
        });

        var urlParams = new URLSearchParams(window.location.search);
        var autoTab = urlParams.get('tab');
        if (autoTab && document.getElementById('panel-' + autoTab)) {
            activateTab(autoTab);
        }

        <?php if ( $product_message ) echo 'activateTab("products");'; ?>
        <?php if ( $jobs_message ) echo 'activateTab("recruitment");'; ?>
        <?php if ( $ticket_message ) echo 'activateTab("support");'; ?>
    })();
    </script>

    <?php
    return ob_get_clean();
} );
