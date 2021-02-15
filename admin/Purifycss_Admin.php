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
        if (get_option('purifycss_livemode')=='1') { $status = 'on'; $statusLabel = "Live mode on"; }
        else if (get_option('purifycss_testmode')=='1') { $status = 'test'; $statusLabel = "Test mode on"; $color = 'silver'; }
        else { $status = 'off'; $statusLabel = "Purifycss is not active"; $color = 'gray';}

        $menu_id = 'purifycss';
        $wp_admin_bar->add_menu(array('id' => $menu_id, 'title' => "<span style='color: $color'>PurifyCSS</span>", 'href' => admin_url( 'options-general.php?page=purifycss-plugin' )));
        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "<span style='color: $color'>$statusLabel</span>", 'id' => 'purifycss-status'));

        $url = untrailingslashit(home_url( $wp->request ));
        if (strpos($url, '/wp-admin/') === false) {
            if (PurifycssHelper::isExcluded()) {
                $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => "This URL is excluded", 'id' => 'purifycss-excluded'));
            } else {
                $data = $this->getPurifyData($url);
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
            return wp_remote_post( $apiUrl, $params );
        }
    }

    public function actionStartJob(){
        $params = [
            'timeout' =>300,
            'body'=>[
                'url'      => [get_site_url()],
                "source"   => 'wp-plugin',
                "options"  => [
                    'crawl'             => true,
                    'whitelistCssFiles' => explode("\n",get_option('purifycss_skip_css_files'))
                ],
                "htmlCode" => $_POST['customhtml'],
                "key"      => get_option('purifycss_api_key')
            ]
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
        if (is_wp_error($res)) error_log('Purifycss failed to unzip job package');
    }

    private function storeData($responseBody, $single) {
        PurifycssDb::insert_data($responseBody, $single);
    }

	public function actionSaveCSS(){
		$key            = get_option('purifycss_api_key');
		$html           = base64_encode($_POST['customhtml']);
		$excludeUrls    = $_POST['excludeUrls'];
        $skipCssFiles    = $_POST['skipCssFiles'];
		$css            = $_POST['editedcss'];
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

	public function actionGetCSS(){
		$option         = "purifycss_css";
		$url            = $this->get_api_host().'/api/purify';
		$key            = get_option('purifycss_api_key');
		$html           = base64_encode($_POST['customhtml']);
		$msg 	        = '';
		$css            = '';
		$resmsg         = '';
		$result         = false;
        $skipCssFiles   = get_option('purifycss_skip_css_files');

		if ( $key =='' ){
			$msg = __("Invalid license key",'purifycss');
			wp_send_json([ 'status'=>'ERR','msg'=>$msg,'resmsg'=>'error' ]);
			return;
		}

        update_option( 'purifycss_customhtml', $html );

        $this->set_onoff("purifycss_livemode", "0");

		do_action('purifycss_before_api_request');

        $params = [
            'timeout' =>300,
            'body'=>[
                'url'      => [get_site_url()],
                "source"   => 'wp-plugin',
                "options"  => [
                    'crawl'             => true,
                    'whitelistCssFiles' => explode("\n",$skipCssFiles)
                ],
                "htmlCode" => base64_decode($html),
                "key"      => $key
            ]

        ];

		$response = wp_remote_post( $url, $params );

		// check error
		if ( is_wp_error( $response ) ) {
			$msg    = $response->get_error_message();
			$result = false;
		}else{
			// get body request
			$_rsp = json_decode($response['body'], true);
			$rs = $_rsp;
			if ( !$_rsp || isset($_rsp['error']) ){
				$result = false;
				if ( isset($_rsp['response']['message']) ){
					$msg    = $_rsp['response']['message'];
					$resmsg = $_rsp['response']['message'];
				} else{
					$msg = $_rsp['error'];
					if (isset($_rsp['while'])) {
					    $msg .= '<br>while '.$_rsp['while'];
                    }
					$resmsg = $msg;
				}

				wp_send_json([
                    'status'=>'ERR',
                    'msg'=> $msg=='' ? __('Error while generating CSS','purifycss') : $msg,
                    'resmsg'=>$resmsg,
                    'resp'=>$response,
                    'livemode' => get_option('purifycss_livemode'),
                ]);
				return;

			}


            $result = true;
            update_option( $option, $_rsp['results']['purified']['content'] );
            $css = $_rsp['results']['purified']['content'];
            // save css to db
            PurifycssHelper::save_css_to_db( $_rsp['css'] );
            PurifycssHelper::save_pages_to_db( $_rsp['html'] );

            $percentage = round((($_rsp['results']['stats']['beforeBytes']-$_rsp['results']['stats']['afterBytes'])/$_rsp['results']['stats']['beforeBytes'])*100);
            // calc percentage
            $resmsg = '<b>'.$_rsp['results']['stats']['removed']
                           .' ('.$percentage.'%)</b> '
                           .__('of your CSS has been cleaned up','purifycss');

            // save result text to db
            update_option( 'purifycss_resultdata', $resmsg );

        }
		// remove this
		// $resmsg=$response;

        $files = Purifycsshelper::get_css_files_mapping();

		// success result
		if ( $result ){
			wp_send_json([
				'status'=>'OK',
				'msg'=>__('CSS generated successfully','purifycss'),
				'resmsg'=>$resmsg,
				'styles'=>$css,
				'resp'=>$rs,
                'files' => $files,
				'livemode' => get_option('purifycss_livemode'),
				]);			
		}else{
			// error

		}
	}

    public function actionGetCssForSinglePage(){
        $params = [
            'timeout' =>300,
            'body'=>[
                'url'      => [self::normalizeUrl($_POST['url'])], // url for current page...
                "source"   => 'wp-plugin',
                "options"  => [
                    'crawlerOptions'    => ['maxdepth' => 0],
                    'whitelistCssFiles' => explode("\n",get_option('purifycss_skip_css_files'))
                ],
                "htmlCode" => base64_decode(get_option('purifycss_customhtml')),
                "key"      => get_option('purifycss_api_key')
            ]
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
        echo "<script>var purifyData = {ajaxurl:$ajaxurl, apiHost: $apiHost};</script><script src='".plugin_dir_url( __FILE__ ) . 'js/purifycss-adminbar.js'."'></script>";
    }

	public function get_api_host() {
	    if (isset($_COOKIE['purifycss_api_host'])) {
            return $_COOKIE['purifycss_api_host'];
        }
        return "https://api.purifycss.online";
    }

	public function actionActivate(){
		$option = "purifycss_api_key";
		$url    = $this->get_api_host().'/api/validate';
		// $url    = 'https://purifycss.online/api/validate';
		$key    = esc_attr($_POST['key']);

		$msg 	= '';
		// result of function execution
		$result   = false;

		// send request
		$response = wp_remote_post( $url, [ 'body'=>['key'=>$key, 'domain'=>home_url()] ] );

		if ( is_wp_error( $response ) ) {
			$msg    = $response->get_error_message();
			$result = false;
		}else{
			$_rsp = json_decode($response['body'], true);
			if ( $_rsp['valid']==true ){
				update_option( $option, $key );
				$result = true;
				update_option( 'purifycss_api_key_activated', true);
			}else{
				$result = false;
				$msg    = $_rsp['error'];
				update_option( 'purifycss_api_key_activated', false);
			}
		}

		// success result
		if ( $result ){
			wp_send_json([
				'status'=>'OK',
				'msg'=>__('License key acceped','purifycss').' '.$key,
				// 'resp'=>$response
				]);			
		}else{
			// error
			wp_send_json([
				'status'=>'ERR',
				'msg'=>$msg==''?__('License key not acceped, site error','purifycss'):$msg,
				// 'resp'=>$response
				]);
		}
	}

	public function actionLivemode(){
		$option = "purifycss_livemode";
		$livemode = get_option($option);
        $result = $this->set_onoff($option, $livemode=="" || $livemode=="0" ? "1" : "0");

		if ( $result ){
			wp_send_json([
				'status'=>'OK',
				'msg'=>__('Live mode '.($livemode=='1'?'enabled':'disabled'),'purifycss'),
				'livemode'=>$livemode,
				]);			
		}else{
			wp_send_json([
				'status'=>'ERR',
				'msg'=>__('Live mode don\'t enabled, site error','purifycss'),
				]);
		}
	}

	public function actionTestmode(){
		$option = "purifycss_testmode";
		$testmode = get_option($option);

        $result = $this->set_onoff($option, $testmode=="" || $testmode=="0" ? "1" : "0");

        if ( $result ){
			wp_send_json([
				'status'=>'OK',
				'msg'=>__('Test mode '.($testmode=='1'?'enabled':'disabled'),'purifycss'),
				'testmode'=>$testmode,
				]);			
		}else{
			// error
			wp_send_json([
				'status'=>'ERR',
				'msg'=>__('Test mode don\'t enabled, site error','purifycss'),
				]);
		}
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
