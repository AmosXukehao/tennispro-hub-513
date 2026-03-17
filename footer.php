<?php defined( 'ABSPATH' ) || exit; ?>
</div>

<footer class="tennispro-footer">
    &copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( defined( 'TENNISPRO_SITE_NAME' ) ? TENNISPRO_SITE_NAME : 'Amos' ); ?> — TennisPro Hub. All rights reserved.
</footer>

<script>
(function(){
    var origin = location.origin;
    var prefetched = {};
    document.addEventListener('mouseover', function(e){
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.href;
        if (!href || href.indexOf(origin) !== 0 || href.indexOf('#') > -1 || prefetched[href]) return;
        if (a.target === '_blank' || a.hasAttribute('download')) return;
        prefetched[href] = true;
        var link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = href;
        document.head.appendChild(link);
    }, {passive: true});
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
