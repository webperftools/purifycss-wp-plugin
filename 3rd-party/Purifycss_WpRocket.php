<?php


class Purifycss_WpRocket extends Purifycss_ThirdPartyExtension {

    public function run() {
        if ($this->wpRocketPluginActive()) {
            add_action( 'purifycss_after_onoff', array($this, 'clear_rocket_cache'));
        }
    }

    public function clear_rocket_cache() {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    private function wpRocketPluginActive() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (get_plugins() as $key => $value) {
            if ($key === 'wprocket/wprocket.php') {
                return is_plugin_active($key);
            }
        }

        return false;
    }

}
