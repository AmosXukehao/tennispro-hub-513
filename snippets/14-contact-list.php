<?php
/**
 * Snippet: TennisPro Contact List
 * Shortcode: [tennispro_contact_list]
 * Description: Displays all FluentCRM subscribers (Name, Email, Phone).
 */

add_shortcode( 'tennispro_contact_list', function () {
    ob_start();

    $subscribers = function_exists( 'tennispro_get_subscribers' ) ? tennispro_get_subscribers( 200 ) : [];
    ?>
    <h1>Customer List (FluentCRM Subscribers)</h1>
    <p>Name, Email, Phone from FluentCRM. Ensure you have ≥30 records for submission.</p>

    <?php if ( empty( $subscribers ) ) : ?>
        <p>No subscribers yet. Add contacts via your FluentCRM registration form.</p>
    <?php else : ?>
        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Created</th></tr>
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
        </div>
        <p><strong><?php echo count( $subscribers ); ?></strong> subscriber(s).</p>
    <?php endif; ?>

    <?php
    return ob_get_clean();
} );
