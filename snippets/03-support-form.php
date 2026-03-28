<?php
/**
 * Snippet: TennisPro Support Form
 * Shortcode: [tennispro_support_form]
 * Description: Renders the customer support ticket submission form.
 */

add_shortcode( 'tennispro_support_form', function () {
    ob_start();
    global $wpdb;
    $prefix  = $wpdb->prefix;
    $error   = '';
    $success = false;

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_support_submit'] ) ) {
        $name    = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
        $email   = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( $name === '' || $email === '' || $subject === '' || $message === '' ) {
            $error = 'All fields are required.';
        } elseif ( ! is_email( $email ) ) {
            $error = 'Please enter a valid email.';
        } else {
            $inserted = $wpdb->insert( $prefix . 'support_tickets', [
                'customer_name'  => $name,
                'customer_email' => $email,
                'subject'        => $subject,
                'message'        => $message,
                'submitted_at'   => current_time( 'mysql' ),
                'status'         => 'open',
            ], [ '%s', '%s', '%s', '%s', '%s', '%s' ] );
            if ( $inserted ) {
                $success = true;
                wp_mail( $email, 'We received your message - TennisPro Hub Support',
                    "Hello " . $name . ",\n\nThank you for your message. We'll respond within 48 hours.\n\nSubject: " . $subject . "\n\nBest regards,\nTennisPro Hub Support Team" );
            } else {
                $error = 'Failed to submit ticket.';
            }
        }
    }

    if ( $success ) : ?>
        <div class="alert alert-success">Your ticket has been submitted. We have sent you a confirmation email.</div>
    <?php endif;
    if ( $error ) : ?>
        <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="tennispro_support_submit" value="1">
        <div class="form-group"><label for="customer_name">Name</label><input type="text" id="customer_name" name="customer_name" required></div>
        <div class="form-group"><label for="customer_email">Email</label><input type="email" id="customer_email" name="customer_email" required></div>
        <div class="form-group"><label for="subject">Subject</label><input type="text" id="subject" name="subject" required></div>
        <div class="form-group"><label for="message">Message</label><textarea id="message" name="message" rows="4" required></textarea></div>
        <button type="submit" class="btn">Submit ticket</button>
    </form>
    <?php
    return ob_get_clean();
} );
