<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/f2re
 * @since      1.0.0
 *
 * @package    Purifycss
 * @subpackage Purifycss/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Purifycss
 * @subpackage Purifycss/includes
 * @author     F2re <lendingad@gmail.com>
 */
class Purifycss {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Purifycss_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Purifycss_Public    $public    The string used to uniquely identify this plugin.
	 */
	protected $public;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;
	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $latest_version    The latest version of the plugin.
	 */
	protected $latest_version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PURIFYCSS_VERSION' ) ) {
			$this->version = PURIFYCSS_VERSION;
		} else {
			$this->version = '1.0.0';
		}

	/*	$cache_folder_name = 'generatedcss';
		if (!defined('PURIFYCSS_CACHEDIR_URL')) {define('PURIFYCSS_CACHEDIR_URL', );}
		if (!defined('PURIFYCSS_CACHEDIR_PATH')) {define('PURIFYCSS_CACHEDIR_PATH', );}
	*/


		$this->plugin_name = 'purifycss';

        $this->load_dependencies();

		$this->public = new Purifycss_Public( $this->get_plugin_name(), $this->get_version() );

		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Purifycss_Loader. Orchestrates the hooks of the plugin.
	 * - Purifycss_Admin. Defines all hooks for the admin area.
	 * - Purifycss_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssLoader.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssDb.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Purifycss_Admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Purifycss_Updater.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/Puriycss_Public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssHelper.php';

		$this->loader = new Purifycss_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Purifycss_Admin( $this->get_plugin_name(), $this->get_version() );
        $plugin_updater = new Purifycss_Updater( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// register setting page
		$this->loader->add_action( 'admin_menu',$plugin_admin, 'add_settings_page' );

		// register settings for plugin
		$this->loader->add_action( 'admin_init',$plugin_admin, 'register_settings' );

		/**
		 * add ajax action from admin
		 */
		$this->loader->add_action( 'wp_ajax_purifycss_livemode',$plugin_admin, 'actionLivemode' );
		$this->loader->add_action( 'wp_ajax_purifycss_testmode',$plugin_admin, 'actionTestmode' );
		$this->loader->add_action( 'wp_ajax_purifycss_activate',$plugin_admin, 'actionActivate' );
		$this->loader->add_action( 'wp_ajax_purifycss_getcss',$plugin_admin, 'actionGetCSS' );
		$this->loader->add_action( 'wp_ajax_purifycss_savecss',$plugin_admin, 'actionSaveCSS' );


        add_filter( 'pre_set_site_transient_update_plugins', array( $plugin_updater, 'check_plugins_updates' ) );

    }

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		if ( PurifycssHelper::is_enabled() ){

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
        }
        $this->loader->add_filter( 'wp_footer', $this->public, 'debug_enqueued_styles', PHP_INT_MAX);


        $this->thirdparty_hooks();
	}

    /**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Purifycss_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

    /**
     * Custom fixes for 3rd party plugins and themes
     *
     * @since     1.0.0
     */
    private function thirdparty_hooks() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_ThirdPartyExtension.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Autoptimize.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Elementor.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_W3TotalCache.php';

        $third_parties = array(
            new Purifycss_Elementor($this->loader, $this->public),
            new Purifycss_Autoptimize($this->loader, $this->public),
            new Purifycss_W3TotalCache($this->loader, $this->public)
        );

        foreach ($third_parties as $plugin) {
            $plugin->run();
        }
    }
}
