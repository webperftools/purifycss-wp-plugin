<?php

class Purifycss_Public {
    public $files;
    public $files_perpage;

    public $purifycss_url = null;
    public $criticalcss = null;

    public function __construct( ) {
        $this->files_perpage = PurifycssHelper::get_pages_files_mapping();
    }

    public function define_hooks() {
        if ( PurifycssHelper::is_enabled() ){


            // place internal hooks
            add_filter('template_redirect', array($this, 'start_html_buffer'), PHP_INT_MAX );
            add_filter('wp_footer', array($this, 'end_html_buffer'), PHP_INT_MAX );

            // use hooks to perform purify actions
            add_action('wp_print_styles', function () {
                if ($this->should_run()) {
                    $this->remove_enqueued_styles();
                }
            }, PHP_INT_MAX - 1);

            add_filter('purifycss_before_final_print', function($wpHTML) {
                if ($this->should_run()) {
                    $wpHTML = $this->remove_inline_styles($wpHTML);
                    $wpHTML = $this->remove_stylesheet_tags($wpHTML);
                    $wpHTML = $this->add_critical_css($wpHTML);
                    $wpHTML = $this->add_purifycss_preload($wpHTML);
                }
                return $wpHTML;
            }, PHP_INT_MAX );
            /*
            add_filter('purifycss_before_final_print', array($this, 'remove_inline_styles'), PHP_INT_MAX );
            add_filter('purifycss_before_final_print', array($this, 'remove_stylesheet_tags'), PHP_INT_MAX );
            add_filter('purifycss_before_final_print', array($this, 'add_critical_css'), PHP_INT_MAX );
            add_filter('purifycss_before_final_print', array($this, 'add_purifycss_preload'), PHP_INT_MAX );
            */

            add_action('purifycss_after_final_print', array($this, 'print_debug'), PHP_INT_MAX );



            $this->thirdparty_hooks();
        }
    }

    public function start_html_buffer(){
        ob_start();
    }

    public function end_html_buffer(){
        global $wp_styles;
        $wpHTML = ob_get_clean();

        $wpHTML = apply_filters('purifycss_before_final_print', $wpHTML);
        echo $wpHTML;
        do_action('purifycss_after_final_print');

    }

    public function should_run() {
        if (PurifycssHelper::isExcluded()) return false;
        if (!apply_filters('purifycss_should_run', true)) return false;

        global $wp;
        $url = untrailingslashit(home_url( $wp->request ));

        foreach ($this->files_perpage as $pcfile) {
            if (untrailingslashit( $pcfile->url) === $url) {
                $this->purifycss_url = $pcfile->css;
                $this->criticalcss = $pcfile->criticalcss;
                PurifycssDebugger::log("  purifycss_should_run returns true");
                return true;
            }
        }
        PurifycssDebugger::log("  purifycss_should_run returns false - nothing found for current url: ".$url);
        PurifycssDebugger::log("  We have only data for the urls: ");
        foreach($this->files_perpage as $item) {
            PurifycssDebugger::log("    ".$item->url);
        }
        return false;
    }

    function remove_enqueued_styles() {
        PurifycssDebugger::log("remove_enqueued_styles");
        if (PurifycssHelper::isExcluded()) return;

        $skip = apply_filters('purifycss_skip_remove_enqueued_styles', false);
        if ($skip) return;

        global $wp_styles;
        foreach ($wp_styles->queue as $style) {

            if ( $style=='admin-bar' ) continue;
            if (strpos($style, 'purified') !== false) continue;
            if (!array_key_exists($style, $wp_styles->registered)) {
                PurifycssDebugger::log("  failed to dequeue unregistered style: ".$style);
            }

            if ($this->isWhitelistedStyle($wp_styles->registered[$style]->src)) {
                PurifycssDebugger::log("  skip (whitelist): ".$wp_styles->registered[$style]->src);
                continue;
            }

            PurifycssDebugger::log("  dequeue style: ".$wp_styles->registered[$style]->src);
            wp_dequeue_style($wp_styles->registered[$style]->handle);
        }
        do_action('purifycss_after_remove_enqueued_styles');
    }


    public function remove_stylesheet_tags($wpHTML) {
        PurifycssDebugger::log("remove_stylesheet_tags");

        $matches = '';
        preg_match_all('/<link[^>]*>/im', $wpHTML, $matches);
        foreach ($matches[0] as $key => $tag) {
            if ( $this->isRelStylesheet($tag) || $this->isRelPreloadAsStyle($tag)) {
                $hrefs = '';
                preg_match('/ href=[\'"]([^\'"]+)[\'"]/im', $tag, $hrefs);

                if ( count($hrefs) <= 1 ) { PurifycssDebugger::log("found link tag without href? ".$tag); continue; }
                $src = $hrefs[1];

                if (!$this->isWhitelistedStyle($src)) {
                    PurifycssDebugger::log("  removing: $src");
                    $wpHTML = str_replace($tag, "", $wpHTML);
                } else {
                    PurifycssDebugger::log("  skipping (whitelisted): $src");
                }

            }
        }
        return $wpHTML;
    }

