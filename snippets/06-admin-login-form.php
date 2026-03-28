<?php
/**
 * Snippet: TennisPro Admin Login Form
 * Shortcode: [tennispro_admin_login_form]
 * Description: Renders the administrator login form.
 */

add_shortcode( 'tennispro_admin_login_form', function () {
    ob_start();
    $error   = $_SESSION['tennispro_admin_login_error'] ?? '';
    unset( $_SESSION['tennispro_admin_login_error'] );
    $message = '';
    if ( isset( $_GET['logout'] ) && $_GET['logout'] == '1' ) {
        $message = 'You have been logged out of the admin area.';
    }

    if ( ! empty( $_SESSION['capstone_admin_logged_in'] ) && empty( $_GET['logout'] ) ) :
        $dash = get_permalink( get_page_by_path( 'admin-dashboard' ) ) ?: home_url( '/admin-dashboard/' );
    ?>
        <div class="alert alert-success">You are already logged in as administrator.</div>
        <p>
            <a class="btn" href="<?php echo esc_url( $dash ); ?>">Go to Admin Dashboard</a>
            <a class="btn" href="<?php echo esc_url( add_query_arg( 'logout', '1', get_permalink() ) ); ?>" style="margin-left:8px;background:#6c757d;">Admin logout</a>
        </p>
    <?php else : ?>
        <?php if ( $message ) : ?><div class="alert alert-success"><?php echo esc_html( $message ); ?></div><?php endif; ?>
        <?php if ( $error ) : ?><div class="alert alert-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
        <p>Sign in with the administrator account <strong>admin / admin123</strong> to view the internal dashboard and reports.</p>
        <form method="post">
            <input type="hidden" name="tennispro_admin_login" value="1">
            <div class="form-group"><label for="username">Username</label><input type="text" id="username" name="username" required></div>
            <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
            <button type="submit" class="btn">Sign in</button>
        </form>
    <?php endif;

    return ob_get_clean();
} );
