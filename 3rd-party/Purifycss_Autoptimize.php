<?php


class Purifycss_Autoptimize extends Purifycss_ThirdPartyExtension {

    public function run() {
        if (!PurifycssHelper::is_enabled()) return;
        if (!$this->autoptimizePluginActive() || !$this->autoptimizedCssEnabled()) return;

        // add_filter( 'purifycss_skip_replace_link_styles', function () {return true;});
        // add_filter( 'purifycss_skip_replace_inline_styles', function () {return true;});

    }

    private function autoptimizePluginActive() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (get_plugins() as $key => $value) {
            if ($key === 'autoptimize/autoptimize.php') {
                return is_plugin_active($key);
            }
        }

        return false;
    }

    private function autoptimizedCssEnabled() {
        return get_option('autoptimize_css') === 'on';
    }

}
