<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Toolkit_For_ChatGPT_Plugins')) {
    class Toolkit_For_ChatGPT_Plugins
    {
        private $plugin_dir;

        public function __construct()
        {
            $this->plugin_dir = plugin_dir_path(__FILE__);
            register_activation_hook(__FILE__, array($this, 'activate_plugin'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

            if (is_admin()) {
                add_action('admin_menu', array($this, 'add_admin_menu'));
                add_action('admin_init', array($this, 'register_settings'));
                add_action('admin_enqueue_scripts', array($this, 'scripts'));
            }

        }

        // Activate the plugin and check for conflicts
        public function activate_plugin()
        {
            $this->check_and_create_directory();
            $this->manage_files();
        }

        // Deactivate the plugin and clean up
        public function deactivate_plugin()
        {
            // TODO: Add an option to the settings menu to decide whether the files should be deleted on uninstall
            //$this->remove_files();
        }

        private function check_and_create_directory()
        {
            $well_known_dir = ABSPATH . '/.well-known';
    
            if (!file_exists($well_known_dir)) {
                if (!mkdir($well_known_dir, 0755)) {
                    deactivate_plugins(plugin_basename(__FILE__));
                    wp_die(__('Error creating the /.well-known/ directory. Please check permissions and try again.', 'toolkit-for-chatgpt-plugins'));
                }
            }
        }

        private function manage_files($update = false)
        {
            $domain = get_site_url();

            $index_php = file_get_contents($this->plugin_dir . '../templates/index.php');
            $ai_plugin_json = file_get_contents($this->plugin_dir . '../templates/ai-plugin.json');
            $openapi_yaml = file_get_contents($this->plugin_dir . '../templates/openapi.yaml');
            $openapi_yaml_woocommerce_stub = file_get_contents($this->plugin_dir . '../templates/stubs/openapi-woocommerce.yaml');
            $openapi_yaml_wordress_stub = file_get_contents($this->plugin_dir . '../templates/stubs/openapi-wordpress.yaml');

            if ($update) {
                // Load the settings from the database
                $settings = $this->get_settings();
        
                // Update the contents with the correct fields
                $plugin_name_for_human = json_encode($settings['plugin_name_for_human']);
                $plugin_name_for_model = json_encode($settings['plugin_name_for_model']);
                $description_for_human = json_encode($settings['description_for_human']);
                $description_for_model = json_encode($settings['description_for_model']);
                $contact_email = json_encode($settings['contact_email']);
                $legal_url = json_encode($settings['legal_url']);
            } else {

                $site_name = esc_attr( get_bloginfo('name') );
                $site_url = esc_attr( site_url() );
                $site_email = esc_attr( get_bloginfo('admin_email') );
                $site_domain = parse_url($site_url, PHP_URL_HOST);
                $slug = sanitize_title($site_name);

                // Default values for file creation
                $plugin_name_for_human = $site_name;
                $plugin_name_for_model = $slug . "-chatgpt-plugin";

                if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
                    $description_for_human = "A ChatGPT plugin for " . $site_name . ", making it easy to 'chat' with our products and posts.";
                    $description_for_model = "Plugin for searching for posts and products on " . $site_name;
                } else {
                    $description_for_human = "A ChatGPT plugin for " . $site_name . ", making it easy to 'chat' with our posts.";
                    $description_for_model = "Plugin for searching for posts on " . $site_name;
                }

                $contact_email = $site_email;
                $legal_url = $site_domain . "/legal";
            }

            // Prepare the ai-plugin.json file
            $ai_plugin_json = str_replace("PLUGIN_NAME_FOR_HUMAN", $plugin_name_for_human, $ai_plugin_json);
            $ai_plugin_json = str_replace("PLUGIN_NAME_FOR_MODEL", $plugin_name_for_model, $ai_plugin_json);
            $ai_plugin_json = str_replace("DESCRIPTION_FOR_HUMAN", $description_for_human, $ai_plugin_json);
            $ai_plugin_json = str_replace("DESCRIPTION_FOR_MODEL", $description_for_model, $ai_plugin_json);
            $ai_plugin_json = str_replace("DOMAIN", $domain, $ai_plugin_json);
            $ai_plugin_json = str_replace("CONTACT_EMAIL", $contact_email, $ai_plugin_json);
            $ai_plugin_json = str_replace("LEGAL_URL", $legal_url, $ai_plugin_json);

            // Prepare the openapi.yaml content
            $openapi_yaml = str_replace("PLUGIN_NAME_FOR_HUMAN", $plugin_name_for_human, $openapi_yaml);
            $openapi_yaml = str_replace("DESCRIPTION_FOR_HUMAN", $description_for_human, $openapi_yaml);
            $openapi_yaml = str_replace("DOMAIN", $domain, $openapi_yaml);

            if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
                $openapi_yaml .= "\n".$openapi_yaml_woocommerce_stub;
            }

            $openapi_yaml .= "\n".$openapi_yaml_wordress_stub;

            $file_structure = array(
                '/.well-known/index.php' => $index_php,
                '/.well-known/ai-plugin.json' => $ai_plugin_json,
                '/.well-known/openapi.yaml' => $openapi_yaml,
            );

            //$this->write_log($file_structure);

            foreach ($file_structure as $file => $content) {
                $file_path = ABSPATH . $file;
                file_put_contents($file_path, $content);
            }

            // Add default logo.png if not exists
            $logo_path = ABSPATH . '/.well-known/logo.png';
            if (!file_exists($logo_path)) {
                copy($this->plugin_dir .'templates/logo.png', $logo_path);
            }
        }

        private function remove_files()
        {
            $files_to_remove = array(
                '/.well-known/index.php',
                '/.well-known/ai-plugin.json',
                '/.well-known/openapi.yaml',
                '/.well-known/logo.png'
            );

            foreach ($files_to_remove as $file) {
                $file_path = ABSPATH . $file;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }

        public function add_admin_menu()
        {
            add_options_page(
                __('Toolkit for ChatGPT Plugins', 'toolkit-for-chatgpt-plugins'),
                __('ChatGPT Plugin', 'toolkit-for-chatgpt-plugins'),
                'manage_options',
                'toolkit-for-chatgpt-plugins',
                array($this, 'settings_page')
            );

            //add_submenu_page( 'woocommerce', 'ChatGPT Plugin Settings', 'ChatGPT Plugin', 'manage_options', 'toolkit-for-chatgpt-plugins', [$this, 'settings_page' ] );

        }

        public function register_settings()
        {
            register_setting('toolkit-for-chatgpt-plugins', 'chatgpt_plugin_settings', array($this, 'sanitize_settings'));

            add_settings_section(
                'chatgpt-plugin-general',
                __('', 'toolkit-for-chatgpt-plugins'),
                null,
                'toolkit-for-chatgpt-plugins'
            );
        
            // Add settings fields for each variable
            $fields = array(
                'plugin_name_for_human' => __('Plugin Name for Human', 'toolkit-for-chatgpt-plugins'),
                'plugin_name_for_model' => __('Plugin Name for Model', 'toolkit-for-chatgpt-plugins'),
                'description_for_human' => __('Description for Human', 'toolkit-for-chatgpt-plugins'),
                'description_for_model' => __('Description for Model', 'toolkit-for-chatgpt-plugins'),
                'contact_email' => __('Contact Email', 'toolkit-for-chatgpt-plugins'),
                'legal_url' => __('Legal URL', 'toolkit-for-chatgpt-plugins'),
            );
        
            foreach ($fields as $field_id => $field_label) {
                add_settings_field(
                    "chatgpt-plugin-{$field_id}",
                    $field_label,
                    array($this, "settings_{$field_id}_field"),
                    'toolkit-for-chatgpt-plugins',
                    'chatgpt-plugin-general'
                );
            }

            add_settings_field(
                "chatgpt-plugin-plugin_logo",
                __('Logo (PNG)', 'toolkit-for-chatgpt-plugins'),
                array($this, "settings_logo_field"),
                'toolkit-for-chatgpt-plugins',
                'chatgpt-plugin-general'
            );

            // Add action to update files after settings are saved
            // NOTE: This will only trigger if the values are changed
            add_action('update_option_chatgpt_plugin_settings', array($this, 'on_update_option_chatgpt_plugin_settings'), 10, 1);
            
        }

        public function sanitize_settings($input)
        {
            $sanitized_input = array();

            //$this->write_log($input);
        
            foreach ($input as $key => $value) {
                if ($key === 'contact_email') {
                    $sanitized_input[$key] = sanitize_email($value);
                } elseif ($key === 'legal_url') {
                    $sanitized_input[$key] = esc_url_raw($value);
                } elseif ($key === 'plugin_logo') {
                    $sanitized_input[$key] = esc_url_raw($value);

                    // Download the image and save it to the logo.png file
                    $response = wp_remote_get($sanitized_input[$key], array('timeout' => 30));
                    //$this->write_log($response);

                    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {

                        $logo_path = ABSPATH . '/.well-known/logo.png';
                        $image_data = wp_remote_retrieve_body($response);
                        $file_info = new finfo(FILEINFO_MIME_TYPE);
                        $mime_type = $file_info->buffer($image_data);
                    
                        if ($mime_type === 'image/png') {

                            if (file_exists($logo_path)) {
                                unlink($logo_path);
                            }

                            file_put_contents($logo_path, $image_data);
                        
                        } else {
                            // Do nothing!
                        }

                    }

                } else {
                    $sanitized_input[$key] = sanitize_text_field($value);
                }
            }
        
            return $sanitized_input;
        }

        // Trigger file re-creation
        public function on_update_option_chatgpt_plugin_settings($values)
        {
            //$this->write_log($values);
            $this->manage_files(true);
        }

        public function settings_plugin_name_for_human_field()
        {
            $this->render_text_field('plugin_name_for_human');
            $this->render_field_description(__('	Human-readable name, such as your website name.', 'toolkit-for-chatgpt-plugins'));
        }

        public function settings_plugin_name_for_model_field()
        {
            $this->render_text_field('plugin_name_for_model');
            $this->render_field_description(__('Name the model will use to target the plugin. Recommend keeping this as a-z and dashes, with no spaces (e.g. your-store-name)', 'toolkit-for-chatgpt-plugins'));
        }

        public function settings_description_for_human_field()
        {
            $this->render_text_field('description_for_human');
            $this->render_field_description(__('Human-readable description of the plugin', 'toolkit-for-chatgpt-plugins'));
        }

        public function settings_description_for_model_field()
        {
            $this->render_text_field('description_for_model');
            $this->render_field_description(__('Description better tailored to the model, such as token context length considerations or keyword usage for improved plugin prompting.', 'toolkit-for-chatgpt-plugins'));
        }

        public function settings_contact_email_field()
        {
            $this->render_text_field('contact_email');
            $this->render_field_description(__('Email contact for safety/moderation reachout, support, and deactivation.', 'toolkit-for-chatgpt-plugins'));
        }

        public function settings_legal_url_field()
        {
            $this->render_text_field('legal_url');
            $this->render_field_description(__('Redirect URL for users to view the terms and conditions / plugin information', 'toolkit-for-chatgpt-plugins'));
        }

        public function settings_logo_field()
        {


            $settings = $this->get_settings();
            $value = isset($settings["plugin_logo"]) ? $settings["plugin_logo"] : '';
            ?>
            <img src="<?php echo esc_attr($value) ?>" id="chatgpt-plugin_logo-preview" style="max-width: 100px; max-height: 100px; display: block; margin-bottom: 10px;" />
            <input type="hidden" id="chatgpt-plugin_logo" name="chatgpt_plugin_settings[plugin_logo]" value="<?php echo esc_attr($value) ?>">
            <button type="button" id="upload-media-button" class="button">Select logo from Media Library</button>
            <?php

            $this->render_field_description(__('Logo that will show on ChatGPT. We recommend a square image at least 200 x 200px.', 'toolkit-for-chatgpt-plugins'));

        }

        private function render_text_field($field_id)
        {
            $settings = $this->get_settings();
            $value = isset($settings[$field_id]) ? $settings[$field_id] : '';

            echo '<input type="text" id="chatgpt-plugin-' . esc_attr($field_id) . '" name="chatgpt_plugin_settings[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" />';
        }

        public function get_settings()
        {
            return get_option('chatgpt_plugin_settings', array());
        }

        public function settings_page()
        {

            $site_url = get_bloginfo('url');
            $site_domain = parse_url($site_url, PHP_URL_HOST);

            ?>
            <div class="wrap" id="toolkit-for-chatgpt-plugins">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>        
                <form action="options.php" method="post" enctype="multipart/form-data">
                    <?php settings_fields('toolkit-for-chatgpt-plugins'); ?>
                    <p>Welcome! This toolkit will enable you to add your website to ChatGPT's Plugin directory so that users can "chat" with your posts and products.</p>
                <div class="container">
                    <div>
                        <h3>Your ChatGPT Plugin links</h3>
                        <ul>
                            <li><a href="/.well-known/openapi.yaml">OpenAPI File</a></li>
                            <li><a href="/.well-known/ai-plugin.json" target="_blank">Manifest File</a></li>
                            <li><a href="/.well-known/logo.png" target="_blank">Logo File</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3>Useful ChatGPT links</h3>
                        <ul>
                            <li><a href="https://openai.com/waitlist/plugins">Join the ChatGPT Plugins waitlist</a></li>
                            <li><a href="https://platform.openai.com/docs/plugins/introduction" target="_blank">ChatGPT Plugins documentation</a></li>
                        </ul>
                    </div>

                </div>

                <div id="instructions">
                    <h3>Activating your plugin</h3>
                    <p>ChatGPT Plugins are currently in an alpha release, so only users <a href="https://openai.com/waitlist/plugins">who have been approved</a> can install and use them. If you have been approved, you can install your plugin by following the instructions below:</p>

                    <ul>
                        <li>Login to ChatGPT and from the <strong>Plugins</strong> dropdown, choose <strong>Plugin Store</strong></li>
                        <li>Choose <strong>Develop your own plugin</strong></li>
                        <li>Enter the url of your site, without the https, i.e. <strong><?php echo esc_attr( $site_domain ) ?></strong> and click <strong>Find manifest file</strong></li>
                        <li>Continue through the prompts, and your plugin will now be available</li>
                    </ul>

                    <a href="mailto:support@dcsdigital.co.uk?subject=ChatGPT Plugin Installation for <?php echo esc_attr( get_bloginfo('name') ); ?> (<?php echo esc_attr( get_bloginfo('url') ); ?>)" class="button button-primary">Need help? E-mail us!</a>
                </div>
                <br/>
                <h3>Settings</h3>
                    <table class="form-table">
                        <?php do_settings_sections('toolkit-for-chatgpt-plugins'); ?>
                    </table>
        
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }

        public function render_field_description($description)
        {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        
        
        public function scripts()
        {
            wp_enqueue_media();
            wp_enqueue_style('chatgpt_plugin_admin_css', plugin_dir_url(__FILE__) . '../css/admin.css', [], '1.0.0');
            wp_enqueue_script('chatgpt_plugin_admin_js', plugin_dir_url(__FILE__) . '../js/admin.js', array('jquery'), '1.0.0', true);
        }

        function write_log($log) {
            if (true === WP_DEBUG) {
                if (is_array($log) || is_object($log)) {
                    error_log(print_r($log, true));
                } else {
                    error_log($log);
                }
            }
        }
    }

    $admin = new Toolkit_For_ChatGPT_Plugins();
}
