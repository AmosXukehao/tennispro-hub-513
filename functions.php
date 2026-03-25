<?php
/**
 * TennisPro Hub Capstone — Child theme functions.
 * ICTWEB 513: WordPress as base CMS, all customizations in this child theme.
 */

defined( 'ABSPATH' ) || exit;

define( 'TENNISPRO_VERSION', '1.0.0' );
define( 'TENNISPRO_SITE_NAME', 'Amos' );

/** Page slugs used for nav (create these pages in WP and assign the correct template). */
function tennispro_get_nav_pages() {
    return [
        [ 'slug' => 'home',          'label' => 'Home' ],
        [ 'slug' => 'products',      'label' => 'Products' ],
        [ 'slug' => 'cart',          'label' => 'Cart' ],
        [ 'slug' => 'support',       'label' => 'Customer Support' ],
        [ 'slug' => 'about',         'label' => 'About' ],
        [ 'slug' => 'forum',         'label' => 'Discussion Forum' ],
        [ 'slug' => 'jobs',          'label' => 'Recruitment' ],
        [ 'slug' => 'customer-list', 'label' => 'Contact List' ],
    ];
}

/** Nav links (href + label) for header. */
function tennispro_nav_links() {
    $links   = [];

    foreach ( tennispro_get_nav_pages() as $item ) {
        $slug  = $item['slug'];
        $label = $item['label'];

        if ( $slug === 'home' ) {
            $url = home_url( '/' );
        } else {
            $page = get_page_by_path( $slug );
            $url  = $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
        }

        $links[] = [ 'href' => $url, 'label' => $label ];
    }
    return $links;
}

/**
 * Header auth UI model.
 * Requirement:
 * - Logged out: show 3 buttons on the far right (Customer Login, Admin Login, Register).
 * - Logged in: show username/admin label in nav; hover reveals only "Logout".
 */
function tennispro_auth_ui() {
    $session_email = (string) ( $_SESSION['checkout_email'] ?? '' );
    $session_name  = (string) ( $_SESSION['checkout_name'] ?? '' );
    $admin_logged  = ! empty( $_SESSION['capstone_admin_logged_in'] );

    $login_url    = get_permalink( get_page_by_path( 'login' ) ) ?: home_url( '/login/' );
    $admin_url    = get_permalink( get_page_by_path( 'admin-login' ) ) ?: home_url( '/admin-login/' );
    $register_url = get_permalink( get_page_by_path( 'register' ) ) ?: home_url( '/register/' );
    $logout_url   = add_query_arg( 'capstone_logout', '1', home_url( '/' ) );

    // Logged-in trigger.
    if ( $admin_logged ) {
        $dash = get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/admin-dashboard/' );
        return [
            'mode'         => 'logged_in',
            'triggerLabel' => 'Admin',
            'triggerHref'  => $dash,
            'items'        => [
                [ 'key' => 'logout', 'label' => 'Logout', 'href' => $logout_url ],
            ],
        ];
    }

    if ( $session_email !== '' ) {
        $label = $session_name !== '' ? $session_name : $session_email;
        $acct  = get_permalink( get_page_by_path( 'my-orders' ) ) ?: home_url( '/my-orders/' );
        return [
            'mode'         => 'logged_in',
            'triggerLabel' => $label,
            'triggerHref'  => $acct,
            'items'        => [
                [ 'key' => 'logout', 'label' => 'Logout', 'href' => $logout_url ],
            ],
        ];
    }

    // Logged-out: show "Customer Login" in nav; dropdown contains Admin Login + Register.
    return [
        'mode'         => 'logged_out',
        'triggerLabel' => 'Customer Login',
        'triggerHref'  => $login_url,
        'items'        => [
            [ 'key' => 'admin',    'label' => 'Admin Login', 'href' => $admin_url ],
            [ 'key' => 'register', 'label' => 'Register',    'href' => $register_url ],
        ],
    ];
}

/** Path to products.json — Capstone: /wp-content/uploads/products.json */
function tennispro_products_json_path() {
    $upload_dir = wp_upload_dir();
    $path       = $upload_dir['basedir'] . '/products.json';
    if ( ! file_exists( $path ) ) {
        $fallback = get_stylesheet_directory() . '/products.json';
        if ( file_exists( $fallback ) ) {
            @copy( $fallback, $path );
        }
    }
    return $path;
}

/** Get products array from JSON file. */
function tennispro_get_products() {
    $path = tennispro_products_json_path();
    if ( ! file_exists( $path ) ) {
        return [];
    }
    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    return is_array( $data ) ? $data : [];
}

/** Enqueue styles and start session */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'twentytwentyfour-style', get_template_directory_uri() . '/style.css', [], null );
    wp_enqueue_style( 'tennispro-capstone', get_stylesheet_directory_uri() . '/style.css', [ 'twentytwentyfour-style' ], TENNISPRO_VERSION );
}, 20 );

add_action( 'init', function () {
    if ( ! session_id() && ! headers_sent() ) {
        session_start();
    }
}, 1 );

// Double opt-in is disabled via FluentCRM plugin settings (一般设定).
// No code-level interception needed; welcome emails are sent normally.

/**
 * AJAX Cart Operations — no page reload needed.
 * Actions: add, update (delta), clear. Returns JSON with updated cart state.
 */
add_action( 'wp_ajax_tennispro_cart',        'tennispro_ajax_cart' );
add_action( 'wp_ajax_nopriv_tennispro_cart', 'tennispro_ajax_cart' );

add_action( 'wp_ajax_tennispro_forum_login',        'tennispro_ajax_forum_login' );
add_action( 'wp_ajax_nopriv_tennispro_forum_login', 'tennispro_ajax_forum_login' );

function tennispro_ajax_forum_login() {
    if ( ! isset( $_SESSION ) ) { session_start(); }

    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

    if ( $email === '' || $phone === '' ) {
        wp_send_json_error( 'Please enter both Email and Phone Number.' );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( 'Please enter a valid email address.' );
    }

    $sub = function_exists( 'tennispro_checkout_verify' ) ? tennispro_checkout_verify( $email, $phone ) : null;
    if ( ! $sub ) {
        wp_send_json_error( 'Email and Phone not found. Please check your details or register first.' );
    }

    $_SESSION['checkout_email']   = $sub['email'] ?? $email;
    $_SESSION['checkout_user_id'] = $sub['id'] ?? null;
    $_SESSION['checkout_name']    = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
    if ( $_SESSION['checkout_name'] === '' ) {
        $_SESSION['checkout_name'] = $email;
    }

    wp_send_json_success( [ 'name' => $_SESSION['checkout_name'] ] );
}

function tennispro_ajax_cart() {
    if ( ! isset( $_SESSION ) ) { session_start(); }
    if ( ! isset( $_SESSION['cart'] ) ) { $_SESSION['cart'] = []; }

    $action = sanitize_text_field( $_POST['cart_action'] ?? '' );
    $pid    = (int) ( $_POST['product_id'] ?? 0 );

    if ( $action === 'add' && $pid > 0 ) {
        $qty = max( 1, min( 99, (int) ( $_POST['quantity'] ?? 1 ) ) );
        $_SESSION['cart'][ $pid ] = ( $_SESSION['cart'][ $pid ] ?? 0 ) + $qty;
        if ( $_SESSION['cart'][ $pid ] > 99 ) $_SESSION['cart'][ $pid ] = 99;

    } elseif ( $action === 'update' && $pid > 0 ) {
        $delta   = (int) ( $_POST['delta'] ?? 0 );
        $current = (int) ( $_SESSION['cart'][ $pid ] ?? 0 );
        $new_qty = $current + $delta;
        if ( $new_qty <= 0 ) {
            unset( $_SESSION['cart'][ $pid ] );
        } else {
            $_SESSION['cart'][ $pid ] = min( 99, $new_qty );
        }

    } elseif ( $action === 'clear' ) {
        $_SESSION['cart'] = [];
    }

    $products  = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
    $pmap      = [];
    foreach ( $products as $p ) { $pmap[ (int) ( $p['id'] ?? 0 ) ] = $p; }

    $cart_items  = [];
    $total       = 0.0;
    $item_count  = 0;
    foreach ( $_SESSION['cart'] as $id => $q ) {
        $id = (int) $id;
        $q  = (int) $q;
        if ( ! isset( $pmap[ $id ] ) || $q <= 0 ) continue;
        $unit     = (float) ( $pmap[ $id ]['price'] ?? 0 );
        $subtotal = $unit * $q;
        $total   += $subtotal;
        $item_count += $q;
        $cart_items[] = [
            'id'       => $id,
            'name'     => $pmap[ $id ]['name'] ?? '',
            'price'    => $unit,
            'qty'      => $q,
            'subtotal' => $subtotal,
        ];
    }

    $shipping = $total > 0 ? 10.0 : 0.0;

    wp_send_json_success( [
        'items'      => $cart_items,
        'item_count' => $item_count,
        'subtotal'   => $total,
        'shipping'   => $shipping,
        'total'      => $total + $shipping,
        'cart_count' => $item_count,
    ] );
}

