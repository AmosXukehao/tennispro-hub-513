<?php
/**
 * Snippet: TennisPro Home Banner
 * Shortcode: [tennispro_home]
 * Description: Renders the home page banner, intro text, and CTA buttons.
 */

add_shortcode( 'tennispro_home', function () {
    ob_start();
    $banner_url = get_stylesheet_directory_uri() . '/images/banner.jpg';
    ?>
    <div class="tennispro-banner" style="background-image: url('<?php echo esc_url( $banner_url ); ?>');">
        <h1>Welcome to TennisPro Hub</h1>
        <p class="slogan">Your One-Stop Tennis Hub — Coaching, Equipment, Courts.</p>
    </div>

    <p>TennisPro Hub is an integrated e-commerce platform designed to serve tennis enthusiasts by offering a one-stop solution for tennis training, equipment, and facility bookings.</p>
    <p>Our platform combines high-quality tennis coaching services, a curated selection of tennis rackets and accessories, and a seamless online court reservation system.</p>

    <h2>Who is this for?</h2>
    <p>We target amateur and professional players aged 18–45, college athletes, fitness lovers, parents seeking lessons for their children, and corporate clients organizing tennis events.</p>

    <h2>Why TennisPro Hub?</h2>
    <p>Our unique value comes from combining coaching services, equipment sales, and court bookings in a single platform.</p>

    <p>
        <a class="btn" href="<?php echo esc_url( get_permalink( get_page_by_path( 'products' ) ) ?: home_url( '/products/' ) ); ?>">Browse products</a>
        <a class="btn btn-secondary" href="<?php echo esc_url( get_permalink( get_page_by_path( 'cart' ) ) ?: home_url( '/cart/' ) ); ?>">View cart</a>
    </p>
    <?php
    return ob_get_clean();
} );
