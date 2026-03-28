<?php
/**
 * Snippet: TennisPro Login Form
 * Shortcode: [tennispro_login_form]
 * Description: Renders the customer login form (Email + Phone via FluentCRM).
 */

add_shortcode( 'tennispro_login_form', function () {
    ob_start();
    $error = $_SESSION['tennispro_login_error'] ?? '';
    unset( $_SESSION['tennispro_login_error'] );

    if ( ! empty( $_SESSION['checkout_email'] ) ) : ?>
        <div class="alert alert-success">
            You are currently logged in as
            <strong><?php echo esc_html( $_SESSION['checkout_name'] ?? $_SESSION['checkout_email'] ); ?></strong>.
            <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'my-orders' ) ) ?: home_url( '/my-orders/' ) ); ?>" style="margin-left:8px;">Go to My Account</a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url( add_query_arg( 'logout', '1', get_permalink() ) ); ?>">Log out</a>
        </div>
    <?php endif;

    if ( $error ) : ?>
        <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
    <?php endif;

    if ( empty( $_SESSION['checkout_email'] ) ) : ?>
        <form method="post">
            <input type="hidden" name="tennispro_login_submit" value="1">
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
    <?php endif;

    return ob_get_clean();
} );