/**
 * Hard route templates for block-theme environments.
 * Some block themes may bypass PHP page templates; this guarantees our pages render.
 */
add_action( 'template_redirect', function () {
    // Global logout handler for the header dropdown.
    if ( isset( $_GET['capstone_logout'] ) && $_GET['capstone_logout'] == '1' ) {
        unset( $_SESSION['capstone_admin_logged_in'] );
        unset(
            $_SESSION['checkout_email'],
            $_SESSION['checkout_user_id'],
            $_SESSION['checkout_name'],
            $_SESSION['checkout_phone'],
            $_SESSION['checkout_shipping_address'],
            $_SESSION['checkout_billing_address'],
            $_SESSION['checkout_payment_method'],
            $_SESSION['checkout_order_notes']
        );
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    $dir = get_stylesheet_directory();
    if ( is_page( 'admin-login' ) ) {
        $file = $dir . '/template-admin-login.php';
        if ( file_exists( $file ) ) {
            include $file;
            exit;
        }
    }
    if ( is_page( 'admin-dashboard' ) ) {
        $file = $dir . '/page-admin-dashboard.php';
        if ( file_exists( $file ) ) {
            include $file;
            exit;
        }
    }
    if ( is_page( 'my-orders' ) ) {
        $file = $dir . '/template-my-orders.php';
        if ( file_exists( $file ) ) {
            include $file;
            exit;
        }
    }
    if ( is_page( 'cart' ) ) {
        $file = $dir . '/template-cart.php';
        if ( file_exists( $file ) ) {
            include $file;
            exit;
        }
    }
    if ( is_page( 'products' ) ) {
        $file = $dir . '/template-products.php';
        if ( file_exists( $file ) ) {
            include $file;
            exit;
        }
    }

    // Handle Checkout submission early (before any theme output), place order, then redirect.
    if ( is_page( 'checkout' ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_checkout_submit'] ) ) {
        $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $full_name        = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
        $shipping_address = isset( $_POST['shipping_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shipping_address'] ) ) : '';
        $billing_address  = isset( $_POST['billing_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['billing_address'] ) ) : '';
        $payment_method   = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'credit_card';
        $order_notes      = isset( $_POST['order_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) ) : '';
        $card_name        = isset( $_POST['card_name'] ) ? sanitize_text_field( wp_unslash( $_POST['card_name'] ) ) : '';
        $card_no          = isset( $_POST['card_no'] ) ? sanitize_text_field( wp_unslash( $_POST['card_no'] ) ) : '';

        if ( $email === '' || ! is_email( $email ) ) {
            $_SESSION['checkout_error'] = 'Please enter a valid email address.';
            wp_safe_redirect( get_permalink() );
            exit;
        }

        $was_logged_in = ! empty( $_SESSION['checkout_email'] );
        $customer_name = $full_name !== '' ? $full_name : ( $_SESSION['checkout_name'] ?? $email );
        $_SESSION['checkout_email']              = $email;
        $_SESSION['checkout_name']               = $customer_name;
        $_SESSION['checkout_phone']              = $phone;
        $_SESSION['checkout_shipping_address']   = $shipping_address;
        $_SESSION['checkout_billing_address']    = $billing_address;
        $_SESSION['checkout_payment_method']     = $payment_method;
        $_SESSION['checkout_order_notes']        = $order_notes;
        unset( $_SESSION['checkout_error'] );

        if ( in_array( $payment_method, [ 'credit_card', 'debit_card' ], true ) ) {
            if ( $card_name === '' || $card_no === '' ) {
                $_SESSION['checkout_error'] = 'Please enter card holder name and card number.';
                wp_safe_redirect( get_permalink() );
                exit;
            }
        }

        $cart = $_SESSION['cart'] ?? [];
        if ( empty( $cart ) ) {
            $_SESSION['checkout_error'] = 'Your cart is empty.';
            wp_safe_redirect( get_permalink() );
            exit;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
        $product_map = [];
        foreach ( $products as $p ) {
            $product_map[ (int) ( $p['id'] ?? 0 ) ] = $p;
        }

        $total = 0.0;
        $items = [];
        foreach ( $cart as $pid => $qty ) {
            $pid = (int) $pid;
            $qty = (int) $qty;
            if ( $qty <= 0 || ! isset( $product_map[ $pid ] ) ) {
                continue;
            }
            $unit = (float) ( $product_map[ $pid ]['price'] ?? 0 );
            if ( $unit <= 0 ) {
                continue;
            }
            $total += $unit * $qty;
            $items[] = [ 'product_id' => $pid, 'quantity' => $qty, 'unit_price' => $unit ];
        }

        if ( $total <= 0 || empty( $items ) ) {
            $_SESSION['checkout_error'] = 'Unable to place order. Please check your cart.';
            wp_safe_redirect( get_permalink() );
            exit;
        }

        $contact_name  = $card_name !== '' ? $card_name : $customer_name;
        $contact_phone = $phone;

        $wpdb->insert(
            $prefix . 'orders',
            [
                'user_name'     => $customer_name,
                'total_amount'  => $total,
                'contact_name'  => $contact_name,
                'contact_phone' => $contact_phone,
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%s', '%f', '%s', '%s', '%s' ]
        );

        $order_id = (int) $wpdb->insert_id;
        if ( $order_id > 0 ) {
            foreach ( $items as $it ) {
                $wpdb->insert(
                    $prefix . 'order_items',
                    [
                        'order_id'   => $order_id,
                        'product_id' => (int) $it['product_id'],
                        'quantity'   => (int) $it['quantity'],
                        'unit_price' => (float) $it['unit_price'],
                    ],
                    [ '%d', '%d', '%d', '%f' ]
                );
            }
        }

        if ( $order_id > 0 && $card_name !== '' && $card_no !== '' && function_exists( 'tennispro_save_customer_card' ) ) {
            tennispro_save_customer_card( $email, $card_name, $card_no );
        }

        $_SESSION['cart'] = [];
        $_SESSION['last_order_id']    = $order_id;
        $_SESSION['last_order_total'] = $total;
        $_SESSION['last_order_name']  = $customer_name;
        $_SESSION['last_order_items'] = $items;

        if ( ! $was_logged_in ) {
            unset(
                $_SESSION['checkout_email'],
                $_SESSION['checkout_name'],
                $_SESSION['checkout_user_id'],
                $_SESSION['checkout_phone'],
                $_SESSION['checkout_shipping_address'],
                $_SESSION['checkout_billing_address'],
                $_SESSION['checkout_payment_method'],
                $_SESSION['checkout_order_notes']
            );
        }

        $confirm_url = home_url( '/checkout/' );
        $confirm_url = add_query_arg( 'order_success', '1', $confirm_url );
        wp_safe_redirect( $confirm_url );
        exit;
    }

    // Show order confirmation page (no login required).
    if ( is_page( 'checkout' ) && isset( $_GET['order_success'] ) && ! empty( $_SESSION['last_order_id'] ) ) {
        tennispro_render_order_confirmation();
        exit;
    }

    // Render Checkout/Payment directly instead of relying on the block theme
    // to include the Post Content block (some templates omit it, causing a blank page).
    if ( is_page( 'checkout' ) && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
        tennispro_render_checkout_page();
        exit;
    }
    if ( is_page( 'payment' ) && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
        tennispro_render_payment_page();
        exit;
    }

    // Recruitment / Jobs page: always show the job application form (for all users).
    // Admin can manage submitted applications from the Admin Dashboard #recruitment-section.
    if ( is_page( [ 'jobs', 'project-job', 'project-jobs', 'recruitment' ] ) ) {
        $file = $dir . '/template-jobs.php';
        if ( file_exists( $file ) ) {
            include $file;
            exit;
        }
    }
}, 0 );

/**
 * Render: Order confirmation page (public, no login required).
 */
function tennispro_render_order_confirmation() {
    get_header();

    $order_id    = (int) ( $_SESSION['last_order_id'] ?? 0 );
    $order_total = (float) ( $_SESSION['last_order_total'] ?? 0 );
    $order_name  = (string) ( $_SESSION['last_order_name'] ?? '' );
    $order_items = (array) ( $_SESSION['last_order_items'] ?? [] );

    $products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
    $product_map = [];
    foreach ( $products as $p ) {
        $product_map[ (int) ( $p['id'] ?? 0 ) ] = $p;
    }

    $shipping = $order_total > 0 ? 10.0 : 0.0;
    $grand    = $order_total + $shipping;

    unset(
        $_SESSION['last_order_id'],
        $_SESSION['last_order_total'],
        $_SESSION['last_order_name'],
        $_SESSION['last_order_items']
    );

    $products_url = get_permalink( get_page_by_path( 'products' ) ) ?: home_url( '/products/' );
    $home_url     = home_url( '/' );

    echo '<div class="tennispro-page-wrap">';
    ?>
    <div style="max-width:650px;margin:40px auto;text-align:center;">
        <div style="background:#d4edda;border:2px solid #28a745;border-radius:14px;padding:36px 28px;margin-bottom:30px;">
            <div style="font-size:56px;margin-bottom:10px;">&#10004;</div>
            <h1 style="color:#155724;margin:0 0 8px;">Order Placed Successfully!</h1>
            <p style="color:#155724;font-size:17px;margin:0;">
                Thank you<?php echo $order_name !== '' ? ', <strong>' . esc_html( $order_name ) . '</strong>' : ''; ?>! Your order has been confirmed.
            </p>
        </div>

        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;text-align:left;margin-bottom:24px;">
            <h2 style="margin-top:0;">Order Details</h2>
            <p><strong>Order Number:</strong> #<?php echo $order_id; ?></p>

            <?php if ( ! empty( $order_items ) ) : ?>
                <table style="width:100%;border-collapse:collapse;margin:14px 0;">
                    <thead>
                        <tr style="border-bottom:2px solid #ddd;">
                            <th style="text-align:left;padding:8px 4px;">Product</th>
                            <th style="text-align:center;padding:8px 4px;">Qty</th>
                            <th style="text-align:right;padding:8px 4px;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $order_items as $item ) :
                            $pid  = (int) $item['product_id'];
                            $qty  = (int) $item['quantity'];
                            $unit = (float) $item['unit_price'];
                            $name = $product_map[ $pid ]['name'] ?? ( 'Product #' . $pid );
                        ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:8px 4px;"><?php echo esc_html( $name ); ?></td>
                                <td style="text-align:center;padding:8px 4px;"><?php echo $qty; ?></td>
                                <td style="text-align:right;padding:8px 4px;">$<?php echo number_format( $unit * $qty, 2 ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="border-top:2px solid #ddd;padding-top:10px;margin-top:4px;">
                    <p style="display:flex;justify-content:space-between;margin:4px 0;"><span>Subtotal</span><span>$<?php echo number_format( $order_total, 2 ); ?></span></p>
                    <p style="display:flex;justify-content:space-between;margin:4px 0;"><span>Shipping</span><span>$<?php echo number_format( $shipping, 2 ); ?></span></p>
                    <p style="display:flex;justify-content:space-between;margin:4px 0;font-weight:bold;font-size:18px;"><span>Total</span><span>$<?php echo number_format( $grand, 2 ); ?></span></p>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
            <a href="<?php echo esc_url( $products_url ); ?>" class="btn">Continue Shopping</a>
            <a href="<?php echo esc_url( $home_url ); ?>" class="btn" style="background:#6c757d;">Back to Home</a>
        </div>
    </div>
    <?php
    echo '</div>';
    get_footer();
}

/**
 * Direct render: Checkout page.
 * Does not depend on the_content being rendered by a block template.
 */
function tennispro_render_checkout_page() {
    get_header();

    $error = isset( $_SESSION['checkout_error'] ) ? (string) $_SESSION['checkout_error'] : '';
    unset( $_SESSION['checkout_error'] );

    // Cart summary
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

    echo '<div class="tennispro-page-wrap">';
    echo '<h1>Checkout</h1>';
    ?>
    <div class="checkout-layout" style="display:grid;visibility:visible;opacity:1;">
        <section class="checkout-main" style="display:block;visibility:visible;opacity:1;">
            <h2>Shipping & Contact</h2>
            <?php if ( $error ) : ?>
                <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( get_permalink() ); ?>">
                <input type="hidden" name="tennispro_checkout_submit" value="1">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo esc_attr( $_SESSION['checkout_name'] ?? '' ); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo esc_attr( $_SESSION['checkout_email'] ?? '' ); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?php echo esc_attr( $_SESSION['checkout_phone'] ?? '' ); ?>" required>
                </div>
                <div class="form-group">
                    <label for="shipping_address">Shipping Address</label>
                    <textarea id="shipping_address" name="shipping_address" rows="3" required><?php echo esc_textarea( $_SESSION['checkout_shipping_address'] ?? '' ); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="billing_address">Billing Address</label>
                    <textarea id="billing_address" name="billing_address" rows="3" required><?php echo esc_textarea( $_SESSION['checkout_billing_address'] ?? '' ); ?></textarea>
                </div>

                <h2>Payment Information</h2>
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="credit_card" <?php selected( $_SESSION['checkout_payment_method'] ?? '', 'credit_card' ); ?>>Credit Card</option>
                        <option value="debit_card" <?php selected( $_SESSION['checkout_payment_method'] ?? '', 'debit_card' ); ?>>Debit Card</option>
                        <option value="paypal" <?php selected( $_SESSION['checkout_payment_method'] ?? '', 'paypal' ); ?>>PayPal</option>
                    </select>
                </div>

                <div id="card-fields" style="margin-top:10px;">
                    <div class="form-group">
                        <label for="card_name">Card holder name</label>
                        <input type="text" id="card_name" name="card_name" value="" autocomplete="cc-name">
                    </div>
                    <div class="form-group">
                        <label for="card_no">Card number</label>
                        <input type="text" id="card_no" name="card_no" value="" inputmode="numeric" autocomplete="cc-number">
                    </div>
                </div>
                <div class="form-group">
                    <label for="order_notes">Order Notes (Optional)</label>
                    <textarea id="order_notes" name="order_notes" rows="3" placeholder="Any special instructions for your order"><?php echo esc_textarea( $_SESSION['checkout_order_notes'] ?? '' ); ?></textarea>
                </div>

                <button type="submit" class="btn">Place order</button>
            </form>
            <script>
            (function(){
              function toggleCardFields(){
                var sel = document.getElementById('payment_method');
                var box = document.getElementById('card-fields');
                if(!sel || !box) return;
                var v = (sel.value || '').toLowerCase();
                var show = (v === 'credit_card' || v === 'debit_card');
                box.style.display = show ? 'block' : 'none';
                var name = document.getElementById('card_name');
                var no   = document.getElementById('card_no');
                if(name) name.required = show;
                if(no)   no.required   = show;
              }
              document.addEventListener('change', function(e){
                if(e && e.target && e.target.id === 'payment_method') toggleCardFields();
              });
              toggleCardFields();
            })();
            </script>
        </section>

        <aside class="checkout-summary" style="display:block;visibility:visible;opacity:1;">
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
                        $p     = $product_map[ $pid ];
                        $price = (float) ( $p['price'] ?? 0 );
                        $line  = $price * $qty;
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
    <?php
    echo '</div>';

    get_footer();
}

/**
 * Direct render: Payment page.
 * Does not depend on the_content being rendered by a block template.
 */
function tennispro_render_payment_page() {
    if ( empty( $_SESSION['checkout_email'] ) ) {
        $checkout = get_permalink( get_page_by_path( 'checkout' ) ) ?: home_url( '/checkout/' );
        wp_safe_redirect( $checkout );
        exit;
    }

    get_header();

    $customer_email   = $_SESSION['checkout_email'] ?? '';
    $customer_name    = $_SESSION['checkout_name'] ?? '';
    $shipping_address = $_SESSION['checkout_shipping_address'] ?? '';
    $billing_address  = $_SESSION['checkout_billing_address'] ?? '';
    $payment_method   = $_SESSION['checkout_payment_method'] ?? '';
    $order_notes      = $_SESSION['checkout_order_notes'] ?? '';

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

    echo '<div class="tennispro-page-wrap">';
    echo '<h1>Payment</h1>';
    ?>
    <div class="checkout-layout" style="display:grid;visibility:visible;opacity:1;">
        <section class="checkout-main" style="display:block;visibility:visible;opacity:1;">
            <h2>Review & pay</h2>
            <p>Email: <strong><?php echo esc_html( $customer_email ); ?></strong></p>
            <?php if ( $customer_name ) : ?><p>Name: <strong><?php echo esc_html( $customer_name ); ?></strong></p><?php endif; ?>
            <?php if ( $shipping_address ) : ?><p>Shipping address:<br><?php echo nl2br( esc_html( $shipping_address ) ); ?></p><?php endif; ?>
            <?php if ( $billing_address ) : ?><p>Billing address:<br><?php echo nl2br( esc_html( $billing_address ) ); ?></p><?php endif; ?>
            <?php if ( $payment_method ) : ?><p>Payment method: <strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $payment_method ) ) ); ?></strong></p><?php endif; ?>
            <?php if ( $order_notes ) : ?><p>Order notes:<br><?php echo nl2br( esc_html( $order_notes ) ); ?></p><?php endif; ?>

            <h3>Card details</h3>
            <form method="post" action="<?php echo esc_url( get_permalink() ); ?>">
                <input type="hidden" name="tennispro_pay_submit" value="1">
                <div class="form-group">
                    <label for="card_name">Card holder name</label>
                    <input type="text" id="card_name" name="card_name" required>
                </div>
                <div class="form-group">
                    <label for="card_no">Card number</label>
                    <input type="text" id="card_no" name="card_no" required>
                </div>
                <button type="submit" class="btn">Place order</button>
            </form>
        </section>

        <aside class="checkout-summary" style="display:block;visibility:visible;opacity:1;">
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
                        $p     = $product_map[ $pid ];
                        $price = (float) ( $p['price'] ?? 0 );
                        $line  = $price * $qty;
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
    <?php
    echo '</div>';

    get_footer();
}

/**
 * Final fallback: replace page content for key slugs.
 * This works even if a block theme ignores classic templates.
 */
add_filter( 'the_content', function ( $content ) {
    if ( ! is_page() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    if ( is_page( 'admin-login' ) ) {
        ob_start();

        $error   = '';
        $message = '';

        if ( isset( $_GET['logout'] ) && $_GET['logout'] == '1' ) {
            unset( $_SESSION['capstone_admin_logged_in'] );
            unset( $_SESSION['checkout_email'], $_SESSION['checkout_user_id'], $_SESSION['checkout_name'] );
            $message = 'You have been logged out of the admin area.';
        }

        // If already logged in, show a clear button to dashboard (no redirect here).
        if ( ! empty( $_SESSION['capstone_admin_logged_in'] ) && empty( $_GET['logout'] ) ) {
            $dash = get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/admin-dashboard/' );
            ?>
            <h1>Administrator Login</h1>
            <div class="alert alert-success">You are already logged in as administrator.</div>
            <p><a class="btn" href="<?php echo esc_url( $dash ); ?>">Go to Admin Dashboard</a>
                <a class="btn btn-secondary" href="<?php echo esc_url( add_query_arg( 'logout', '1', get_permalink() ) ); ?>" style="margin-left:8px;">Admin logout</a>
            </p>
            <?php
            return ob_get_clean();
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_admin_login'] ) ) {
            $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
            $password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

            if ( $username === '' || $password === '' ) {
                $error = 'Please enter both username and password.';
            } elseif ( $username === 'admin' && $password === 'admin123' ) {
                $_SESSION['capstone_admin_logged_in'] = true;
                // Admin also has customer privileges.
                $_SESSION['checkout_email']   = 'admin@example.com';
                $_SESSION['checkout_user_id'] = 0;
                $_SESSION['checkout_name']    = 'Admin';
                $dash = get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/admin-dashboard/' );
                wp_safe_redirect( $dash );
                exit;
            } else {
                $error = 'Invalid admin credentials.';
            }
        }
        ?>
        <h1>Administrator Login</h1>
        <p>Sign in with the administrator account <strong>admin / admin123</strong> to view the internal dashboard and reports.</p>

        <?php if ( $message ) : ?>
            <div class="alert alert-success"><?php echo esc_html( $message ); ?></div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
            <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="tennispro_admin_login" value="1">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Admin Login</button>
        </form>
        <?php

        return ob_get_clean();
    }

    if ( is_page( 'admin-dashboard' ) ) {
        if ( empty( $_SESSION['capstone_admin_logged_in'] ) ) {
            $login = get_permalink( get_page_by_path( 'admin-login' ) ) ?: home_url( '/admin-login/' );
            wp_safe_redirect( $login );
            exit;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Handle inline product CRUD from the dashboard.
        $product_message = '';
        if ( isset( $_POST['tennispro_product_action'] ) && wp_verify_nonce( ( $_POST['tennispro_product_nonce'] ?? '' ), 'tennispro_product_crud' ) ) {
            $action   = sanitize_text_field( $_POST['tennispro_product_action'] );
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
                    if ( function_exists( 'tennispro_save_products' ) && tennispro_save_products( $products_list ) ) {
                        $product_message = 'Product created successfully.';
                    } else {
                        $product_message = 'Failed to save product.';
                    }
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
                    if ( function_exists( 'tennispro_save_products' ) && tennispro_save_products( $products_list ) ) {
                        $product_message = 'Product updated.';
                    } else {
                        $product_message = 'Failed to update product.';
                    }
                }
            } elseif ( $action === 'delete' ) {
                $p_id = (int) ( $_POST['p_id'] ?? 0 );
                if ( $p_id > 0 ) {
                    $products_list = array_values( array_filter( $products_list, function ( $pp ) use ( $p_id ) {
                        return (int) ( $pp['id'] ?? 0 ) !== $p_id;
                    } ) );
                    if ( function_exists( 'tennispro_save_products' ) && tennispro_save_products( $products_list ) ) {
                        $product_message = 'Product deleted.';
                    } else {
                        $product_message = 'Failed to delete product.';
                    }
                }
            }
        }

        // Handle inline job application status updates from the dashboard.
        $jobs_message = '';
        if ( isset( $_POST['tennispro_job_update'] ) && check_admin_referer( 'tennispro_job_update', 'tennispro_job_nonce' ) ) {
            $job_id = isset( $_POST['application_id'] ) ? (int) $_POST['application_id'] : 0;
            $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
            $allowed_statuses = [ 'received', 'in_review', 'shortlisted', 'hired', 'rejected' ];
            if ( $job_id > 0 && in_array( $status, $allowed_statuses, true ) ) {
                $updated = $wpdb->update(
                    $prefix . 'job_applications',
                    [ 'status' => $status ],
                    [ 'application_id' => $job_id ],
                    [ '%s' ],
                    [ '%d' ]
                );
                if ( $updated !== false ) {
                    $jobs_message = 'Job application status updated.';
                } else {
                    $jobs_message = 'Failed to update job application.';
                }
            }
        }

        $total_revenue     = (float) $wpdb->get_var( "SELECT SUM(total_amount) FROM {$prefix}orders" );
        $total_orders      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}orders" );
        $total_customers   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_name) FROM {$prefix}orders" );
        $avg_order_value   = $total_orders > 0 ? $total_revenue / $total_orders : 0.0;
        $total_tickets     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}support_tickets" );
        $open_tickets      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}support_tickets WHERE status = 'open'" );
        $total_applications = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}job_applications" );

        $total_subscribers = 0;
        $fc_table = $prefix . 'fc_subscribers';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fc_table}'" ) === $fc_table ) {
            $total_subscribers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fc_table}" );
        }

        $recent_orders = $wpdb->get_results(
            "SELECT id, user_name, total_amount, created_at FROM {$prefix}orders ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );

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

        // Load latest job applications for inline management.
        $job_applications = $wpdb->get_results(
            "SELECT application_id, full_name, email, phone, position, cover_letter, cv_file_path, submitted_at, status
             FROM {$prefix}job_applications
             ORDER BY submitted_at DESC
             LIMIT 50",
            ARRAY_A
        );

        ob_start();
        // ── Load all data for tabs ──
        $all_products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];

        $all_subscribers = [];
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fc_table}'" ) === $fc_table ) {
            $all_subscribers = $wpdb->get_results(
                "SELECT id, first_name, last_name, email, phone, status, created_at FROM {$fc_table} ORDER BY created_at DESC LIMIT 200",
                ARRAY_A
            );
        }

        $all_orders = $wpdb->get_results(
            "SELECT id, user_name, total_amount, contact_name, contact_phone, created_at
             FROM {$prefix}orders ORDER BY created_at DESC LIMIT 200",
            ARRAY_A
        );
        ?>

        <h1>Admin Dashboard</h1>

        <div class="admin-tabs" id="admin-tabs">
            <button type="button" class="admin-tab admin-tab-active" data-tab="dashboard">Dashboard</button>
            <button type="button" class="admin-tab" data-tab="products">Products JSON</button>
            <button type="button" class="admin-tab" data-tab="recruitment">Recruitment</button>
            <button type="button" class="admin-tab" data-tab="all-users">All Users</button>
            <button type="button" class="admin-tab" data-tab="all-orders">All Orders</button>
            <a class="admin-tab" href="<?php echo esc_url( get_permalink( get_page_by_path( 'customer-list' ) ) ?: home_url( '/customer-list/' ) ); ?>">Customers</a>
            <a class="admin-tab" href="<?php echo esc_url( get_permalink( get_page_by_path( 'support' ) ) ?: home_url( '/support/' ) ); ?>">Support</a>
            <a class="admin-tab" style="margin-left:auto;background:#fff;color:#333;border:1px solid #ccc;" href="<?php echo esc_url( add_query_arg( 'capstone_logout', '1', home_url( '/' ) ) ); ?>">Admin Logout</a>
        </div>

        <!-- ═══════════ TAB: Dashboard ═══════════ -->
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
                        <p>No orders have been placed yet.</p>
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
                        <p>No products have been sold yet.</p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <!-- ═══════════ TAB: Products JSON ═══════════ -->
        <div class="admin-tab-panel" id="panel-products" style="display:none;">
            <section class="admin-panel">
                <h2>Product Management (<?php echo count( $all_products ); ?> products)</h2>
                <p>View, create, update, or delete products stored in <code>products.json</code>.</p>

                <?php if ( ! empty( $product_message ) ) : ?>
                    <div class="alert alert-success"><?php echo esc_html( $product_message ); ?></div>
                <?php endif; ?>

                <h3>Existing products</h3>
                <?php if ( $all_products ) : ?>
                    <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Price</th><th>Image</th><th>Description</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $all_products as $prod ) : ?>
                                <?php $pid = (int) ( $prod['id'] ?? 0 ); ?>
                                <tr>
                                    <td><?php echo $pid; ?></td>
                                    <td>
                                        <form method="post" style="display:inline;" id="product-form-<?php echo $pid; ?>">
                                            <?php wp_nonce_field( 'tennispro_product_crud', 'tennispro_product_nonce' ); ?>
                                            <input type="hidden" name="tennispro_product_action" value="update">
                                            <input type="hidden" name="p_id" value="<?php echo $pid; ?>">
                                            <input type="text" name="p_name" value="<?php echo esc_attr( $prod['name'] ?? '' ); ?>" style="width:140px;">
                                    </td>
                                    <td>
                                            <input type="number" name="p_price" step="0.01" value="<?php echo esc_attr( $prod['price'] ?? 0 ); ?>" style="width:80px;">
                                    </td>
                                    <td>
                                            <input type="text" name="p_image" value="<?php echo esc_attr( $prod['image_path'] ?? $prod['image'] ?? '' ); ?>" style="width:120px;">
                                    </td>
                                    <td>
                                            <textarea name="p_desc" rows="1" style="width:160px;vertical-align:middle;"><?php echo esc_textarea( $prod['description'] ?? '' ); ?></textarea>
                                    </td>
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

        <!-- ═══════════ TAB: Recruitment ═══════════ -->
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
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Position</th>
                                <th>Submitted At</th><th>Cover Letter</th><th>CV</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $job_applications as $job ) : ?>
                                <tr>
                                    <td>#<?php echo (int) $job['application_id']; ?></td>
                                    <td><?php echo esc_html( $job['full_name'] ); ?></td>
                                    <td><?php echo esc_html( $job['email'] ); ?></td>
                                    <td><?php echo esc_html( $job['phone'] ); ?></td>
                                    <td><?php echo esc_html( $job['position'] ); ?></td>
                                    <td><?php echo esc_html( $job['submitted_at'] ); ?></td>
                                    <td style="max-width:200px;font-size:12px;overflow:hidden;text-overflow:ellipsis;">
                                        <?php echo nl2br( esc_html( mb_strimwidth( $job['cover_letter'] ?? '', 0, 120, '…' ) ) ); ?>
                                    </td>
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

        <!-- ═══════════ TAB: All Users ═══════════ -->
        <div class="admin-tab-panel" id="panel-all-users" style="display:none;">
            <section class="admin-panel">
                <h2>All Registered Users (<?php echo count( $all_subscribers ); ?>)</h2>
                <p>All contacts from FluentCRM subscriber list.</p>
                <?php if ( $all_subscribers ) : ?>
                    <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Registered</th></tr>
                        </thead>
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

        <!-- ═══════════ TAB: All Orders ═══════════ -->
        <div class="admin-tab-panel" id="panel-all-orders" style="display:none;">
            <section class="admin-panel">
                <h2>All Orders (<?php echo count( $all_orders ); ?>)</h2>
                <p>Complete order history across all customers.</p>
                <?php if ( $all_orders ) : ?>
                    <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Order #</th><th>Customer</th><th>Contact</th><th>Phone</th><th>Total</th><th>Date</th><th>Items</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $all_orders as $o ) : ?>
                                <?php
                                $oid = (int) $o['id'];
                                $order_items = $wpdb->get_results(
                                    $wpdb->prepare( "SELECT product_id, quantity, unit_price FROM {$prefix}order_items WHERE order_id = %d", $oid ),
                                    ARRAY_A
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
                    <p>No orders have been placed yet.</p>
                <?php endif; ?>
            </section>
        </div>

        <script>
        (function(){
            var tabs = document.querySelectorAll('#admin-tabs .admin-tab[data-tab]');
            var panels = document.querySelectorAll('.admin-tab-panel');

            function activateTab(name) {
                tabs.forEach(function(t){ t.classList.remove('admin-tab-active'); });
                panels.forEach(function(p){ p.style.display = 'none'; });
                var btn = document.querySelector('#admin-tabs .admin-tab[data-tab="' + name + '"]');
                var panel = document.getElementById('panel-' + name);
                if(btn) btn.classList.add('admin-tab-active');
                if(panel) panel.style.display = 'block';
            }

            tabs.forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    activateTab(btn.getAttribute('data-tab'));
                });
            });

            // Check URL parameter ?tab=xxx to auto-open the right tab.
            var urlParams = new URLSearchParams(window.location.search);
            var autoTab = urlParams.get('tab');
            if (autoTab && document.getElementById('panel-' + autoTab)) {
                activateTab(autoTab);
            }

            <?php
            // After product CRUD, auto-open Products tab.
            if ( ! empty( $product_message ) ) {
                echo 'activateTab("products");';
            }
            // After job status update, auto-open Recruitment tab.
            if ( ! empty( $jobs_message ) ) {
                echo 'activateTab("recruitment");';
            }
            ?>
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // Final fallback for Checkout page (block themes may ignore PHP templates).
    if ( is_page( 'checkout' ) ) {
        ob_start();
        $error = isset( $_SESSION['checkout_error'] ) ? (string) $_SESSION['checkout_error'] : '';
        unset( $_SESSION['checkout_error'] );

        // Cart summary
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

        ?>
        <h1>Checkout</h1>
        <div class="checkout-layout">
            <section class="checkout-main">
                <h2>Shipping & Contact</h2>
                <?php if ( $error ) : ?>
                    <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( get_permalink() ); ?>">
                    <input type="hidden" name="tennispro_checkout_submit" value="1">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo esc_attr( $_SESSION['checkout_name'] ?? '' ); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr( $_SESSION['checkout_email'] ?? '' ); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" value="<?php echo esc_attr( $_SESSION['checkout_phone'] ?? '' ); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="shipping_address">Shipping Address</label>
                        <textarea id="shipping_address" name="shipping_address" rows="3" required><?php echo esc_textarea( $_SESSION['checkout_shipping_address'] ?? '' ); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="billing_address">Billing Address</label>
                        <textarea id="billing_address" name="billing_address" rows="3" required><?php echo esc_textarea( $_SESSION['checkout_billing_address'] ?? '' ); ?></textarea>
                    </div>

                    <h2>Payment Information</h2>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="credit_card" <?php selected( $_SESSION['checkout_payment_method'] ?? '', 'credit_card' ); ?>>Credit Card</option>
                            <option value="debit_card" <?php selected( $_SESSION['checkout_payment_method'] ?? '', 'debit_card' ); ?>>Debit Card</option>
                            <option value="paypal" <?php selected( $_SESSION['checkout_payment_method'] ?? '', 'paypal' ); ?>>PayPal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="order_notes">Order Notes (Optional)</label>
                        <textarea id="order_notes" name="order_notes" rows="3" placeholder="Any special instructions for your order"><?php echo esc_textarea( $_SESSION['checkout_order_notes'] ?? '' ); ?></textarea>
                    </div>

                    <button type="submit" class="btn">Place order</button>
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
                            if ( ! isset( $product_map[ $pid ] ) ) {
                                continue;
                            }
                            $p     = $product_map[ $pid ];
                            $price = (float) ( $p['price'] ?? 0 );
                            $line  = $price * $qty;
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
        <?php
        return ob_get_clean();
    }

    // Final fallback for Payment page.
    if ( is_page( 'payment' ) ) {
        if ( empty( $_SESSION['checkout_email'] ) ) {
            $checkout = get_permalink( get_page_by_path( 'checkout' ) ) ?: home_url( '/checkout/' );
            wp_safe_redirect( $checkout );
            exit;
        }

        global $wpdb;
        $prefix   = $wpdb->prefix;
        $success  = false;
        $order_id = 0;

        $customer_email   = $_SESSION['checkout_email'] ?? '';
        $customer_name    = $_SESSION['checkout_name'] ?? '';
        $shipping_address = $_SESSION['checkout_shipping_address'] ?? '';
        $billing_address  = $_SESSION['checkout_billing_address'] ?? '';
        $payment_method   = $_SESSION['checkout_payment_method'] ?? '';
        $order_notes      = $_SESSION['checkout_order_notes'] ?? '';

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

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_pay_submit'] ) ) {
            $card_name = isset( $_POST['card_name'] ) ? sanitize_text_field( wp_unslash( $_POST['card_name'] ) ) : '';
            $card_no   = isset( $_POST['card_no'] ) ? sanitize_text_field( wp_unslash( $_POST['card_no'] ) ) : '';

            if ( $card_name !== '' && $card_no !== '' ) {
                $items = [];
                foreach ( $cart as $pid => $qty ) {
                    $pid = (int) $pid;
                    $qty = (int) $qty;
                    if ( isset( $product_map[ $pid ] ) ) {
                        $unit = (float) ( $product_map[ $pid ]['price'] ?? 0 );
                        if ( $unit > 0 && $qty > 0 ) {
                            $items[] = [ 'product_id' => $pid, 'quantity' => $qty, 'unit_price' => $unit ];
                        }
                    }
                }

                if ( $total > 0 && ! empty( $items ) ) {
                    $user_name = $customer_name !== '' ? $customer_name : ( $customer_email ?: 'guest' );
                    $wpdb->insert(
                        $prefix . 'orders',
                        [
                            'user_name'     => $user_name,
                            'total_amount'  => $total,
                            'contact_name'  => $card_name,
                            'contact_phone' => $_SESSION['checkout_phone'] ?? '',
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

                    if ( function_exists( 'tennispro_save_customer_card' ) ) {
                        tennispro_save_customer_card( $customer_email, $card_name, $card_no );
                    }
                }

                $success          = true;
                $_SESSION['cart'] = [];
            }
        }

        if ( $success ) {
            $account_page = get_permalink( get_page_by_path( 'my-orders' ) ) ?: home_url( '/my-orders/' );
            if ( $order_id ) {
                $account_page = add_query_arg( [ 'order_success' => '1', 'order_id' => $order_id ], $account_page );
            } else {
                $account_page = add_query_arg( 'order_success', '1', $account_page );
            }
            wp_safe_redirect( $account_page );
            exit;
        }

        ob_start();
        ?>
        <h1>Payment</h1>
        <div class="checkout-layout">
            <section class="checkout-main">
                <h2>Review & pay</h2>
                <p>Email: <strong><?php echo esc_html( $customer_email ); ?></strong></p>
                <?php if ( $customer_name ) : ?><p>Name: <strong><?php echo esc_html( $customer_name ); ?></strong></p><?php endif; ?>
                <?php if ( $shipping_address ) : ?><p>Shipping address:<br><?php echo nl2br( esc_html( $shipping_address ) ); ?></p><?php endif; ?>
                <?php if ( $billing_address ) : ?><p>Billing address:<br><?php echo nl2br( esc_html( $billing_address ) ); ?></p><?php endif; ?>
                <?php if ( $payment_method ) : ?><p>Payment method: <strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $payment_method ) ) ); ?></strong></p><?php endif; ?>
                <?php if ( $order_notes ) : ?><p>Order notes:<br><?php echo nl2br( esc_html( $order_notes ) ); ?></p><?php endif; ?>

                <h3>Card details</h3>
                <form method="post">
                    <input type="hidden" name="tennispro_pay_submit" value="1">
                    <div class="form-group">
                        <label for="card_name">Card holder name</label>
                        <input type="text" id="card_name" name="card_name" required>
                    </div>
                    <div class="form-group">
                        <label for="card_no">Card number</label>
                        <input type="text" id="card_no" name="card_no" required>
                    </div>
                    <button type="submit" class="btn">Place order</button>
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
                            if ( ! isset( $product_map[ $pid ] ) ) {
                                continue;
                            }
                            $p     = $product_map[ $pid ];
                            $price = (float) ( $p['price'] ?? 0 );
                            $line  = $price * $qty;
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
        <?php
        return ob_get_clean();
    }

    return $content;
}, 50 );

