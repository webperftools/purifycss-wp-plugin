<?php


class Purifycss_Autoptimize extends Purifycss_ThirdPartyExtension {

    public function run() {
        if (!PurifycssHelper::is_enabled()) return;
        if (!$this->autoptimizePluginActive() | !$this->autoptimizedCssEnabled()) return;

        add_filter( 'purifycss_skip_replace_link_styles', function () {return true;});
        add_filter( 'purifycss_skip_replace_inline_styles', function () {return true;});

        add_filter( 'autoptimize_html_after_minify', array($this, 'replace_styles'), 20);
    }

    public function replace_styles($html) {
        $str = "<!--\n";

        foreach ($this->public->files as $file) {
            $str.= $file->orig_css."\n".$file->css;

            if ($html.strpos($file->orig_css, $html) !== -1) { $str .= ' CHECK!'; }
            $str.="\n\n";
            $html = str_replace($file->orig_css, $file->css, $html);
        }
        $str .= "-->";

        if (PurifycssHelper::is_debug()) {
            $html = '<!-- purifycss -->'.$str.$html;
        }

        return $html;
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
