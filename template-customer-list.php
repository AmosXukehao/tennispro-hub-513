<?php
/**
 * Template Name: Customer List (Capstone)
 * Displays all FluentCRM subscribers (Name, Email, Phone). ≥30 sample records for submission.
 */
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) && ! is_user_logged_in() ) {
    wp_safe_redirect( wp_login_url( get_permalink() ) );
    exit;
}

get_header();

$subscribers = function_exists( 'tennispro_get_subscribers' ) ? tennispro_get_subscribers( 200 ) : [];
?>
<h1>Customer List (FluentCRM Subscribers)</h1>
<p>Name, Email, Phone from FluentCRM. Ensure you have ≥30 records for submission.</p>

<?php if ( empty( $subscribers ) ) : ?>
    <p>No subscribers yet. Add contacts via your FluentCRM registration form.</p>
<?php else : ?>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $subscribers as $s ) : ?>
                <tr>
                    <td><?php echo (int) ( $s['id'] ?? 0 ); ?></td>
                    <td><?php echo esc_html( trim( ( $s['first_name'] ?? '' ) . ' ' . ( $s['last_name'] ?? '' ) ) ?: '-' ); ?></td>
                    <td><?php echo esc_html( $s['email'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $s['phone'] ?? '-' ); ?></td>
                    <td><?php echo esc_html( $s['status'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $s['created_at'] ?? '' ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><strong><?php echo count( $subscribers ); ?></strong> subscriber(s).</p>
<?php endif; ?>

<?php get_footer(); ?>
