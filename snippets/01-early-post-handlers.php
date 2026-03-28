<?php
/**
 * Snippet: TennisPro Early POST Handlers
 * Description: Handles form POST submissions (login, checkout, payment, admin login)
 *              via template_redirect so redirects fire before any output.
 * Scope: Run everywhere (front-end only)
 */

add_action( 'template_redirect', 'tennispro_shortcode_post_handlers', 1 );
function tennispro_shortcode_post_handlers() {
    if ( ! isset( $_SESSION ) ) session_start();

    // ── Login POST ──
    if ( is_page( 'login' ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_login_submit'] ) ) {
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

        if ( $email === '' || $phone === '' ) {
            $_SESSION['tennispro_login_error'] = 'Please enter both Email and Phone Number.';
        } elseif ( ! is_email( $email ) ) {
            $_SESSION['tennispro_login_error'] = 'Please enter a valid email address.';
        } elseif ( function_exists( 'tennispro_checkout_verify' ) ) {
            $sub = tennispro_checkout_verify( $email, $phone );
            if ( $sub ) {
                $_SESSION['checkout_email']   = $sub['email'] ?? $email;
                $_SESSION['checkout_user_id'] = $sub['id'] ?? null;
                $_SESSION['checkout_name']    = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
                if ( $_SESSION['checkout_name'] === '' ) $_SESSION['checkout_name'] = $email;
                wp_safe_redirect( get_permalink( get_page_by_path( 'my-orders' ) ) ?: home_url( '/my-orders/' ) );
                exit;
            }
            $_SESSION['tennispro_login_error'] = 'We could not find a customer with that Email and Phone. Please check your details or register first.';
        }
        wp_safe_redirect( get_permalink() );
        exit;
    }

    // ── Login logout ──
    if ( is_page( 'login' ) && isset( $_GET['logout'] ) && $_GET['logout'] == '1' ) {
        unset( $_SESSION['checkout_email'], $_SESSION['checkout_user_id'], $_SESSION['checkout_name'] );
        wp_safe_redirect( get_permalink() );
        exit;
    }

    // ── Checkout POST (verify customer, redirect to payment) ──
    if ( is_page( 'checkout' ) && $_SERVER['REQUEST_METHOD'] === 'POST' && ! isset( $_POST['tennispro_checkout_submit'] ) ) {
        $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $full_name        = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
        $shipping_address = isset( $_POST['shipping_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shipping_address'] ) ) : '';
        $billing_address  = isset( $_POST['billing_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['billing_address'] ) ) : '';
        $payment_method   = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'credit_card';
        $order_notes      = isset( $_POST['order_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) ) : '';

        if ( $email === '' || ! is_email( $email ) ) {
            $_SESSION['checkout_error'] = 'Please enter a valid email address.';
            wp_safe_redirect( get_permalink() );
            exit;
        }
        $sub = function_exists( 'tennispro_checkout_verify' ) ? tennispro_checkout_verify( $email, $phone ) : null;
        if ( ! $sub ) {
            $_SESSION['checkout_error'] = 'Email and Phone not found in our FluentCRM customer list. Please register first.';
            wp_safe_redirect( get_permalink() );
            exit;
        }
        $customer_name = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
        if ( $customer_name === '' ) $customer_name = $full_name !== '' ? $full_name : $email;
        $_SESSION['was_logged_in_before_checkout'] = ! empty( $_SESSION['checkout_email'] );
        $_SESSION['checkout_email']            = $sub['email'] ?? $email;
        $_SESSION['checkout_user_id']          = $sub['id'] ?? null;
        $_SESSION['checkout_name']             = $customer_name;
        $_SESSION['checkout_phone']            = $phone;
        $_SESSION['checkout_shipping_address'] = $shipping_address;
        $_SESSION['checkout_billing_address']  = $billing_address;
        $_SESSION['checkout_payment_method']   = $payment_method;
        $_SESSION['checkout_order_notes']      = $order_notes;
        unset( $_SESSION['checkout_error'] );
        wp_safe_redirect( get_permalink( get_page_by_path( 'payment' ) ) ?: home_url( '/payment/' ) );
        exit;
    }

    // ── Payment POST (process payment, place order, redirect to my-orders) ──
    if ( is_page( 'payment' ) && $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_SESSION['checkout_email'] ) ) {
        $card_name = isset( $_POST['card_name'] ) ? sanitize_text_field( wp_unslash( $_POST['card_name'] ) ) : '';
        $card_no   = isset( $_POST['card_no'] ) ? sanitize_text_field( wp_unslash( $_POST['card_no'] ) ) : '';
        $phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

        if ( $card_name !== '' && $card_no !== '' && $phone !== '' ) {
            global $wpdb;
            $prefix = $wpdb->prefix;
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
                $wpdb->insert( $prefix . 'orders', [
                    'user_name' => $user_name, 'total_amount' => $total,
                    'contact_name' => $card_name, 'contact_phone' => $phone,
                    'created_at' => current_time( 'mysql' ),
                ], [ '%s', '%f', '%s', '%s', '%s' ] );
                $order_id = $wpdb->insert_id;
                if ( $order_id ) {
                    foreach ( $items as $item ) {
                        $wpdb->insert( $prefix . 'order_items', [
                            'order_id' => $order_id, 'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                        ], [ '%d', '%d', '%d', '%f' ] );
                    }
                }
                if ( function_exists( 'tennispro_save_customer_card' ) ) {
                    tennispro_save_customer_card( $_SESSION['checkout_email'] ?? '', $card_name, $card_no );
                }

                $_SESSION['cart'] = [];
                $_SESSION['last_order_id']    = $order_id;
                $_SESSION['last_order_total'] = $total;
                $_SESSION['last_order_name']  = $user_name;
                $_SESSION['last_order_items'] = $items;

                $success_page = home_url( '/checkout/' );
                $success_page = add_query_arg( [ 'order_success' => '1', 'order_id' => $order_id ], $success_page );

                if ( empty( $_SESSION['was_logged_in_before_checkout'] ) ) {
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
                }
                unset( $_SESSION['was_logged_in_before_checkout'] );

                wp_safe_redirect( $success_page );
                exit;
            }
        }
    }

    // ── Admin Login POST ──
    if ( is_page( 'admin-login' ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_admin_login'] ) ) {
        $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
        $password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

        if ( $username === 'admin' && $password === 'admin123' ) {
            $_SESSION['capstone_admin_logged_in'] = true;
            $_SESSION['checkout_email']   = 'admin@example.com';
            $_SESSION['checkout_user_id'] = 0;
            $_SESSION['checkout_name']    = 'Admin';
            wp_safe_redirect( get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/admin-dashboard/' ) );
            exit;
        }
        $_SESSION['tennispro_admin_login_error'] = 'Invalid admin credentials.';
        wp_safe_redirect( get_permalink() );
        exit;
    }

    // ── Admin Login logout ──
    if ( is_page( 'admin-login' ) && isset( $_GET['logout'] ) && $_GET['logout'] == '1' ) {
        unset( $_SESSION['capstone_admin_logged_in'], $_SESSION['checkout_email'], $_SESSION['checkout_user_id'], $_SESSION['checkout_name'] );
    }
}
