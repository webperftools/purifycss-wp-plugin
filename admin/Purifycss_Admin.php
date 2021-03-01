<?php

class Purifycss_Admin {

	public function __construct() { }

	public function add_settings_page(){
		add_options_page( 'PurifyCSS', 'PurifyCSS', 'manage_options', 'purifycss-plugin', [$this,'render_plugin_settings_page'] );
	}

	public function render_plugin_settings_page(){
		require_once 'partials/purifycss-admin-display.php';
	}

	private function getPurifyData($url) {
        foreach (PurifycssHelper::get_pages_files_mapping() as $pcfile) {
            if (untrailingslashit( $pcfile->url) === $url) {
                return $pcfile;
            };
        }
        return false;
    }

	public function add_admin_bar_menu() {
        global $wp_admin_bar, $wp;

        $color = '#eee';
        if (get_option('purifycss_livemode')=='1') { $status = 'on'; $statusLabel = "PurifyCSS is live"; }
        else if (get_option('purifycss_testmode')=='1') { $status = 'test'; $statusLabel = "PurifyCSS is in test mode"; $color = 'silver'; }
        else { $status = 'off'; $statusLabel = "Purifycss is off"; $color = 'gray';}

        $menu_id = 'purifycss';
        $wp_admin_bar->add_menu(array('id' => $menu_id, 'title' => "<span style='color: $color'>PurifyCSS</span>", 'href' => admin_url( 'options-general.php?page=purifycss-plugin' )));
        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "<span style='color: $color'>$statusLabel</span>", 'id' => 'purifycss-status'));

        $url = $_SERVER['REQUEST_URI'];
        if (strpos($url, '/wp-admin/') === false) {
            if (PurifycssHelper::isExcluded()) {
                $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "This URL is excluded", 'id' => 'purifycss-excluded'));
            } else {
                $data = $this->getPurifyData($wp->request);
                if (is_object($data)) {
                    if ($data->used != "") {
                        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "PurifyCSS: ".$data->used, 'id' => 'purifycss-used'));
                    }
                    if ($data->criticalcss != "") {
                        $criticalSize = strlen($data->criticalcss);
                        $criticalSize = round($criticalSize/1024,1)."kb";
                        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "Critical CSS: ".$criticalSize, 'id' => 'purifycss-critical'));
                    }
                    $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "<span style='color: red'>Re-run for this URL</span>", 'id' => 'purifycss-rerunsingle', 'href' => '#'));
                } else {
                    $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "No data for this page", 'id' => 'purifycss-nodata'));
                    $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "<span style='color: red'>Run for this URL</span>", 'id' => 'purifycss-runsingle', 'href' => '#'));
                }
            }
        }

        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "Settings", 'id' => 'purifycss-settings', 'href' => admin_url( 'options-general.php?page=purifycss-plugin' )));

    }

	public function register_settings(){
		register_setting( 'purifycss', "purifycss_api_key", 'string' );
		register_setting( 'purifycss', "purifycss_livemode", 'string' );
	}

	public function apiRequest($method, $route, $params = []) {
        $apiUrl = $this->get_api_host().$route;
        if ($method == 'GET') {
            return wp_remote_get( $apiUrl );
        } else {
            return wp_remote_post( $apiUrl, [ 'body' => $params ] );
        }
    }

    public function actionStartJob(){
        $params = [
            'url'      => [ get_site_url() ],
            "source"   => 'wp-plugin',
            "options"  => [
                'crawl'             => true,
                'whitelistCssFiles' => explode("\n",get_option('purifycss_skip_css_files'))
            ],
            "htmlCode" => $_POST['customhtml'],
            "key"      => get_option('purifycss_api_key')
        ];

        $this->set_onoff('purifycss_livemode', '0');

        $response = $this->apiRequest('POST', '/create', $params);

        if ( is_wp_error( $response ) ) {
            $this->handleError($response);
            return;
        }

        $responseBody = json_decode($response['body'], true);
        update_option('purifycss_job_id', $responseBody['jobId']);
        wp_send_json($response);
    }

    /** @param array|WP_Error $response */
    private function handleError($response) {
        $msg = $response->get_error_message();

        $responseData = [
            'status' => 'ERR',
            'msg' => $msg == '' ? __('Error while generating CSS', 'purifycss') : $msg,
            'resmsg' => $msg,
            'apiResponse' => $response,
        ];

        wp_send_json($responseData, $response->get_error_code());
    }

    public function actionSetRunningJob(){
        $jobId = $_GET['jobId'];
        if ($jobId == "") {
            delete_option('purifycss_runningjob');
        } else {
            update_option('purifycss_runningjob', $jobId);
        }
    }

    public function actionGetRunningJob(){
        wp_send_json(get_option('purifycss_runningjob'));
    }

    public function actionJobStatus(){
        $jobId = $_GET['jobId'];
        $single = isset($_GET['single']) ? $_GET['single'] : false;
        $response = $this->apiRequest('GET', "/status/$jobId");

        if (is_wp_error( $response ) ) {
            wp_send_json([ 'status'=>'ERR', 'res' => $response ]);
            return;
        }

        $responseBody = json_decode($response['body'], true);
        $storedStatus = get_option('purifycss_job_status');
        //if ($storedStatus !== $responseBody['status']) {
            update_option('purifycss_job_status', $responseBody['status']);
            if ($responseBody['status'] == 'completed') {
                $this->retrieveFiles($responseBody);
                $this->storeData($responseBody, $single);
            }
        //}

        wp_send_json($responseBody);
    }

    private function retrieveFiles($responseBody) {
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $cacheDir = PurifycssHelper::get_cache_dir_path();
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

        $jobId = $responseBody['jobId'];
        file_put_contents("$cacheDir/$jobId.zip", file_get_contents($this->get_api_host()."/retrieve/$jobId/$jobId.zip"));

        $res = unzip_file("$cacheDir/$jobId.zip", $cacheDir);
        if (is_wp_error($res)) PurifycssDebugger::log('Purifycss failed to unzip job package');
    }

    private function storeData($responseBody, $single) {
        PurifycssDb::insert_data($responseBody, $single);
    }

	public function actionSaveCSS(){
		$key            = get_option('purifycss_api_key');
		$html           = base64_encode($_POST['customhtml']);
		$excludeUrls    = $_POST['excludeUrls'];
        $skipCssFiles    = $_POST['skipCssFiles'];
		$msg 	        = '';
		// result msg for display in div block
		$resmsg = '';
		// result of function execution
		$result   = false;

		// check license key
		if ( $key =='' ){
			$msg = __("Invalid license key. Please enter verified license",'purifycss');
			wp_send_json([ 'status'=>'ERR','msg'=>$msg,'resmsg'=>'error' ]);
		}

		// save html code
		update_option( 'purifycss_customhtml', $html );
        update_option( 'purifycss_excluded_urls', $excludeUrls );
        update_option( 'purifycss_skip_css_files', $skipCssFiles );

		// save css code

		$result   = true;

		// success result
		if ( $result ){
			wp_send_json([
				'status'=>'OK',
				'msg'=>__('Params saved successfully','purifycss'),
				'resmsg'=>$resmsg,
				]);			
		}else{
			// error
			wp_send_json([
				'status'=>'ERR',
				'msg'=>$msg==''?__('Error by save params','purifycss'):$msg,
				'resmsg'=>$resmsg,
				]);
		}
	}

    public function actionGetCssForSinglePage(){
        $params = [
            'url'      => [self::normalizeUrl($_POST['url'])], // url for current page...
            "source"   => 'wp-plugin',
            "options"  => [
                'crawlerOptions'    => ['maxdepth' => 0],
                'whitelistCssFiles' => explode("\n",get_option('purifycss_skip_css_files'))
            ],
            "htmlCode" => base64_decode(get_option('purifycss_customhtml')),
            "key"      => get_option('purifycss_api_key')
        ];

        $response = $this->apiRequest('POST', '/create', $params);

        if ( is_wp_error( $response ) ) {
            $this->handleError($response);
            return;
        }

        wp_send_json($response); // pass jobId to frontend script - to start polling status
    }

    public function actionClearForSinglePage(){
        $url    = $_POST['url']; // url for current page...
        $url    = self::normalizeUrl($url);

	    PurifycssDb::clear_url($url);

        wp_send_json(['status' => 'OK']);
    }

    public function enqueue_adminbar_scripts() {
	    $ajaxurl = json_encode(admin_url( 'admin-ajax.php' ));
	    $apiHost = json_encode($this->get_api_host());
        echo "<script>var purifyData = {ajaxurl:$ajaxurl, apiHost: $apiHost};</script><script async='async' src='".plugin_dir_url( __FILE__ ) . 'js/purifycss-adminbar.js'."'></script>";
    }

	public function get_api_host() {
	    if (isset($_COOKIE['purifycss_api_host'])) {
            return $_COOKIE['purifycss_api_host'];
        }
        return "https://api.purifycss.online";
    }

	public function actionActivate(){
        $licenseKey    = esc_attr($_POST['key']);
        update_option( 'purifycss_api_key', $licenseKey );

        $params = ['licenseKey'=>$licenseKey, 'domain'=>home_url()];
        $response = $this->apiRequest('POST', '/license/activate', $params);

        if ( is_wp_error( $response ) ) {
            $this->handleError($response);
            return;
        }

        wp_send_json($response);
    }

	public function actionLivemode(){
        $this->set_onoff("purifycss_livemode", '1');
        $this->set_onoff("purifycss_testmode", '0');
        $this->set_onoff("purifycss_offmode", '0');
        wp_send_json([ 'status'=>'OK', 'msg'=>__('Live mode enabled','purifycss') ]);
	}

	public function actionOffmode(){
        $this->set_onoff("purifycss_livemode", '0');
        $this->set_onoff("purifycss_testmode", '0');
        $this->set_onoff("purifycss_offmode", '1');
        wp_send_json([ 'status'=>'OK', 'msg'=>__('PurifyCSS disabled','purifycss') ]);
	}
	public function actionTestmode(){
        $this->set_onoff("purifycss_livemode", '0');
        $this->set_onoff("purifycss_testmode", '1');
        $this->set_onoff("purifycss_offmode", '0');
        wp_send_json([ 'status'=>'OK', 'msg'=>__('Test mode enabled','purifycss') ]);
	}

	public function enqueue_styles() {
        $wpScreen = get_current_screen();
        if ($wpScreen->id != "settings_page_purifycss-plugin") return;

		wp_enqueue_style( 'purifycss', plugin_dir_url( __FILE__ ) . 'css/purifycss-admin.css', array(), PURIFYCSS_VERSION, 'all' );
	}

	public function enqueue_scripts() {
	    $wpScreen = get_current_screen();
	    if ($wpScreen->id != "settings_page_purifycss-plugin") return;

        $settings_html = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
        $settings_css  = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

        wp_enqueue_script( 'purifycss', plugin_dir_url( __FILE__ ) . 'js/purifycss-admin.js', array( 'jquery', 'code-editor' ), PURIFYCSS_VERSION, true );

		if ( false === $settings_html ) {
			return;
		}

		wp_localize_script( 'purifycss', 'customhtml_text_param', $settings_html  );
		
	}

    private function set_onoff($option, $value) {
        $result = update_option( $option, $value );
        do_action('purifycss_after_onoff', array('option' => $option, 'value' => $value));

        return $result;
    }

    private static function normalizeUrl($url) {
	    $parts = explode("#", $url); // remove hash
        $url = $parts[0];

	    return $url;
    }
}
