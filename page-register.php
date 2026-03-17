<?php
/**
 * Page for slug 'register' — shows FluentCRM/Fluent Forms shortcode
 * inside the TennisPro Hub layout (header/footer/styles).
 */
defined( 'ABSPATH' ) || exit;

get_header();

?>
<h1>Register</h1>
<p>Please complete the form below to join our TennisPro Hub community.</p>

<?php
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        the_content();
    }
}

get_footer();

