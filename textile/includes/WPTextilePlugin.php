<?php

/*
*   A helper class to interact with Textile API
*/

class WPTextilePlugin {

    private $menu_slug;

    /*
    *   Add action hooks and set initial values
    */
    public function __construct() {
        $this->menu_slug = 'wptextile';
        $this->page_title = 'Textile Tools for Wordpress';
        $this->menu_title = 'Textile Tools';

        // Ajax
        add_action('wp_ajax_textilepostslist', array($this, 'ajax_posts_list'));

        // Admin
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init_actions'));
            add_action('admin_menu', array($this, 'admin_page_hook'));
        } else {
            add_action('init', array($this, 'init_actions'));
        }
        
    }


    /*
    *   Ajax call
    *   Prints the list of posts in json
    */
    public function ajax_posts_list($data) {
        $filter = !empty($_POST['filter']) ? $_POST['filter'] : '';
        $res = array();
        $posts = array();

        // Return all posts
        if ($filter === 'all') {

            // The Query
            $args = array(
                'numberposts'      => 30,
                'category'         => 0,
                'orderby'          => 'date',
                'order'            => 'DESC',
                'include'          => array(),
                'exclude'          => array(),
                'meta_key'         => '',
                'meta_value'       => '',
                'post_type'        => 'post',
                'suppress_filters' => true,
                'post_status'    => 'publish',
            );
            // slug
            // if ($slug)
            // args['name'] = $slug;

            $posts = get_posts($args);
        }

        $res = $this->ajax_post_list_template_post($posts);
        echo json_encode($res);

        wp_die();
    }

    /*
    *   Format post data
    */
    private function ajax_post_list_template_post($posts) {
        $res = [];
        foreach ($posts as $post) {
            $post = (array) $post;
            $res[] = [
                'id' => $post['ID'],
                'post_date_gmt' => $post['post_date_gmt'],
                'post_content' => $post['post_content'],
                'post_title' => $post['post_title'],
                'post_name' => $post['post_name'],
                'post_modified_gmt' => $post['post_modified_gmt']
            ];
        }

        return $res;
        
    }

    /*
    *   ADMIN SECTION
    *   Enqueues JS and CSS scripts
    */
    public function admin_enqueue_scripts( $hook ) {
        // My custom page
        if( 'plugins_page_wptextile' != $hook ) return;

        // CSS
        $css_dependencies = array();
        $css_version = false;
        $css_in_footer = false;
        wp_enqueue_style(
            'wptextileplugin_css',
            plugins_url( '../admin/css/wptextileplugin_admin.css', __FILE__ ),
            $css_dependencies,
            $css_version
        );
        
        // JS Scripts
        $version = false;
        $in_footer = true;
        $dependencies = array();
        wp_enqueue_script(
            'wptextileplugin_admin_js',
            plugins_url( '../admin/js/wptextileplugin_admin.js', __FILE__ ),
            $dependencies,
            $version,
            $in_footer
        );
        $title_nonce = wp_create_nonce( 'title_example' );
        $textile_config = get_option('wptextile_options');
        $apikey = !empty($textile_config['wptextile_userdata_apikey']) ?
            $textile_config['wptextile_userdata_apikey'] : '';
        $apisecret = !empty($textile_config['wptextile_userdata_apisecret']) ?
            $textile_config['wptextile_userdata_apisecret'] : '';
        $privateidentity = !empty($textile_config['wptextile_userdata_privateidentity']) ?
            $textile_config['wptextile_userdata_privateidentity'] : '';
        
            
        wp_localize_script(
            'wptextileplugin_admin_js', 'TEXTILE_AJAX_OBJ', 
            array(
               'ajax_url' => admin_url( 'admin-ajax.php' ),
               'nonce'    => $title_nonce,
               'apikey' => $apikey,
               'apisecret' => $apisecret,
               'privateidentity' => $privateidentity
            )
        );
        
    }

    /*
    *   Enqueues JS and CSS scripts
    */
    public function enqueue_scripts() {
        // JS Scripts
        $version = false;
        $in_footer = true;
        $dependencies = array('jquery');
        wp_enqueue_script(
            'wptextileplugin_js',
            plugins_url( '../public/js/wptextileplugin.js', __FILE__ ),
            $dependencies,
            $version,
            $in_footer
        );
        $title_nonce = wp_create_nonce( 'title_example' );
        $apikey = get_option('wptextile_options');
        wp_localize_script( 'wptextileplugin_js', 'TEXTILE_AJAX_OBJ', array(
           'ajax_url' => admin_url( 'admin-ajax.php' ),
           'nonce'    => $title_nonce,
           'apikey' => $apikey
        ));

        // CSS
        $css_dependencies = array();
        $css_version = false;
        $css_in_footer = false;
        wp_enqueue_style(
            'wptextileplugin_css',
            plugins_url( '../public/css/wptextileplugin.css', __FILE__ ),
            $css_dependencies,
            $css_version
        );
    }

    /*
    *   Admin section
    */
    public function admin_page_hook()
    {
        // add_submenu_page
        // add_management_page()
        $hook = add_plugins_page(
            __( $this->page_title, 'textdomain' ),
            __( $this->menu_title, 'textdomain' ),
            'manage_options',
            $this->menu_slug,
            array($this, 'admin_page_html')
        );

        // add_action( 'load-' . $hook, array($this, 'admin_page_form_submit'));

    }

    public function admin_page_init_settings()
    {
        // Register a new setting for my plugins page.
        $new_option = 'wptextile_options';
        register_setting( $this->menu_slug, $new_option);
     
        // Register a new section in my plugins page.
        $section = 'wptextile_section_userdata';
        add_settings_section(
            $section,
            'User data',
            array($this, 'admin_page_settings_section_userdata_html'),
            $this->menu_slug
        );
     
        // Register a new field in the "wptextile_section_userdata" section, inside my page
        add_settings_field(
            'wptextile_userdata_apikey', // As of WP 4.6 this value is used only internally.
                                    // Use $args' label_for to populate the id inside the callback.
            'API KEY:',
            array($this, 'admin_page_settings_add_field_userdata'),
            $this->menu_slug,
            $section,
            array(
                'label_for'         => 'wptextile_userdata_apikey',
                'class'             => 'wptextile_row',
                'wptextile_attribute' => $new_option,
                'required' => true,
                'type' => 'text'
            )
        );

        // Register a new field 
        add_settings_field(
            'wptextile_userdata_apisecret', 
            'API SECRET:',
            array($this, 'admin_page_settings_add_field_userdata'),
            $this->menu_slug,
            $section,
            array(
                'label_for'         => 'wptextile_userdata_apisecret',
                'class'             => 'wptextile_row',
                'wptextile_attribute' => $new_option,
                'required' => true,
                'type' => 'text'
            )
        );

        // Register a new field
        add_settings_field(
            'wptextile_userdata_typeofapikey',
            'TYPE OF API KEYS:',
            array($this, 'admin_page_settings_add_field_userdata'),
            $this->menu_slug,
            $section,
            array(
                'label_for'         => 'wptextile_userdata_typeofapikey',
                'class'             => 'wptextile_row',
                'wptextile_attribute' => $new_option,
                'disabled' => true,
                'required' => false,
                'type' => 'radio',
                'radio_options' => array(
                    'account_key' => 'Account keys',
                    'user_group_key' => 'User group keys'
                ),
                'radio_default_value' => 'account_key'
            )
        );


        // Register a new field
        add_settings_field(
            'wptextile_userdata_privateidentity',
            'PRIVATE IDENTITY:',
            array($this, 'admin_page_settings_add_field_userdata'),
            $this->menu_slug,
            $section,
            array(
                'label_for'         => 'wptextile_userdata_privateidentity',
                'class'             => 'wptextile_row',
                'wptextile_attribute' => $new_option,
                'disabled' => true,
                'required' => false,
                'type' => 'text'
            )
        );

        

       

        
       
    }

    /*
    *   Admin section
    */
    public function admin_page_settings_section_userdata_html($args) {
        echo '<p id="'. esc_attr( $args['id'] ) . ' ">You need Textile API KEYS to use this demo. Please follow the instructions on the next link to get your keys: <a href="https://docs.textile.io/hub/apis/#api-keys" target="_blank">https://docs.textile.io/hub/apis/#api-keys</a></p>';
        // esc_html_e( json_encode($args), $this->menu_slug );
    }

    /*
    *   Admin section
    */
    public function admin_page_settings_add_field_userdata($args) {
        $id = !empty($args['label_for']) ? esc_attr($args['label_for']) : '';
        $attribute = !empty($args['wptextile_attribute']) ? esc_attr($args['wptextile_attribute']) : '';
        $disabled = !empty($args['disabled']) ? ' readonly="readonly" ' : '';
        $required = !empty($args['required']) ? ' required ' : '';

        $value = get_option( $attribute );
        $id_attr = $attribute.'['.$id.']';
        $value = !empty($value[$id]) ? esc_attr($value[$id]) : '';

        $res = '';
        $type = !empty($args['type']) ? esc_attr($args['type']) : '';
        if ($type === 'text') {
            $res = '<input '. $disabled . ' '. $required . ' name="' .
                $id_attr . '" id="' . $id .
                '" type="' . $type . '" class="regular-text" value="' . $value .
                '" />';
        } else if ($type === 'radio') {
            $radio_options = !empty($args['radio_options']) ?
                $args['radio_options'] : [];

            foreach ($radio_options as $radio_key => $radio_value) {
                $radio_default_value = !empty($args['radio_default_value']) ?
                    $args['radio_default_value'] : '';

                // Default value if $value is empty
                $checked = $value === $radio_key || 
                    (empty($value) && $radio_key === $radio_default_value) ? 
                    'checked' : 
                    '';

                $composed_id = $id . '_' . $radio_key;
                $res .= '<label>';

                $res .= '<input name="' . $id_attr . '" id="' . $composed_id .
                    '" type="' . $type . '" value="' . $radio_key .
                    '" ' . $checked . ' /> ' . $radio_value;
                $res .= '</label>&nbsp;&nbsp;';
            }
            
        }

        echo $res;
       
    }

    /*
    *   Admin section
    */
    public function admin_page_html() {
        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $img_src = plugins_url( '../admin/img/textile.svg', __FILE__ );
        $img_logo = '<img style="float: left; margin-top: 10px" width="22px" src="'.$img_src.'" alt="Textile" />';

        echo '<div class="wrap">';
        echo $img_logo;
        echo '<h1 style="margin-left: 30px">';
        echo esc_html( get_admin_page_title() );
        echo '</h1>';
        echo '<form action="options.php" method="post">';

        // output security fields for the registered setting "wporg"
        settings_fields( $this->menu_slug );
        // output setting sections and their fields
        // (sections are registered for "wporg", each field is registered to a specific section)
        do_settings_sections( $this->menu_slug );

        // output save settings button
        submit_button( __( 'Save Settings', 'textdomain' ) );

        echo '<input class="button button-warning" type="button" id="textile_btn_generate_new_identity" value="GENERATE NEW IDENTITY">';
        echo '<label id="wptextile_userdata_privateidentity_label" style="cursor:pointer; margin-left: 10px;"><input type="checkbox" id="wptextile_userdata_privateidentity_chk" /> Edit private identity field</label>';
        echo '</form>';

        echo '<br>';
        // General RESULT AREA
        echo '<div id="wptextile_result_area" class="container">';
        echo '</div>';

        echo '<br>';
        echo '<br>';
        // Tabs
        echo $this->admin_page_html_tabs();

        echo '</div>';

    }

    private function admin_page_html_tabs() {
        $content = '';
        $content .= '<div id="wptextile_tabs_area">
        <!-- Tabs menu -->
        <ul class="wptextile_tabs_menu">
            <li><a data-tab="info" class="main">Info</a></li><li><a data-tab="archive">Archive</a></li><li><a data-tab="buckets">Buckets</a></li>
        </ul>
        <!-- Tabs content -->
        <div class="wptextile_tabs_container">
            <!-- Info tab -->
            <div class="wptextile_tab_content info">
                <h2 class="tab_title">Welcome back!</h2>
                <div class="wptextile_tabs_content_info_body">
                    
                </div>
                
            </div>

            <!-- TAB: Archive -->
            <div class="wptextile_tab_content archive hide">
                <h2 class="tab_title">Archive: Static Site Generator</h2>
                <!-- Bucket settings -->
                <div id="wptextile_archive_section_bucket_settings">
                    <h3>Step 1. Configure bucket settings</h3>
                    <div>
                        <p>
                            Buckets:
                            <input class="button button-primary" type="button" id="textile_archive_btn_get_buckets" value="GET BUCKETS">
                        </p>
                        <div id="wptextile_archive_section_bucket_settings_bucklist">
                        </div>
                    </div>
                    <div style="text-align: center">
                        <label>
                            Bucket Name
                            <input class="regular-text" type="text" id="textile_archive_txt_bucket_name" value="">
                        </label>
                        <input class="button button-primary" type="button" id="textile_archive_btn_activate_posts" value="SAVE AND GO TO NEXT STEP">
                    </div>
                    <div id="textile_archive_div_bucket_gen_info"></div>
                    
                </div>
                <!-- Posts list -->
                <div id="wptextile_archive_section_post_list" class="hide">
                    <h3>Step 2. Create and upload index files:</h3>
                    <p>
                        <input class="button button-primary" type="button" id="textile_archive_btn_generate_index" value="CREATE INDEX FILES">
                    </p>
                    
                    <h3>Step 3. Posts list</h3>
                    <p>
                        Get last 30 posts from database:
                        <input class="button button-primary" type="button" id="textile_archive_btn_get_posts" value="GET POSTS">
                    </p>
                        
                    <br>
                    <!-- Post results -->
                    <div id="textile_archive_div_results">
                        --
                    </div>
                </div>

            </div>

    
            <!-- Buckets tab -->
            <div class="wptextile_tab_content buckets hide">
                <h2 class="tab_title">Buckets</h2>

                <!-- Bucket settings -->
                <h3>Get buckets:</h3>
                <p>
                    Buckets:
                    <input class="button button-primary" type="button" id="textile_buckets_btn_get_buckets" value="GET BUCKETS">
                </p>
                <div class="wptextile_col_6">
                    <h3 class="wptextile_text-center">Buckets</h3>
                    <div id="wptextile_tab_content_buckets_results_bucketsAuto">
                    </div>
                </div>
                <div class="wptextile_col_6">
                    <h3 class="wptextile_text-center">Files</h3>
                    <div id="wptextile_tab_content_buckets_results_bucketsAuto_files">
                    </div>
                </div>
                <div class="wptextile_clearfix"></div>
                <hr>

                <h3>Get/Create a bucket:</h3>
                <label for="textile_txt_get_bucket_content_bname">Bucket Name:</label>
                <input 
                    type="text" 
                    class="regular-text" 
                    id="textile_txt_get_bucket_content_bname"
                    name="textile_txt_get_bucket_content_bname">
                <input type="button" class="button button-primary" id="textile_btn_get_bucket_content" value="GET/CREATE A BUCKET">
                <br>
                <h3>Results:</h3>
                <div id="wptextile_tab_content_buckets_results_bcont"></div>
                <hr>

                <h3>Upload a file to IPFS:</h3>
                <label for="textile_txt_upload_file_bucketname">Bucket name:</label>
                <input 
                    type="text" 
                    class="regular-text" 
                    id="textile_txt_upload_file_bucketname"
                    name="textile_txt_upload_file_bucketname">
                Upload Image: <input type="file" id="textile_image" >
                <input class="button button-primary" id="textile_btn_upload" type="button" value="Upload to IPFS">
                <h3>Results:</h3>
                <div id="wptextile_tab_content_buckets_results_fileupload"></div>

            </div>

            
        </div>
        
    </div>';
        return $content;
    }

    private function admin_page_html_image_uploader() {
        $content = '';
        $content .= 'Upload Image: <input type="file" id="textile_image" >:';
        $content .= '<input id="textile_btn_upload" type="button" value="Upload to IPFS">';
        return $content;
    }
   

    /*
    *   Actions executed when plugin is running on Admin page
    */
    public function admin_init_actions($data) {
       
        $this->admin_page_init_settings();

        // Scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    
    /*
    *   Actions executed when plugin is running on main page
    */
    public function init_actions() {
        // Scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }


    /*
    *   On plugin activation
    */
    public function activate() {
        // do not generate any output here
        
            
    }


    /*
    *    On plugin deactivation
    */
    public function deactivate() {
        // do not generate any output here
        echo 'BYE';
    }

}