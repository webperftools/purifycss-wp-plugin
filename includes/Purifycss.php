<?php

class Purifycss {

	protected $loader;
	protected $plugin_name;
	protected $public;
	protected $version;
	protected $latest_version;

	public function __construct() {
		$this->version = defined('PURIFYCSS_VERSION') ? PURIFYCSS_VERSION : '1.0.0';
		$this->plugin_name = 'purifycss';

        $this->load_dependencies();

		$this->public = new Purifycss_Public( $this->get_plugin_name(), $this->get_version() );

		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssLoader.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssDb.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Purifycss_Admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Purifycss_Updater.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/Puriycss_Public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssHelper.php';

		$this->loader = new Purifycss_Loader();
	}


	private function define_admin_hooks() {

		$plugin_admin = new Purifycss_Admin( $this->get_plugin_name(), $this->get_version() );
        $plugin_updater = new Purifycss_Updater( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'admin_menu',$plugin_admin, 'add_settings_page' );
        $this->loader->add_action( 'admin_init',$plugin_admin, 'register_settings' );

        $this->loader->add_action( 'admin_bar_menu', $plugin_admin, 'add_admin_bar_menu' , 2000);

        $this->loader->add_action( 'wp_ajax_purifycss_livemode',$plugin_admin, 'actionLivemode' );
		$this->loader->add_action( 'wp_ajax_purifycss_testmode',$plugin_admin, 'actionTestmode' );
		$this->loader->add_action( 'wp_ajax_purifycss_activate',$plugin_admin, 'actionActivate' );
		$this->loader->add_action( 'wp_ajax_purifycss_getcss',$plugin_admin, 'actionGetCSS' );
		$this->loader->add_action( 'wp_ajax_purifycss_savecss',$plugin_admin, 'actionSaveCSS' );



        add_filter( 'pre_set_site_transient_update_plugins', array( $plugin_updater, 'check_plugins_updates' ) );

    }

	private function define_public_hooks() {
        global $wp;
        $current_url = is_object($wp) ? untrailingslashit(home_url( $wp->request )) : null;

		if ( PurifycssHelper::is_enabled($current_url) ){

            $this->loader->add_action( 'wp_print_styles', $this->public, 'before_wp_print_styles', 0);
            $this->loader->add_action( 'wp_print_styles', $this->public, 'after_wp_print_styles', PHP_INT_MAX);


            $usingAPI = true; // TODO enable using the plugin without API license
            if ($usingAPI) {
                $this->loader->add_action( 'wp_print_styles', $this->public, 'replace_all_styles', PHP_INT_MAX - 1 );
            } else {
                $this->loader->add_action( 'wp_print_styles', $this->public, 'replace_all_styles', PHP_INT_MAX - 1 );
                $this->loader->add_action( 'wp_print_styles', $this->public, 'enqueue_purified_css_file', PHP_INT_MAX - 1 );
            }

            // $this->loader->add_action( 'style_loader_src', $plugin_public, 'replace_styles', PHP_INT_MAX );

            $this->loader->add_filter( 'template_redirect', $this->public, 'start_html_buffer', PHP_INT_MAX );
			$this->loader->add_filter( 'wp_footer', $this->public, 'end_html_buffer', PHP_INT_MAX );
            $this->loader->add_filter( 'wp_footer', $this->public, 'debug_enqueued_styles', PHP_INT_MAX);
            $this->thirdparty_hooks();
		}
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}


	public function get_version() {
		return $this->version;
	}

    private function thirdparty_hooks() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_ThirdPartyExtension.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Autoptimize.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Elementor.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_W3TotalCache.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_WpRocket.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Overrides.php';

        $third_parties = array(
            new Purifycss_Elementor($this->loader, $this->public),
            new Purifycss_Autoptimize($this->loader, $this->public),
            new Purifycss_W3TotalCache($this->loader, $this->public),
            new Purifycss_WpRocket($this->loader, $this->public),
            new Purifycss_Overrides($this->loader, $this->public)
        );

        foreach ($third_parties as $plugin) {
            $plugin->run();
        }
    }
}