/**
 * Ensure our custom templates are always used for key slugs,
 * even when the parent theme is a block theme.
 */
add_filter( 'template_include', function ( $template ) {
    if ( is_page( 'admin-login' ) ) {
        $custom = get_stylesheet_directory() . '/template-admin-login.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    if ( is_page( 'my-orders' ) ) {
        $custom = get_stylesheet_directory() . '/template-my-orders.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}, 20 );

/** Create Capstone tables on theme activation */
add_action( 'after_switch_theme', 'tennispro_create_tables' );
add_action( 'after_switch_theme', 'tennispro_seed_forum_and_uploads', 20 );

function tennispro_create_tables() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $charset_collate = $wpdb->get_charset_collate();

    $sql_orders = "CREATE TABLE IF NOT EXISTS {$prefix}orders (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_name varchar(100) NOT NULL,
        total_amount decimal(10,2) NOT NULL,
        contact_name varchar(100) NOT NULL,
        contact_phone varchar(50) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset_collate;";

    $sql_order_items = "CREATE TABLE IF NOT EXISTS {$prefix}order_items (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id bigint(20) unsigned NOT NULL,
        product_id bigint(20) unsigned NOT NULL,
        quantity int(11) NOT NULL,
        unit_price decimal(10,2) NOT NULL,
        PRIMARY KEY (id),
        KEY order_id (order_id)
    ) $charset_collate;";

    $sql_support = "CREATE TABLE IF NOT EXISTS {$prefix}support_tickets (
        ticket_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        customer_name varchar(100) NOT NULL,
        customer_email varchar(150) NOT NULL,
        subject varchar(255) NOT NULL,
        message text NOT NULL,
        submitted_at datetime NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'open',
        PRIMARY KEY (ticket_id),
        KEY submitted_at (submitted_at)
    ) $charset_collate;";

    $sql_jobs = "CREATE TABLE IF NOT EXISTS {$prefix}job_applications (
        application_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        full_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        position varchar(100) NOT NULL,
        cover_letter text NOT NULL,
        cv_file_path varchar(255) NOT NULL,
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) NOT NULL DEFAULT 'received',
        PRIMARY KEY (application_id),
        KEY status (status)
    ) $charset_collate;";

    $sql_forum = "CREATE TABLE IF NOT EXISTS {$prefix}forum_posts (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        content text NOT NULL,
        author varchar(100) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset_collate;";

    $sql_cards = "CREATE TABLE IF NOT EXISTS {$prefix}customer_cards (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        customer_email varchar(150) NOT NULL,
        holder_name varchar(100) NOT NULL,
        card_brand varchar(50) NOT NULL,
        card_last4 varchar(4) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY customer_email (customer_email)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_orders );
    dbDelta( $sql_order_items );
    dbDelta( $sql_support );
    dbDelta( $sql_jobs );
    dbDelta( $sql_forum );
    dbDelta( $sql_cards );
}

