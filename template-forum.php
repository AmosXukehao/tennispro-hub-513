<?php
/**
 * Template Name: Discussion Forum (Capstone)
 * Create new topics; list ≥20 posts. (Reply = new post in same list.)
 */
defined( 'ABSPATH' ) || exit;
get_header();

global $wpdb;
$prefix = $wpdb->prefix;
$error  = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_SESSION['checkout_name'] ) ) {
    $title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
    $author  = $_SESSION['checkout_name'];
    if ( $title === '' || $content === '' ) {
        $error = 'Title and content are required.';
    } else {
        $wpdb->insert(
            $prefix . 'forum_posts',
            [
                'title'      => $title,
                'content'    => $content,
                'author'     => $author,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }
} elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $error = 'You must be logged in to post.';
}

$posts = $wpdb->get_results(
    "SELECT id, title, content, author, created_at FROM {$prefix}forum_posts ORDER BY created_at DESC LIMIT 100",
    ARRAY_A
);
?>
<h1>Community Forum</h1>
<p>Share your tennis experience and discuss coaching, equipment, and courts.</p>

<?php if ( $error ) : ?>
    <div class="alert alert-error"><?php echo esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( ! empty( $_SESSION['checkout_name'] ) ) : ?>
    <h2>Create a new post</h2>
    <p style="color:#588157;margin-bottom:10px;">Posting as: <strong><?php echo esc_html( $_SESSION['checkout_name'] ); ?></strong></p>
    <form method="post">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn">Post</button>
    </form>
<?php else : ?>
    <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:16px;border-radius:8px;margin:20px 0;">
        <strong>Login required</strong> — You must be logged in to create a post.
        <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'login' ) ) ?: home_url( '/login/' ) ); ?>" class="btn" style="margin-left:12px;padding:6px 16px;font-size:14px;">Customer Login</a>
    </div>
<?php endif; ?>

<h2>Latest posts</h2>
<?php if ( empty( $posts ) ) : ?>
    <p>No posts yet. Be the first to start a discussion!</p>
<?php else : ?>
    <?php foreach ( $posts as $post ) : ?>
        <div class="product-card" style="margin-top:10px;">
            <div class="product-name"><?php echo esc_html( $post['title'] ?? '' ); ?></div>
            <div style="font-size:12px; color:#666; margin-bottom:6px;">
                By <?php echo esc_html( $post['author'] ?? '' ); ?>
                on <?php echo esc_html( $post['created_at'] ?? '' ); ?>
            </div>
            <div class="product-desc" style="white-space:pre-wrap;"><?php echo nl2br( esc_html( $post['content'] ?? '' ) ); ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php get_footer(); ?>
