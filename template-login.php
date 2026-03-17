<?php
/**
 * Template Name: Customer Login (Capstone)
 * Frontend login using FluentCRM (Email + Phone).
 * This does NOT use the WordPress admin login screen.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$error   = '';
$success = false;

// Handle logout via query parameter ?logout=1
if ( isset( $_GET['logout'] ) && $_GET['logout'] == '1' ) {
    unset( $_SESSION['checkout_email'], $_SESSION['checkout_user_id'], $_SESSION['checkout_name'] );
    $success = false;
    $error   = '';
}

// Handle login
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

    if ( $email === '' || $phone === '' ) {
        $error = 'Please enter both Email and Phone Number.';
    } elseif ( ! is_email( $email ) ) {
        $error = 'Please enter a valid email address.';
    } elseif ( function_exists( 'tennispro_checkout_verify' ) ) {
        $sub = tennispro_checkout_verify( $email, $phone );
        if ( $sub ) {
            $_SESSION['checkout_email']   = $sub['email'] ?? $email;
            $_SESSION['checkout_user_id'] = $sub['id'] ?? null;
            $_SESSION['checkout_name']    = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
            if ( $_SESSION['checkout_name'] === '' ) {
                $_SESSION['checkout_name'] = $email;
            }
            $success = true;
        } else {
            $error = 'We could not find a customer with that Email and Phone. Please check your details or register first.';
        }
    }
}
?>
<h1>Customer login</h1>
<p>Log in with the Email and Phone Number you used when registering. This verifies you against our FluentCRM customer list.</p>

<?php if ( ! empty( $_SESSION['checkout_email'] ) ) : ?>
    <div class="alert alert-success">
        You are currently logged in as
        <strong><?php echo esc_html( $_SESSION['checkout_name'] ?? $_SESSION['checkout_email'] ); ?></strong>.
        <a href="<?php echo esc_url( add_query_arg( 'logout', '1', get_permalink() ) ); ?>" style="margin-left:8px;">Log out</a>
    </div>
<?php elseif ( $success ) : ?>
    <div class="alert alert-success">
        You are now logged in as <?php echo esc_html( $_SESSION['checkout_name'] ?? $_SESSION['checkout_email'] ?? '' ); ?>.
        You can now continue to browse products, view your cart, or proceed to checkout.
    </div>
<?php endif; ?>

<?php if ( $error ) : ?>
    <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( empty( $_SESSION['checkout_email'] ) ) : ?>
    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" required>
        </div>
        <button type="submit" class="btn">Log in</button>
    </form>

    <p style="margin-top:16px;">
        New here? <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'register' ) ) ?: home_url( '/register/' ) ); ?>">Register now</a>.
    </p>
<?php endif; ?>

<?php get_footer(); ?>

