<?php
/**
 * Snippet: TennisPro Jobs Form
 * Shortcode: [tennispro_jobs_form]
 * Description: Renders the job application form with CV upload.
 */

add_shortcode( 'tennispro_jobs_form', function () {
    ob_start();
    global $wpdb;
    $prefix  = $wpdb->prefix;
    $error   = '';
    $success = false;
    $allowed_ext  = [ 'pdf', 'doc', 'docx' ];
    $allowed_mime = [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ];

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tennispro_jobs_submit'] ) ) {
        $full_name    = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
        $email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $position     = isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : '';
        $cover_letter = isset( $_POST['cover_letter'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cover_letter'] ) ) : '';

        if ( $full_name === '' || $email === '' || $phone === '' || $position === '' || $cover_letter === '' ) {
            $error = 'All fields are required.';
        } elseif ( ! is_email( $email ) ) {
            $error = 'Please enter a valid email.';
        } elseif ( empty( $_FILES['cv_upload']['name'] ) || $_FILES['cv_upload']['error'] !== UPLOAD_ERR_OK ) {
            $error = 'Please upload your CV (PDF, DOC or DOCX only).';
        } else {
            $ext   = strtolower( pathinfo( $_FILES['cv_upload']['name'], PATHINFO_EXTENSION ) );
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime  = finfo_file( $finfo, $_FILES['cv_upload']['tmp_name'] );
            finfo_close( $finfo );
            if ( ! in_array( $ext, $allowed_ext, true ) || ! in_array( $mime, $allowed_mime, true ) ) {
                $error = 'Only .pdf, .doc and .docx files are allowed for CV.';
            } else {
                $upload_dir = wp_upload_dir();
                $cv_dir     = $upload_dir['basedir'] . '/cv_uploads';
                if ( ! is_dir( $cv_dir ) ) wp_mkdir_p( $cv_dir );
                $ht = $cv_dir . '/.htaccess';
                if ( ! file_exists( $ht ) ) file_put_contents( $ht, "php_flag engine off\n" );
                $safe_name = sanitize_file_name( $full_name ) . '_cv_' . time() . '.' . $ext;
                $safe_name = preg_replace( '/[^a-z0-9_\-\.]/i', '_', $safe_name );
                $target    = $cv_dir . '/' . $safe_name;
                if ( move_uploaded_file( $_FILES['cv_upload']['tmp_name'], $target ) ) {
                    $inserted = $wpdb->insert( $prefix . 'job_applications', [
                        'full_name'    => $full_name, 'email' => $email, 'phone' => $phone,
                        'position'     => $position, 'cover_letter' => $cover_letter,
                        'cv_file_path' => 'cv_uploads/' . $safe_name, 'status' => 'received',
                    ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
                    $success = $inserted ? true : false;
                    if ( ! $success ) $error = 'Failed to save application.';
                } else {
                    $error = 'Failed to save uploaded file.';
                }
            }
        }
    }

    if ( $success ) : ?><div class="alert alert-success">Thank you! Your application has been received.</div><?php endif;
    if ( $error ) : ?><div class="alert alert-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="tennispro_jobs_submit" value="1">
        <div class="form-group"><label for="full_name">Full Name</label><input type="text" id="full_name" name="full_name" required></div>
        <div class="form-group"><label for="email">Email Address</label><input type="email" id="email" name="email" required></div>
        <div class="form-group"><label for="phone">Phone Number</label><input type="text" id="phone" name="phone" required></div>
        <div class="form-group"><label for="position">Position Applying For</label>
            <select id="position" name="position" required>
                <option value="">— Select —</option>
                <option value="Tennis Coaching Specialist">Tennis Coaching Specialist</option>
                <option value="Business Intelligence Analyst">Business Intelligence Analyst</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="form-group"><label for="cover_letter">Cover Letter</label><textarea id="cover_letter" name="cover_letter" rows="4" required></textarea></div>
        <div class="form-group"><label for="cv_upload">CV / Resume (PDF, DOC or DOCX only)</label><input type="file" id="cv_upload" name="cv_upload" accept=".pdf,.doc,.docx" required></div>
        <button type="submit" class="btn">Submit application</button>
    </form>
    <?php
    return ob_get_clean();
} );
