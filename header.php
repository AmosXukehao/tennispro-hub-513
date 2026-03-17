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

    <nav class="header-links">
        <?php foreach ( $nav_links as $link ) : ?>
            <a href="<?php echo esc_url( $link['href'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="header-auth">
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

<div class="tennispro-container tennispro-capstone">
