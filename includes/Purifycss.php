<?php

class Purifycss {

	protected $public;

	public function __construct() {
	    error_log('Purifycss init');

        $this->load_dependencies();

		$this->public = new Purifycss_Public();

		$this->define_admin_hooks();
		$this->public->define_hooks();

	}

	private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssDb.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Purifycss_Admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Purifycss_Updater.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/Puriycss_Public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssHelper.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/PurifycssDebugger.php';

	}


	private function define_admin_hooks() {

		$plugin_admin = new Purifycss_Admin();
        $plugin_updater = new Purifycss_Updater();

		add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles') );
		add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts') );

		add_action('admin_menu',array($plugin_admin, 'add_settings_page') );
        add_action('admin_init',array($plugin_admin, 'register_settings') );

        add_action('admin_bar_menu', array($plugin_admin, 'add_admin_bar_menu') , 2000);
        add_action('wp_after_admin_bar_render',array( $plugin_admin, 'enqueue_adminbar_scripts'));

        add_action('wp_ajax_purifycss_livemode',array($plugin_admin, 'actionLivemode') );
		add_action('wp_ajax_purifycss_testmode',array($plugin_admin, 'actionTestmode') );
		add_action('wp_ajax_purifycss_activate',array($plugin_admin, 'actionActivate') );
		add_action('wp_ajax_purifycss_getcss',array($plugin_admin, 'actionGetCSS') );
        add_action('wp_ajax_purifycss_savecss',array($plugin_admin, 'actionSaveCSS') );
        add_action('wp_ajax_purifycss_startjob',array($plugin_admin, 'actionStartJob') );
        add_action('wp_ajax_purifycss_jobstatus',array($plugin_admin, 'actionJobStatus') );

        add_action('wp_ajax_purifycss_getcss_single', array($plugin_admin, 'actionGetCssForSinglePage'));
		add_action('wp_ajax_purifycss_clear_single', array($plugin_admin, 'actionClearForSinglePage'));

        add_filter('pre_set_site_transient_update_plugins', array( $plugin_updater, 'check_plugins_updates' ) );
    }

}
