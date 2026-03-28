<?php
/**
 * TennisPro Hub Capstone — Child theme functions.
 * ICTWEB 513: WordPress as base CMS, all customizations in this child theme.
 */

defined( 'ABSPATH' ) || exit;

define( 'TENNISPRO_VERSION', '1.0.0' );
define( 'TENNISPRO_SITE_NAME', 'Amos' );

/** Register WordPress navigation menu. */
add_action( 'after_setup_theme', function () {
    register_nav_menus( [
        'primary' => 'Primary Navigation',
    ] );
} );

/** Fallback menu when no WordPress menu is assigned. */
function tennispro_fallback_menu() {
    $pages = [
        [ 'slug' => 'home',          'label' => 'Home' ],
        [ 'slug' => 'products',      'label' => 'Products' ],
        [ 'slug' => 'cart',          'label' => 'Cart' ],
        [ 'slug' => 'support',       'label' => 'Customer Support' ],
        [ 'slug' => 'about',         'label' => 'About' ],
        [ 'slug' => 'forum',         'label' => 'Discussion Forum' ],
        [ 'slug' => 'jobs',          'label' => 'Recruitment' ],
        [ 'slug' => 'customer-list', 'label' => 'Contact List' ],
    ];
    echo '<ul class="header-nav-menu">';
    foreach ( $pages as $item ) {
        $slug  = $item['slug'];
        $label = $item['label'];
        $url   = $slug === 'home' ? home_url( '/' ) : home_url( '/' . $slug . '/' );
        echo '<li class="menu-item"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Header auth UI model.
 * Logged out: Customer Login trigger, dropdown has Admin Login + Register.
 * Logged in: username/admin label, dropdown has Logout.
 */
function tennispro_auth_ui() {
    $session_email = (string) ( $_SESSION['checkout_email'] ?? '' );
    $session_name  = (string) ( $_SESSION['checkout_name'] ?? '' );
    $admin_logged  = ! empty( $_SESSION['capstone_admin_logged_in'] );

    $login_url    = get_permalink( get_page_by_path( 'login' ) ) ?: home_url( '/login/' );
    $admin_url    = get_permalink( get_page_by_path( 'admin-login' ) ) ?: home_url( '/admin-login/' );
    $register_url = get_permalink( get_page_by_path( 'register' ) ) ?: home_url( '/register/' );
    $logout_url   = add_query_arg( 'capstone_logout', '1', home_url( '/' ) );

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

/** Path to products.json */
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

/** Enqueue styles */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'twentytwentyfour-style', get_template_directory_uri() . '/style.css', [], null );
    wp_enqueue_style( 'tennispro-capstone', get_stylesheet_directory_uri() . '/style.css', [ 'twentytwentyfour-style' ], TENNISPRO_VERSION );
}, 20 );

/** Start session */
add_action( 'init', function () {
    if ( ! session_id() && ! headers_sent() ) {
        session_start();
    }
}, 1 );

/** AJAX Cart Operations */
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

    $products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
    $pmap     = [];
    foreach ( $products as $p ) { $pmap[ (int) ( $p['id'] ?? 0 ) ] = $p; }

    $cart_items = [];
    $total      = 0.0;
    $item_count = 0;
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

/** Logout handler + Checkout POST handler (runs before page output). */
add_action( 'template_redirect', function () {
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

        $sub = function_exists( 'tennispro_checkout_verify' ) ? tennispro_checkout_verify( $email, $phone ) : null;
        if ( ! $sub ) {
            $_SESSION['checkout_error'] = 'Email and Phone not found in our FluentCRM customer list. Please <a href="' . esc_url( home_url( '/register/' ) ) . '">register here</a> first.';
            wp_safe_redirect( get_permalink() );
            exit;
        }

        $was_logged_in = ! empty( $_SESSION['checkout_email'] );
        $customer_name = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
        if ( $customer_name === '' ) {
            $customer_name = $full_name !== '' ? $full_name : $email;
        }
        $_SESSION['checkout_email']              = $sub['email'] ?? $email;
        $_SESSION['checkout_user_id']            = $sub['id'] ?? null;
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
            if ( $qty <= 0 || ! isset( $product_map[ $pid ] ) ) continue;
            $unit = (float) ( $product_map[ $pid ]['price'] ?? 0 );
            if ( $unit <= 0 ) continue;
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

}, 0 );

/** Order confirmation body — called by checkout shortcode after successful order. */
function tennispro_render_order_confirmation_body() {
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
}

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

/** Create cv_uploads dir + seed forum with 20 posts if empty. */
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

/** REST API: GET /wp-json/custom/v1/products */
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

/** WP admin menu — redirects to frontend admin dashboard. */
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

/** Validate Email + Phone against FluentCRM. */
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
            "SELECT * FROM {$table} WHERE email = %s AND phone = %s AND status = 'subscribed' LIMIT 1",
            $email,
            $phone
        ),
        ARRAY_A
    );
    return is_array( $row ) ? $row : null;
}

/** FluentCRM subscribers for Customer List. */
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

/** Saved customer cards for demo checkout. */
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
