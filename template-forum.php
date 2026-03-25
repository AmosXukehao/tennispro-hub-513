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
        <button type="button" class="btn" style="margin-left:12px;padding:6px 16px;font-size:14px;" onclick="document.getElementById('forum-login-modal').style.display='flex'">Customer Login</button>
    </div>

    <div id="forum-login-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:32px;width:90%;max-width:420px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.25);">
            <button type="button" onclick="document.getElementById('forum-login-modal').style.display='none'" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:#666;">&times;</button>
            <h2 style="margin-top:0;color:#344E41;">Customer Login</h2>
            <p style="color:#666;font-size:14px;">Log in with Email and Phone to post in the forum.</p>
            <div id="forum-login-error" style="display:none;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:10px;border-radius:6px;margin-bottom:12px;"></div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="forum-email" style="display:block;margin-bottom:4px;font-weight:600;">Email</label>
                <input type="email" id="forum-email" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:14px;" required>
            </div>
            <div class="form-group" style="margin-bottom:18px;">
                <label for="forum-phone" style="display:block;margin-bottom:4px;font-weight:600;">Phone Number</label>
                <input type="text" id="forum-phone" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:14px;" required>
            </div>
            <button type="button" id="forum-login-btn" class="btn" style="width:100%;padding:12px;font-size:16px;">Log in</button>
            <p style="margin-top:14px;text-align:center;font-size:13px;color:#666;">
                New here? <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'register' ) ) ?: home_url( '/register/' ) ); ?>">Register now</a>
            </p>
        </div>
    </div>

    <script>
    (function(){
        var modal = document.getElementById('forum-login-modal');
        modal.addEventListener('click', function(e){ if(e.target === modal) modal.style.display='none'; });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape') modal.style.display='none'; });

        document.getElementById('forum-login-btn').addEventListener('click', function(){
            var btn   = this;
            var email = document.getElementById('forum-email').value.trim();
            var phone = document.getElementById('forum-phone').value.trim();
            var errEl = document.getElementById('forum-login-error');
            errEl.style.display = 'none';

            if(!email || !phone){
                errEl.textContent = 'Please enter both Email and Phone Number.';
                errEl.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Logging in...';

            var fd = new FormData();
            fd.append('action', 'tennispro_forum_login');
            fd.append('email', email);
            fd.append('phone', phone);

            fetch('<?php echo esc_url( admin_url("admin-ajax.php") ); ?>', { method:'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if(data.success){
                        location.reload();
                    } else {
                        errEl.textContent = data.data || 'Login failed. Please check your details.';
                        errEl.style.display = 'block';
                        btn.disabled = false;
                        btn.textContent = 'Log in';
                    }
                })
                .catch(function(){
                    errEl.textContent = 'Network error. Please try again.';
                    errEl.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Log in';
                });
        });
    })();
    </script>
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
