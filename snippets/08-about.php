<?php
/**
 * Snippet: TennisPro About
 * Shortcode: [tennispro_about]
 * Description: Renders the About page content (company info, target market, map).
 */

add_shortcode( 'tennispro_about', function () {
    ob_start(); ?>
    <p>TennisPro Hub is an integrated e-commerce platform designed to serve tennis enthusiasts by offering a one-stop solution for tennis training, equipment, and facility bookings.</p>
    <p>Our platform combines high-quality tennis coaching services, a curated selection of tennis rackets and accessories, and a seamless online court reservation system.</p>
    <h2>Target market</h2>
    <p>We serve amateur and professional tennis players aged 18–45, college athletes, fitness enthusiasts, parents looking for lessons for their children, and corporate clients planning tennis events.</p>
    <h2>Our location</h2>
    <p>The map below highlights our primary market region.</p>
    <div style="margin-top:12px; text-align:center;">
        <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/image/map.jpg' ); ?>"
             alt="TennisPro Hub primary market region map"
             style="max-width:100%; height:auto; border-radius:12px; border:1px solid #d6dec9;">
    </div>
    <?php
    return ob_get_clean();
} );