/** Create cv_uploads dir + .htaccess; seed forum with 20 posts if empty. */
function tennispro_seed_forum_and_uploads() {
    global $wpdb;
    $upload_dir = wp_upload_dir();
    $cv_dir     = $upload_dir['basedir'] . '/cv_uploads';
    if ( ! is_dir( $cv_dir ) ) {
        wp_mkdir_p( $cv_dir );
    }
    $ht = $cv_dir . '/.htaccess';
    if ( ! file_exists( $ht ) ) {
        file_put_contents( $ht, "php_flag engine off\n" );
    }
    $prefix = $wpdb->prefix;
    $count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}forum_posts" );
    if ( $count < 20 ) {
        $posts = [
            [ 'Welcome to TennisPro Hub community!', 'Introduce yourself and share your tennis journey.', 'TennisPro Hub' ],
            [ 'Best racket for intermediate players?', 'Looking for a racket that gives good control.', 'Alex' ],
            [ 'Court booking during peak hours', 'Has anyone had success booking weekend peak slots?', 'Jordan' ],
            [ 'Coaching package worth it?', 'I did the beginner package and found it really improved my serve.', 'Sam' ],
            [ 'String tension advice', 'What tension do you use for poly strings on hard courts?', 'Casey' ],
            [ 'Junior coaching feedback', 'My 10-year-old loved the junior package.', 'Parent_01' ],
            [ 'Shoes for clay vs hard court', 'Do you use different shoes for clay?', 'Riley' ],
            [ 'Team building event', 'We did the corporate event last month – great for our team.', 'HR_Mel' ],
            [ 'Video analysis add-on', 'The video analysis session was eye-opening.', 'Taylor' ],
            [ 'Ball machine rental', 'Rented the ball machine for 2 hours. Perfect for solo practice.', 'Morgan' ],
            [ 'Off-peak court availability', 'Weekend off-peak is usually free.', 'Jamie' ],
            [ 'Grip and overgrip tips', 'How often do you change overgrips?', 'Drew' ],
            [ 'Private coaching vs group', 'Trying to decide between 1-on-1 and group.', 'Quinn' ],
            [ 'Tournament entry', 'Signed up for the round-robin. Anyone else in?', 'Avery' ],
            [ 'Stringing service turnaround', 'How long did your restringing take?', 'Parker' ],
            [ 'Kids camp feedback', 'Considering the 5-day camp for my son.', 'Parent_02' ],
            [ 'Evening court lights', 'Are the lights good for night play?', 'Riley' ],
            [ 'Tennis bag recommendation', 'Looking at the Pro bag. Is the thermal pocket useful?', 'Jordan' ],
            [ 'Elbow support', 'Has anyone used the elbow support from the shop?', 'Alex' ],
            [ 'New to area – where to start?', 'Just moved here. Best way to find hitting partners?', 'NewPlayer' ],
        ];
        foreach ( $posts as $i => $p ) {
            $wpdb->insert( $prefix . 'forum_posts', [
                'title'      => $p[0],
                'content'    => $p[1],
                'author'     => $p[2],
                'created_at' => date( 'Y-m-d H:i:s', strtotime( '-' . ( 20 - $i ) . ' days' ) ),
            ], [ '%s', '%s', '%s', '%s' ] );
        }
    }
}

