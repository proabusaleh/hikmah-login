<?php
/**
 * Dashboard Profile Tab
 *
 * Handles profile view, edit, and avatar management.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Profile {

    /**
     * Constructor
     */
    public function __construct() {
        // Form processing
        add_action('hikmah_dashboard_process_update_profile', [$this, 'process_profile_update']);
        
        // AJAX handlers
        add_action('hikmah_dashboard_ajax_upload_avatar', [$this, 'ajax_upload_avatar']);
        add_action('hikmah_dashboard_ajax_remove_avatar', [$this, 'ajax_remove_avatar']);
        add_action('hikmah_dashboard_ajax_crop_avatar', [$this, 'ajax_crop_avatar']);
    }

    /**
     * Render profile tab
     *
     * @return void
     */
    public function render() {
        $user = wp_get_current_user();
        $dashboard = Hikmah_Dashboard::get_instance();

        // Profile fields
        $profile_data = $this->get_profile_data($user);
        ?>

        <div class="hikmah-tab-profile">

            <!-- Avatar Section -->
            <div class="hikmah-tab-profile__avatar-section">
                <?php $this->render_avatar_editor($user); ?>
            </div>

            <!-- Profile Form -->
            <form method="post" class="hikmah-tab-profile__form" id="hikmah-profile-form"
                  enctype="multipart/form-data">

                <?php wp_nonce_field('hikmah_update_profile', '_hikmah_profile_nonce'); ?>
                <input type="hidden" name="hikmah_dashboard_action" value="update_profile" />

                <!-- Basic Information -->
                <fieldset class="hikmah-tab-profile__fieldset">
                    <legend class="hikmah-tab-profile__legend">
                        <?php esc_html_e('Basic Information', 'hikmah-login'); ?>
                    </legend>

                    <div class="hikmah-form-row hikmah-form-row--two-col">
                        <!-- First Name -->
                        <div class="hikmah-form-group">
                            <label for="hikmah_first_name" class="hikmah-form-label">
                                <?php esc_html_e('First Name', 'hikmah-login'); ?>
                            </label>
                            <input type="text"
                                   id="hikmah_first_name"
                                   name="first_name"
                                   value="<?php echo esc_attr($profile_data['first_name']); ?>"
                                   class="hikmah-form-input"
                                   maxlength="100" />
                        </div>

                        <!-- Last Name -->
                        <div class="hikmah-form-group">
                            <label for="hikmah_last_name" class="hikmah-form-label">
                                <?php esc_html_e('Last Name', 'hikmah-login'); ?>
                            </label>
                            <input type="text"
                                   id="hikmah_last_name"
                                   name="last_name"
                                   value="<?php echo esc_attr($profile_data['last_name']); ?>"
                                   class="hikmah-form-input"
                                   maxlength="100" />
                        </div>
                    </div>

                    <!-- Display Name -->
                    <div class="hikmah-form-group">
                        <label for="hikmah_display_name" class="hikmah-form-label">
                            <?php esc_html_e('Display Name', 'hikmah-login'); ?>
                        </label>
                        <select id="hikmah_display_name" name="display_name" class="hikmah-form-select">
                            <?php
                            $display_options = $this->get_display_name_options($user);
                            foreach ($display_options as $option) :
                            ?>
                                <option value="<?php echo esc_attr($option); ?>"
                                    <?php selected($user->display_name, $option); ?>>
                                    <?php echo esc_html($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Nickname -->
                    <div class="hikmah-form-group">
                        <label for="hikmah_nickname" class="hikmah-form-label">
                            <?php esc_html_e('Nickname', 'hikmah-login'); ?>
                            <span class="hikmah-form-required">*</span>
                        </label>
                        <input type="text"
                               id="hikmah_nickname"
                               name="nickname"
                               value="<?php echo esc_attr($profile_data['nickname']); ?>"
                               class="hikmah-form-input"
                               required
                               maxlength="100" />
                    </div>

                </fieldset>

                <!-- Contact Information -->
                <fieldset class="hikmah-tab-profile__fieldset">
                    <legend class="hikmah-tab-profile__legend">
                        <?php esc_html_e('Contact Information', 'hikmah-login'); ?>
                    </legend>

                    <!-- Email -->
                    <div class="hikmah-form-group">
                        <label for="hikmah_email" class="hikmah-form-label">
                            <?php esc_html_e('Email Address', 'hikmah-login'); ?>
                            <span class="hikmah-form-required">*</span>
                        </label>
                        <input type="email"
                               id="hikmah_email"
                               name="email"
                               value="<?php echo esc_attr($user->user_email); ?>"
                               class="hikmah-form-input"
                               required />
                        <?php
                        $email_verified = get_user_meta($user->ID, '_hikmah_email_verified', true);
                        if ($email_verified) :
                        ?>
                            <span class="hikmah-form-help hikmah-form-help--success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Verified', 'hikmah-login'); ?>
                            </span>
                        <?php else : ?>
                            <span class="hikmah-form-help hikmah-form-help--warning">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('Not verified', 'hikmah-login'); ?>
                                — <a href="#" class="hikmah-resend-verification">
                                    <?php esc_html_e('Resend verification email', 'hikmah-login'); ?>
                                </a>
                            </span>
                        <?php endif; ?>
                        <p class="hikmah-form-description">
                            <?php esc_html_e('Changing your email will require re-verification.', 'hikmah-login'); ?>
                        </p>
                    </div>

                    <!-- Website -->
                    <div class="hikmah-form-group">
                        <label for="hikmah_url" class="hikmah-form-label">
                            <?php esc_html_e('Website', 'hikmah-login'); ?>
                        </label>
                        <input type="url"
                               id="hikmah_url"
                               name="url"
                               value="<?php echo esc_url($user->user_url); ?>"
                               class="hikmah-form-input"
                               placeholder="https://" />
                    </div>

                </fieldset>

                <!-- Bio / About -->
                <fieldset class="hikmah-tab-profile__fieldset">
                    <legend class="hikmah-tab-profile__legend">
                        <?php esc_html_e('About', 'hikmah-login'); ?>
                    </legend>

                    <div class="hikmah-form-group">
                        <label for="hikmah_description" class="hikmah-form-label">
                            <?php esc_html_e('Biographical Info', 'hikmah-login'); ?>
                        </label>
                        <textarea id="hikmah_description"
                                  name="description"
                                  class="hikmah-form-textarea"
                                  rows="5"
                                  maxlength="1000"><?php echo esc_textarea($profile_data['description']); ?></textarea>
                        <p class="hikmah-form-description">
                            <?php esc_html_e('Brief description about yourself. This may be shown publicly.', 'hikmah-login'); ?>
                        </p>
                    </div>
                </fieldset>

                <?php
                /**
                 * Action to add custom profile fields
                 *
                 * @param WP_User $user Current user.
                 */
                do_action('hikmah_dashboard_profile_fields', $user);
                ?>

                <!-- Submit -->
                <div class="hikmah-form-actions">
                    <button type="submit" class="hikmah-btn hikmah-btn--primary" id="hikmah-save-profile">
                        <span class="hikmah-btn__text"><?php esc_html_e('Save Profile', 'hikmah-login'); ?></span>
                        <span class="hikmah-btn__loading" style="display:none;">
                            <span class="hikmah-spinner"></span>
                            <?php esc_html_e('Saving...', 'hikmah-login'); ?>
                        </span>
                    </button>
                </div>

            </form>

        </div>

        <?php
    }

    /**
     * Render avatar editor section
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_avatar_editor($user) {
        $dashboard = Hikmah_Dashboard::get_instance();
        $avatar_url = $dashboard->get_user_avatar_url($user->ID, 150);
        $has_custom = !empty(get_user_meta($user->ID, '_hikmah_custom_avatar', true));
        ?>

        <div class="hikmah-avatar-editor" id="hikmah-avatar-editor">

            <div class="hikmah-avatar-editor__preview">
                <img src="<?php echo esc_url($avatar_url); ?>"
                     alt="<?php echo esc_attr($user->display_name); ?>"
                     class="hikmah-avatar-editor__image"
                     id="hikmah-avatar-preview" />
            </div>

            <div class="hikmah-avatar-editor__actions">
                <label for="hikmah-avatar-upload" class="hikmah-btn hikmah-btn--secondary hikmah-btn--sm">
                    <span class="dashicons dashicons-camera"></span>
                    <?php esc_html_e('Upload Photo', 'hikmah-login'); ?>
                </label>
                <input type="file"
                       id="hikmah-avatar-upload"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       class="hikmah-avatar-editor__file-input"
                       style="display:none;" />

                <?php if ($has_custom) : ?>
                    <button type="button"
                            class="hikmah-btn hikmah-btn--outline hikmah-btn--sm hikmah-btn--danger"
                            id="hikmah-avatar-remove">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Remove', 'hikmah-login'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <p class="hikmah-avatar-editor__help">
                <?php
                printf(
                    /* translators: %s: maximum file size */
                    esc_html__('JPG, PNG, GIF or WebP. Max %s.', 'hikmah-login'),
                    esc_html(size_format(wp_max_upload_size()))
                );
                ?>
            </p>

            <!-- Cropper Modal -->
            <div class="hikmah-avatar-editor__crop-modal" id="hikmah-crop-modal" style="display:none;">
                <div class="hikmah-avatar-editor__crop-modal-overlay"></div>
                <div class="hikmah-avatar-editor__crop-modal-content">
                    <div class="hikmah-avatar-editor__crop-modal-header">
                        <h4><?php esc_html_e('Crop Avatar', 'hikmah-login'); ?></h4>
                        <button type="button" class="hikmah-avatar-editor__crop-close" id="hikmah-crop-close">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="hikmah-avatar-editor__crop-area">
                        <img src="" alt="" id="hikmah-crop-image" />
                    </div>
                    <div class="hikmah-avatar-editor__crop-actions">
                        <button type="button" class="hikmah-btn hikmah-btn--secondary" id="hikmah-crop-cancel">
                            <?php esc_html_e('Cancel', 'hikmah-login'); ?>
                        </button>
                        <button type="button" class="hikmah-btn hikmah-btn--primary" id="hikmah-crop-save">
                            <?php esc_html_e('Save Avatar', 'hikmah-login'); ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <?php
    }

    /**
     * Get profile data for the form
     *
     * @param WP_User $user User object.
     * @return array Profile field values.
     */
    private function get_profile_data($user) {
        return [
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'nickname'    => $user->nickname,
            'description' => $user->description,
        ];
    }

    /**
     * Get display name options
     *
     * @param WP_User $user User object.
     * @return array Unique display name options.
     */
    private function get_display_name_options($user) {
        $options = [];

        $options[] = $user->user_login;

        if (!empty($user->first_name)) {
            $options[] = $user->first_name;
        }

        if (!empty($user->last_name)) {
            $options[] = $user->last_name;
        }

        if (!empty($user->first_name) && !empty($user->last_name)) {
            $options[] = $user->first_name . ' ' . $user->last_name;
            $options[] = $user->last_name . ' ' . $user->first_name;
        }

        if (!empty($user->nickname) && !in_array($user->nickname, $options, true)) {
            $options[] = $user->nickname;
        }

        return array_unique($options);
    }

    /**
     * Process profile update form submission
     *
     * @return void
     */
    public function process_profile_update() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_hikmah_profile_nonce'] ?? '', 'hikmah_update_profile')) {
            Hikmah_Dashboard::set_notice('error', __('Security check failed.', 'hikmah-login'));
            return;
        }

        $user_id = get_current_user_id();
        $user    = get_userdata($user_id);

        if (!$user) {
            return;
        }

        // Sanitize inputs
        $first_name   = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name    = sanitize_text_field($_POST['last_name'] ?? '');
        $nickname     = sanitize_text_field($_POST['nickname'] ?? '');
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $email        = sanitize_email($_POST['email'] ?? '');
        $url          = esc_url_raw($_POST['url'] ?? '');
        $description  = sanitize_textarea_field($_POST['description'] ?? '');

        // Validate nickname
        if (empty($nickname)) {
            Hikmah_Dashboard::set_notice('error', __('Nickname is required.', 'hikmah-login'));
            return;
        }

        // Validate email
        if (empty($email) || !is_email($email)) {
            Hikmah_Dashboard::set_notice('error', __('Please enter a valid email address.', 'hikmah-login'));
            return;
        }

        // Check if email is taken by another user
        $email_user = get_user_by('email', $email);
        if ($email_user && $email_user->ID !== $user_id) {
            Hikmah_Dashboard::set_notice('error', __('This email is already in use by another account.', 'hikmah-login'));
            return;
        }

        // Track email change for re-verification
        $email_changed = ($email !== $user->user_email);

        // Build update data
        $userdata = [
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'nickname'     => $nickname,
            'display_name' => $display_name,
            'user_email'   => $email,
            'user_url'     => $url,
            'description'  => $description,
        ];

        /**
         * Filter profile update data before saving
         *
         * @param array $userdata  User data array.
         * @param int   $user_id   User ID.
         * @param array $_POST     Raw POST data.
         */
        $userdata = apply_filters('hikmah_profile_update_data', $userdata, $user_id, $_POST);

        // Update user
        $result = wp_update_user($userdata);

        if (is_wp_error($result)) {
            Hikmah_Dashboard::set_notice('error', $result->get_error_message());
            return;
        }

        // Handle email change verification
        if ($email_changed) {
            update_user_meta($user_id, '_hikmah_email_verified', false);

            /**
             * Action when user email is changed via dashboard
             *
             * @param int    $user_id   User ID.
             * @param string $email     New email.
             * @param string $old_email Previous email.
             */
            do_action('hikmah_user_email_changed', $user_id, $email, $user->user_email);
        }

        /**
         * Action after profile is updated
         *
         * @param int   $user_id  User ID.
         * @param array $userdata Updated data.
         */
        do_action('hikmah_profile_updated', $user_id, $userdata);

        Hikmah_Dashboard::set_notice('success', __('Profile updated successfully.', 'hikmah-login'));

        // Redirect to prevent form resubmission
        wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('profile', [
            'notice' => 'success',
            'message' => urlencode(__('Profile updated successfully.', 'hikmah-login')),
        ]));
        exit;
    }

    /**
     * AJAX: Upload avatar
     *
     * @return void
     */
    public function ajax_upload_avatar() {
        $user_id = get_current_user_id();

        // Check for uploaded file
        if (empty($_FILES['avatar'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'hikmah-login')]);
        }

        $file = $_FILES['avatar'];

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload JPG, PNG, GIF, or WebP.', 'hikmah-login')]);
        }

        // Validate file size (max 5MB)
        $max_size = min(5 * MB_IN_BYTES, wp_max_upload_size());
        if ($file['size'] > $max_size) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: maximum file size */
                    __('File too large. Maximum size is %s.', 'hikmah-login'),
                    size_format($max_size)
                ),
            ]);
        }

        // Use WordPress media handler
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Handle upload
        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        }

        // Return the image URL for cropping
        $image_url = wp_get_attachment_image_url($attachment_id, 'medium');

        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'url'           => $image_url,
            'message'       => __('Image uploaded. Please crop your avatar.', 'hikmah-login'),
        ]);
    }

    /**
     * AJAX: Crop and save avatar
     *
     * @return void
     */
    public function ajax_crop_avatar() {
        $user_id       = get_current_user_id();
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        $crop_data     = [
            'x'      => floatval($_POST['crop_x'] ?? 0),
            'y'      => floatval($_POST['crop_y'] ?? 0),
            'width'  => floatval($_POST['crop_width'] ?? 0),
            'height' => floatval($_POST['crop_height'] ?? 0),
        ];

        if (!$attachment_id) {
            wp_send_json_error(['message' => __('Invalid attachment.', 'hikmah-login')]);
        }

        // Verify attachment exists and user owns it
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_author != $user_id) {
            wp_send_json_error(['message' => __('Unauthorized.', 'hikmah-login')]);
        }

        // Get file path
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(['message' => __('File not found.', 'hikmah-login')]);
        }

        // Crop image
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            wp_send_json_error(['message' => $editor->get_error_message()]);
        }

        $editor->crop(
            $crop_data['x'],
            $crop_data['y'],
            $crop_data['width'],
            $crop_data['height'],
            300, // Output width
            300  // Output height
        );

        // Generate new filename
        $cropped_file = $editor->generate_filename('avatar');
        $saved = $editor->save($cropped_file);

        if (is_wp_error($saved)) {
            wp_send_json_error(['message' => $saved->get_error_message()]);
        }

        // Create new attachment for cropped image
        $cropped_attachment = [
            'post_mime_type' => $saved['mime-type'],
            'post_title'    => sprintf('avatar-%d', $user_id),
            'post_content'  => '',
            'post_status'   => 'inherit',
        ];

        $cropped_id = wp_insert_attachment($cropped_attachment, $saved['path']);

        if (is_wp_error($cropped_id)) {
            wp_send_json_error(['message' => __('Failed to save avatar.', 'hikmah-login')]);
        }

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($cropped_id, $saved['path']);
        wp_update_attachment_metadata($cropped_id, $metadata);

        // Delete old custom avatar attachment
        $old_avatar = get_user_meta($user_id, '_hikmah_custom_avatar', true);
        if ($old_avatar && $old_avatar != $cropped_id) {
            wp_delete_attachment($old_avatar, true);
        }

        // Also delete the uncropped upload if different
        if ($attachment_id != $cropped_id) {
            wp_delete_attachment($attachment_id, true);
        }

        // Save as user avatar
        update_user_meta($user_id, '_hikmah_custom_avatar', $cropped_id);

        $avatar_url = wp_get_attachment_image_url($cropped_id, [150, 150]);

        /**
         * Action after avatar is updated
         *
         * @param int $user_id    User ID.
         * @param int $cropped_id Attachment ID.
         */
        do_action('hikmah_avatar_updated', $user_id, $cropped_id);

        wp_send_json_success([
            'url'     => $avatar_url,
            'message' => __('Avatar updated successfully.', 'hikmah-login'),
        ]);
    }

    /**
     * AJAX: Remove custom avatar
     *
     * @return void
     */
    public function ajax_remove_avatar() {
        $user_id = get_current_user_id();

        $avatar_id = get_user_meta($user_id, '_hikmah_custom_avatar', true);

        if ($avatar_id) {
            wp_delete_attachment($avatar_id, true);
            delete_user_meta($user_id, '_hikmah_custom_avatar');
        }

        // Return gravatar URL
        $gravatar_url = get_avatar_url($user_id, ['size' => 150]);

        /**
         * Action after avatar is removed
         *
         * @param int $user_id User ID.
         */
        do_action('hikmah_avatar_removed', $user_id);

        wp_send_json_success([
            'url'     => $gravatar_url,
            'message' => __('Avatar removed. Using default Gravatar.', 'hikmah-login'),
        ]);
    }
}
