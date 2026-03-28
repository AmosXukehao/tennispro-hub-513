<?php
/**
 * Snippet: TennisPro Register Success Redirect
 * Description: On the Register page, shows a success message after Fluent Forms submission
 *              and redirects to the login page after 3 seconds.
 */

add_action( 'wp_footer', function () {
    if ( ! is_page( 'register' ) ) {
        return;
    }
    $login_url = get_permalink( get_page_by_path( 'login' ) ) ?: home_url( '/login/' );
    ?>
    <div id="register-success-msg" style="display:none;background:#d4edda;border:2px solid #28a745;color:#155724;padding:16px 20px;border-radius:10px;margin-bottom:20px;font-size:16px;">
        <strong>Registration successful!</strong> A welcome email has been sent to your inbox.
        <br>Redirecting to login page in <span id="reg-countdown">3</span> seconds...
    </div>
    <script>
    (function(){
        var loginUrl = <?php echo wp_json_encode( $login_url ); ?>;
        var observer = new MutationObserver(function(mutations){
            mutations.forEach(function(m){
                m.addedNodes.forEach(function(node){
                    if (node.nodeType !== 1) return;
                    var el = node.classList ? node : null;
                    if (!el) return;
                    var txt = el.textContent || '';
                    if (el.classList.contains('ff-message-success') ||
                        el.classList.contains('fluentform-submission') ||
                        txt.indexOf('Thank you') > -1 ||
                        txt.indexOf('submitted') > -1 ||
                        txt.indexOf('successfully') > -1) {
                        showSuccess();
                    }
                });
            });
        });
        observer.observe(document.body, {childList: true, subtree: true});
        document.addEventListener('fluentform_submission_success', function(){ showSuccess(); });

        var shown = false;
        function showSuccess(){
            if (shown) return;
            shown = true;
            var msg = document.getElementById('register-success-msg');
            if (msg) {
                msg.style.display = 'block';
                var content = document.querySelector('.tennispro-container');
                if (content) content.insertBefore(msg, content.firstChild);
            }
            window.scrollTo({top: 0, behavior: 'smooth'});
            var count = 3;
            var span = document.getElementById('reg-countdown');
            var timer = setInterval(function(){
                count--;
                if (span) span.textContent = count;
                if (count <= 0) {
                    clearInterval(timer);
                    window.location.href = loginUrl;
                }
            }, 1000);
        }
    })();
    </script>
    <?php
} );