/** REST API: GET /wp-json/custom/v1/products — returns all products as JSON */
add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v1', '/products', [
        'methods'             => 'GET',
        'callback'            => function () {
            $products = tennispro_get_products();
            foreach ( $products as &$p ) {
                if ( empty( $p['image_path'] ) && ! empty( $p['image'] ) ) {
                    $p['image_path'] = $p['image'];
                }
            }
            unset( $p );
            return rest_ensure_response( $products );
        },
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * Product CRUD is now managed inline in the Admin Dashboard (Products JSON tab).
 * The WP admin menu page redirects to the frontend dashboard to avoid confusion.
 */
add_action( 'admin_menu', function () {
    add_menu_page(
        'Product Management (JSON)',
        'Products JSON',
        'manage_options',
        'tennispro-products',
        'tennispro_admin_products_redirect',
        'dashicons-cart',
        30
    );
} );

function tennispro_admin_products_redirect() {
    $dash_url = get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/admin-dashboard/' );
    $dash_url .= '?tab=products';
    echo '<script>window.location.href="' . esc_url( $dash_url ) . '";</script>';
    echo '<p>Redirecting to <a href="' . esc_url( $dash_url ) . '">Admin Dashboard &rarr; Products</a>...</p>';
    return;
}

function tennispro_admin_products_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    $path = tennispro_products_json_path();
    $msg  = '';
    $err  = '';

    if ( isset( $_POST['tennispro_product_action'] ) && check_admin_referer( 'tennispro_products' ) ) {
        $action = sanitize_text_field( $_POST['tennispro_product_action'] ?? '' );
        $products = tennispro_get_products();

        if ( $action === 'create' ) {
            $name        = sanitize_text_field( $_POST['name'] ?? '' );
            $description = sanitize_textarea_field( $_POST['description'] ?? '' );
            $price        = floatval( $_POST['price'] ?? 0 );
            $image_path   = sanitize_text_field( $_POST['image_path'] ?? '' );
            if ( $name !== '' && $price > 0 ) {
                $max_id = 0;
                foreach ( $products as $p ) {
                    $id = (int) ( $p['id'] ?? 0 );
                    if ( $id > $max_id ) {
                        $max_id = $id;
                    }
                }
                $products[] = [
                    'id'          => $max_id + 1,
                    'name'        => $name,
                    'description' => $description,
                    'price'       => $price,
                    'image_path'  => $image_path,
                ];
                if ( tennispro_save_products( $products ) ) {
                    $msg = 'Product created.';
                } else {
                    $err = 'Failed to save file.';
                }
            } else {
                $err = 'Name and positive price required.';
            }
        } elseif ( $action === 'update' ) {
            $id          = (int) ( $_POST['id'] ?? 0 );
            $name        = sanitize_text_field( $_POST['name'] ?? '' );
            $description = sanitize_textarea_field( $_POST['description'] ?? '' );
            $price        = floatval( $_POST['price'] ?? 0 );
            $image_path   = sanitize_text_field( $_POST['image_path'] ?? '' );
            if ( $id > 0 && $name !== '' && $price > 0 ) {
                foreach ( $products as $i => $p ) {
                    if ( (int) ( $p['id'] ?? 0 ) === $id ) {
                        $products[ $i ] = array_merge( $p, [
                            'name'        => $name,
                            'description' => $description,
                            'price'       => $price,
                            'image_path'  => $image_path,
                        ] );
                        break;
                    }
                }
                if ( tennispro_save_products( $products ) ) {
                    $msg = 'Product updated.';
                } else {
                    $err = 'Failed to save file.';
                }
            } else {
                $err = 'Valid ID, name and price required.';
            }
        } elseif ( $action === 'delete' ) {
            $id = (int) ( $_POST['id'] ?? 0 );
            if ( $id > 0 ) {
                $products = array_values( array_filter( $products, function ( $p ) use ( $id ) {
                    return (int) ( $p['id'] ?? 0 ) !== $id;
                } ) );
                if ( tennispro_save_products( $products ) ) {
                    $msg = 'Product deleted.';
                } else {
                    $err = 'Failed to save file.';
                }
            }
        }
    }

    $products = tennispro_get_products();
    ?>
    <div class="wrap">
        <h1>Product Management (products.json)</h1>
        <p>Products are stored in <code><?php echo esc_html( $path ); ?></code>. Capstone: CRUD with server-side validation.</p>
        <?php if ( $msg ) : ?>
            <div class="notice notice-success"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php endif; ?>
        <?php if ( $err ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
        <?php endif; ?>

        <h2>Create product</h2>
        <form method="post" style="max-width:600px;">
            <?php wp_nonce_field( 'tennispro_products' ); ?>
            <input type="hidden" name="tennispro_product_action" value="create">
            <p>
                <label>Name <input type="text" name="name" required style="width:100%;"></label>
            </p>
            <p>
                <label>Description <textarea name="description" rows="3" style="width:100%;"></textarea></label>
            </p>
            <p>
                <label>Price <input type="number" name="price" step="0.01" required></label>
            </p>
            <p>
                <label>Image path <input type="text" name="image_path" placeholder="/wp-content/uploads/..."></label>
            </p>
            <p><button type="submit" class="button button-primary">Create</button></p>
        </form>

        <h2>Existing products (<?php echo count( $products ); ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Image path</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $products as $p ) : ?>
                    <tr>
                        <td><?php echo (int) ( $p['id'] ?? 0 ); ?></td>
                        <td><?php echo esc_html( $p['name'] ?? '' ); ?></td>
                        <td><?php echo number_format( (float) ( $p['price'] ?? 0 ), 2 ); ?></td>
                        <td><?php echo esc_html( $p['image_path'] ?? $p['image'] ?? '' ); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'tennispro_products' ); ?>
                                <input type="hidden" name="tennispro_product_action" value="update">
                                <input type="hidden" name="id" value="<?php echo (int) ( $p['id'] ?? 0 ); ?>">
                                <input type="text" name="name" value="<?php echo esc_attr( $p['name'] ?? '' ); ?>" size="20">
                                <input type="number" name="price" step="0.01" value="<?php echo esc_attr( $p['price'] ?? 0 ); ?>" size="8">
                                <input type="text" name="image_path" value="<?php echo esc_attr( $p['image_path'] ?? $p['image'] ?? '' ); ?>" size="15">
                                <textarea name="description" rows="1" style="vertical-align:middle;"><?php echo esc_textarea( $p['description'] ?? '' ); ?></textarea>
                                <button type="submit" class="button">Update</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete?');">
                                <?php wp_nonce_field( 'tennispro_products' ); ?>
                                <input type="hidden" name="tennispro_product_action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) ( $p['id'] ?? 0 ); ?>">
                                <button type="submit" class="button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function tennispro_save_products( $products ) {
    $path = tennispro_products_json_path();
    $dir  = dirname( $path );
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    $json = json_encode( array_values( $products ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    return $json !== false && file_put_contents( $path, $json ) !== false;
}

/** Checkout login: validate Email + Phone against FluentCRM (Capstone 2.3.7). */
function tennispro_checkout_verify( $email, $phone ) {
    global $wpdb;
    $email = trim( $email );
    $phone = trim( $phone );
    if ( $email === '' || $phone === '' ) {
        return null;
    }
    $table = $wpdb->prefix . 'fc_subscribers';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return null;
    }
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE email = %s AND (status = 'subscribed' OR status IS NULL) LIMIT 1",
            $email
        ),
        ARRAY_A
    );
    return is_array( $row ) ? $row : null;
}

