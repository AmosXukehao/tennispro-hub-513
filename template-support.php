<?php
/**
 * Template Name: Customer Support (Capstone)
 * Form: Name, Email, Subject, Message. Save to wp_support_tickets; send email confirmation.
 */
defined( 'ABSPATH' ) || exit;
get_header();

global $wpdb;
$prefix = $wpdb->prefix;
$error  = '';
$success = false;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $name    = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $email   = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
    $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

    if ( $name === '' || $email === '' || $subject === '' || $message === '' ) {
        $error = 'All fields are required.';
    } elseif ( ! is_email( $email ) ) {
        $error = 'Please enter a valid email.';
    } else {
        $inserted = $wpdb->insert(
            $prefix . 'support_tickets',
            [
                'customer_name'  => $name,
                'customer_email' => $email,
                'subject'        => $subject,
                'message'        => $message,
                'submitted_at'   => current_time( 'mysql' ),
                'status'         => 'open',
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
        if ( $inserted ) {
            $success = true;
            wp_mail(
                $email,
                'We received your message - TennisPro Hub Support',
                "Hello " . $name . ",\n\nThank you for your message. We'll respond within 48 hours.\n\nSubject: " . $subject . "\n\nBest regards,\nTennisPro Hub Support Team"
            );
        } else {
            $error = 'Failed to submit ticket.';
        }
    }
}
?>
<h1>Customer Support</h1>
<p>Submit a support ticket and our team will respond as soon as possible.</p>

<?php if ( $success ) : ?>
    <div class="alert alert-success">Your ticket has been submitted. We have sent you a confirmation email.</div>
<?php endif; ?>
<?php if ( $error ) : ?>
    <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
<?php endif; ?>

<form method="post">
    <div class="form-group">
        <label for="customer_name">Name</label>
        <input type="text" id="customer_name" name="customer_name" required>
    </div>
    <div class="form-group">
        <label for="customer_email">Email</label>
        <input type="email" id="customer_email" name="customer_email" required>
    </div>
    <div class="form-group">
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" required>
    </div>
    <div class="form-group">
        <label for="message">Message</label>
        <textarea id="message" name="message" rows="4" required></textarea>
    </div>
    <button type="submit" class="btn">Submit ticket</button>
</form>

<?php get_footer(); ?>
