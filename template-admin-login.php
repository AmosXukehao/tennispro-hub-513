<?php
/**
 * Template Name: Admin Login (Capstone)
 * Frontend admin login using custom credentials (admin / admin123), with unified UI.
 */
defined( 'ABSPATH' ) || exit;

$error   = '';
$message = '';

// Ensure session is available even if another plugin/theme changes init timing.
if ( ! session_id() && ! headers_sent() ) {
    session_start();
}

// Handle admin logout via ?logout=1
if ( isset( $_GET['logout'] ) && $_GET['logout'] == '1' ) {
    unset( $_SESSION['capstone_admin_logged_in'] );
    // Also log out any customer session when admin logs out.
    unset( $_SESSION['checkout_email'], $_SESSION['checkout_user_id'], $_SESSION['checkout_name'] );
    $message = 'You have been logged out of the admin area.';
}

// If already logged in via custom admin session, go straight to dashboard.
if ( isset( $_SESSION['capstone_admin_logged_in'] ) && $_SESSION['capstone_admin_logged_in'] === true && empty( $_GET['logout'] ) ) {
    wp_safe_redirect( get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/' ) );
    exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
    $password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

    if ( $username === '' || $password === '' ) {
        $error = 'Please enter both username and password.';
    } elseif ( $username === 'admin' && $password === 'admin123' ) {
        // Custom admin account: give full admin session and customer session.
        $_SESSION['capstone_admin_logged_in'] = true;
        // Admin has all customer privileges: set checkout session as well.
        $_SESSION['checkout_email']   = 'admin@example.com';
        $_SESSION['checkout_user_id'] = 0;
        $_SESSION['checkout_name']    = 'Admin';
        wp_safe_redirect( get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/' ) );
        exit;
    } else {
        $error = 'Invalid admin credentials.';
    }
}
?>

<?php get_header(); ?>

<h1>Administrator Login</h1>
<p>Sign in with the administrator account <strong>admin / admin123</strong> to view the internal dashboard and reports.</p>

<?php if ( $message ) : ?>
    <div class="alert alert-success"><?php echo esc_html( $message ); ?></div>
<?php endif; ?>

<?php if ( $error ) : ?>
    <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
<?php endif; ?>

<form method="post">
    <div class="form-group">
        <label for="username">Username or Email</label>
        <input type="text" id="username" name="username" required>
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn">Admin Login</button>
</form>

<p style="margin-top:16px;">
    Already logged in? <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/' ) ); ?>">Go to Admin Dashboard</a> |
    <a href="<?php echo esc_url( add_query_arg( 'logout', '1', get_permalink() ) ); ?>">Admin logout</a>
</p>

<?php get_footer(); ?>