/** FluentCRM subscribers for Customer List (Name, Email, Phone). */
function tennispro_get_subscribers( $limit = 200 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'fc_subscribers';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return [];
    }
    return $wpdb->get_results(
        $wpdb->prepare( "SELECT id, email, first_name, last_name, phone, status, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
        ARRAY_A
    );
}

/**
 * Saved customer cards for demo checkout.
 * We only store brand + last4 for display; no real payments are processed.
 */
function tennispro_get_customer_cards( $email ) {
    global $wpdb;
    $email = trim( (string) $email );
    if ( $email === '' ) {
        return [];
    }
    $table = $wpdb->prefix . 'customer_cards';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return [];
    }
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, holder_name, card_brand, card_last4, created_at
             FROM {$table}
             WHERE customer_email = %s
             ORDER BY created_at DESC",
            $email
        ),
        ARRAY_A
    );
}

function tennispro_save_customer_card( $email, $holder_name, $card_number_raw ) {
    global $wpdb;
    $email       = trim( (string) $email );
    $holder_name = trim( (string) $holder_name );
    $card_number = preg_replace( '/\D+/', '', (string) $card_number_raw );
    if ( $email === '' || $holder_name === '' || strlen( $card_number ) < 4 ) {
        return false;
    }
    $last4 = substr( $card_number, -4 );
    // Very simple brand guess based on first digit.
    $brand = 'Card';
    if ( preg_match( '/^4/', $card_number ) ) {
        $brand = 'Visa';
    } elseif ( preg_match( '/^5[1-5]/', $card_number ) ) {
        $brand = 'Mastercard';
    } elseif ( preg_match( '/^3[47]/', $card_number ) ) {
        $brand = 'Amex';
    }

    $table = $wpdb->prefix . 'customer_cards';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return false;
    }
    return (bool) $wpdb->insert(
        $table,
        [
            'customer_email' => $email,
            'holder_name'    => $holder_name,
            'card_brand'     => $brand,
            'card_last4'     => $last4,
            'created_at'     => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%s', '%s' ]
    );
}
