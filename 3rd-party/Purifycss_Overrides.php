<?php

/* this is for testing with different options.
TODO: move these settings into core and make them toggleable through admin options
 */

class Purifycss_Overrides extends Purifycss_ThirdPartyExtension {

    public function run() {
        add_filter( 'purifycss_skip_enqueue_link_styles', function () {return true;});
        add_filter( 'purifycss_before_final_print', array($this, 'modify_final_print'));
        add_action( 'purifycss_after_replace_all_styles', function() {
            // wp_enqueue_style('full-purify', $matching_css, array(), false, 'all' );
        });
        add_action('wp_footer', function() {
            $matching_css = $this->get_matching_css_for_current_url();
            echo <<<HTML
<script>
var cb=function(){var l=document.createElement('link');l.rel='stylesheet';l.href='$matching_css';var h=document.getElementsByTagName('head')[0];h.parentNode.insertBefore(l,h);}; 
var raf=requestAnimationFrame||mozRequestAnimationFrame||webkitRequestAnimationFrame||msRequestAnimationFrame;
if(raf){raf(cb)}else{window.addEventListener('load',cb)}
</script>
HTML;
        });
    }

    private function get_matching_css_for_current_url() {
        global $wp;
        $url = untrailingslashit(home_url( $wp->request ));

        if (PurifycssHelper::isExcluded()) return false;

        foreach ($this->public->files_perpage as $pcfile) {
            if (untrailingslashit( $pcfile->url) === $url) return $pcfile->css;
        }
        return false;
    }

    private function get_critical_css_for_current_url() {
        global $wp;
        $url = untrailingslashit(home_url( $wp->request ));
        foreach ($this->public->files_perpage as $pcfile) {
            if (untrailingslashit( $pcfile->url) === $url) return $pcfile->criticalcss;
        }
        return false;
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

    public function modify_final_print($wpHTML) {
        if (PurifycssHelper::isExcluded()) return $wpHTML;

        $criticalCss = $this->get_critical_css_for_current_url();
        if (!$criticalCss) return $wpHTML;
        $criticalCss = "\n<!--critical css--><style>".$criticalCss."</style>";
        $wpHTML = str_replace('</title>', '</title>'.$criticalCss, $wpHTML);
        $wpHTML = $this->async_css( $wpHTML );
        return $wpHTML;
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


    /*
     * TODO: fix preload when media is not "all"
     * <link rel='preload'   href='https://dev.amplus.ch/wp-content/plugins/woocommerce/assets/css/woocommerce-smallscreen.css?ver=4.8.0' as="style" onload="this.onload=null;this.rel='stylesheet'" media='only screen and (max-width: 768px)' />
     * */
    public function async_css( $buffer ) {
        $css_pattern = '/(?=<link[^>]*\s(rel\s*=\s*[\'"]stylesheet["\']))<link[^>]*\shref\s*=\s*[\'"]([^\'"]+)[\'"](.*)>/iU';

        preg_match_all( $css_pattern, $buffer, $tags_match );
        if ( ! isset( $tags_match[0] ) ) {
            return $buffer;
        }

        $noscripts = '<noscript>';

        foreach ( $tags_match[0] as $i => $tag ) {
            $path = wp_parse_url( $tags_match[2][ $i ], PHP_URL_PATH );

            $preload = str_replace( 'stylesheet', 'preload', $tags_match[1][ $i ] );
            $onload  = preg_replace( '~' . preg_quote( $tags_match[3][ $i ], '~' ) . '~iU', ' as="style" onload=""' . $tags_match[3][ $i ] . '>', $tags_match[3][ $i ] );
            $tag     = str_replace( $tags_match[3][ $i ] . '>', $onload, $tag );
            $tag     = str_replace( $tags_match[1][ $i ], $preload, $tag );
            $tag     = str_replace( 'onload=""', 'onload="this.onload=null;this.rel=\'stylesheet\'"', $tag );
            $tag     = preg_replace( '/(id\s*=\s*[\"\'](?:[^\"\']*)*[\"\'])/i', '', $tag );
            $buffer  = str_replace( $tags_match[0][ $i ], $tag, $buffer );

            $noscripts .= $tags_match[0][ $i ];
        }

        $noscripts .= '</noscript>';

        // $loadScript = "<script>document.querySelectorAll('link[rel=preload][as=style]').forEach(function(link){link.attributes.rel='stylesheet';})</script>";
        return str_replace( '</body>', $noscripts . '</body>', $buffer );
    }

}