    private function isRelStylesheet($tag) {
        return preg_match('/rel=[\'"]stylesheet[\'"]/im', $tag);
    }
    private function isRelPreloadAsStyle($tag) {
        return preg_match('/rel=[\'"]preload[\'"]/im', $tag) && preg_match('/as=[\'"]style[\'"]/im', $tag);
    }

    private function isWhitelistedStyle($src) {
        $default_whitelist = ["admin-bar", "purified", "purifycss"];
        $skipCssFiles = get_option('purifycss_skip_css_files', "");
        $whitelist = array_merge($default_whitelist, explode("\n",$skipCssFiles) );

        foreach ($whitelist as $regex) {
            if ($regex === "") continue;
            if (is_numeric(strpos($src, $regex))) { return true; } // exact match
            if (preg_match("/".$regex."/im", $src)) { return true; } // regex match
        }
        return false;
    }

    public function remove_inline_styles($wpHTML) {
        PurifycssDebugger::log("remove_inline_styles");

        $skip = apply_filters('purifycss_skip_replace_inline_styles', false);
        if (!$skip) {
            $matches = '';
            preg_match_all('/<style[^>]*>([^<]*)<\/style>/im', $wpHTML, $matches);

            foreach ($matches[0] as $tag) {
                $wpHTML = str_replace($tag, "", $wpHTML);
            }
        }
        return $wpHTML;
    }

    public function add_critical_css($wpHTML) {
        PurifycssDebugger::log("add_critical_css");

        $criticalCss = $this->criticalcss;
        if (!$criticalCss) return $wpHTML;

        $criticalCss = file_get_contents(PurifycssHelper::get_cache_dir_path() . $criticalCss);
        $criticalCss = "\n\t<style id=\"critical-css\" type=\"text/css\">".$criticalCss."</style><!-- end critical css -->";
        $wpHTML = str_replace('</title>', '</title>'.$criticalCss, $wpHTML);
        return $wpHTML;
    }

    public function add_purifycss_async($wpHTML) {
        PurifycssDebugger::log("add_purifycss_async");
        $appendCode = "<script>var cb=function(){var l=document.createElement('link');l.rel='stylesheet';l.href='$this->purifycss_url';var h=document.getElementsByTagName('head')[0];h.parentNode.insertBefore(l,h);};var raf=requestAnimationFrame||mozRequestAnimationFrame||webkitRequestAnimationFrame||msRequestAnimationFrame;if(raf){raf(cb)}else{window.addEventListener('load',cb)}</script><noscript><link rel='stylesheet' href='$this->purifycss_url'/></noscript>";
        return $wpHTML . $appendCode;
    }

    public function add_purifycss($wpHTML) {
        PurifycssDebugger::log("add_purifycss");
        PurifycssDebugger::log("  purifycss url: ".$this->purifycss_url);
        $appendCode = "<link id=\"purifycss\" rel=\"stylesheet\" href=\"$this->purifycss_url\" media=\"all\" />";
        $wpHTML = str_replace('<!-- end critical css -->',"\n$appendCode",$wpHTML);
        return $wpHTML;
    }

    public function add_purifycss_preload($wpHTML) {
        PurifycssDebugger::log("add_purifycss_preload");
        PurifycssDebugger::log("  purifycss url: ".$this->purifycss_url);

        $appendCode = "<link id=\"purifycss\" rel=\"preload\" as=\"style\" href=\"$this->purifycss_url\" media=\"all\" onload=\"this.rel='stylesheet'\" />";
        $wpHTML = str_replace('<!-- end critical css -->',"\n\t$appendCode",$wpHTML);
        return $wpHTML;
    }
    public function print_debug() {
        PurifycssDebugger::print_debug_logs();
    }

    private function thirdparty_hooks() { // TODO simplify this by "autoloading" each file in 3rd-party dir
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_ThirdPartyExtension.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Autoptimize.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Elementor.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_W3TotalCache.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_WpRocket.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '3rd-party/Purifycss_Divi.php';

        $third_parties = array(
            new Purifycss_Elementor($this),
            new Purifycss_Autoptimize($this),
            new Purifycss_W3TotalCache($this),
            new Purifycss_WpRocket($this),
            new Purifycss_Divi($this),
        );

        foreach ($third_parties as $plugin) {
            $plugin->run();
        }
    }
}
