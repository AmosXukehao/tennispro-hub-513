<?php
defined( 'ABSPATH' ) || exit;
$nav_links = function_exists( 'tennispro_nav_links' ) ? tennispro_nav_links() : [];
$auth_ui   = function_exists( 'tennispro_auth_ui' ) ? tennispro_auth_ui() : [ 'mode' => 'logged_out', 'items' => [] ];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="tennispro-header">
    <div class="header-logo">
        <span><?php echo esc_html( defined( 'TENNISPRO_SITE_NAME' ) ? TENNISPRO_SITE_NAME : 'Amos' ); ?></span> TennisPro Hub
    </div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>

    <nav class="header-links" id="headerLinks">
        <?php foreach ( $nav_links as $link ) : ?>
            <a href="<?php echo esc_url( $link['href'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="header-auth" id="headerAuth">
        <div class="nav-auth-dropdown">
            <a class="nav-auth-trigger" href="<?php echo esc_url( $auth_ui['triggerHref'] ?? home_url( '/' ) ); ?>">
                <?php echo esc_html( $auth_ui['triggerLabel'] ?? 'Customer Login' ); ?>
                <span class="nav-auth-caret">&#9662;</span>
            </a>
            <div class="nav-auth-menu">
                <?php foreach ( (array) ( $auth_ui['items'] ?? [] ) as $item ) : ?>
                    <a class="nav-auth-item" href="<?php echo esc_url( $item['href'] ?? '#' ); ?>">
                        <?php echo esc_html( $item['label'] ?? '' ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</header>

<script>
(function(){
    var btn = document.getElementById('mobileMenuToggle');
    var links = document.getElementById('headerLinks');
    var auth = document.getElementById('headerAuth');
    if (!btn || !links) return;

    function positionAuth(){
        if (!auth) return;
        if (links.classList.contains('mobile-open')) {
            auth.style.top = (links.offsetTop + links.offsetHeight) + 'px';
        }
    }

    btn.addEventListener('click', function(){
        var open = links.classList.toggle('mobile-open');
        btn.classList.toggle('active');
        if (auth) {
            if (open) {
                auth.classList.add('mobile-open');
                setTimeout(positionAuth, 10);
            } else {
                auth.classList.remove('mobile-open');
            }
        }
    });
    document.addEventListener('click', function(e){
        if (!btn.contains(e.target) && !links.contains(e.target) && (!auth || !auth.contains(e.target))) {
            links.classList.remove('mobile-open');
            btn.classList.remove('active');
            if (auth) auth.classList.remove('mobile-open');
        }
    });
})();
</script>

<div class="tennispro-container tennispro-capstone">
