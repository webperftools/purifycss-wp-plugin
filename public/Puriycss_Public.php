<?php

class Purifycss_Public {
    private $plugin_name;
	private $version;
    public $files;
    public $files_perpage;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
        $this->files = PurifycssHelper::get_css_files_mapping();
        $this->files_perpage = PurifycssHelper::get_pages_files_mapping();
	}

    function remove_all_styles(){
        $this->replace_all_styles();
    }

    function enqueue_purified_css_file() {
        wp_enqueue_style('styles_purified', PurifycssHelper::get_css_file(), false, false, 'all' );
    }

	function replace_all_styles() {
	    if (PurifycssHelper::isExcluded()) return;

        $skip = apply_filters('purifycss_skip_replace_link_styles', false);
        if ($skip) return;

		global $wp_styles;
		$need_to_enc = [];

        foreach( $wp_styles->queue as $style ) {

            if ( $style=='admin-bar' ){
                continue;
            }

            if (strpos($style, 'purified') !== false) {
                continue;
            }

            if (isset($_GET['keep']) && in_array($wp_styles->registered[$style]->handle,explode(",",$_GET['keep']))) {
                continue;
            }

            $files = PurifycssDb::get_by_src($wp_styles->registered[$style]->src);
            foreach ($files as $file){
                // there is a purified version, so remove original inline styles and enqueue corresponding file

                // check for inline extra
                $inline_style = $wp_styles->print_inline_style($wp_styles->registered[$style]->handle, false);
                $inline_style_purified = false;
                if ($inline_style) {
                    $inline_style_purified = $this->get_corresponding_css($inline_style);
                }

                // check for deps
                $deps = [];
                foreach ($wp_styles->registered[$style]->deps as $dep) {
                    $newdep = $this->get_style_dependents($wp_styles->registered[$dep]->src);

                    wp_register_style($dep.'_purified', $newdep);

                    if ($newdep) {
                        $deps[] = $dep.'_purified';
                    } else {
                        $deps[] = $dep;
                    }
                }



                wp_dequeue_style($wp_styles->registered[$style]->handle);

                $skipEnqueue = apply_filters('purifycss_skip_enqueue_link_styles', false);
                if (!$skipEnqueue) {
                    wp_enqueue_style($wp_styles->registered[$style]->handle . '_purified', PurifycssHelper::get_cache_dir_url() . $file->css, $deps, false, 'all' );

                    if ($inline_style_purified) {
                        wp_add_inline_style($wp_styles->registered[$style]->handle . '_purified', $inline_style_purified);
                    }
                }

                do_action('purifycss_after_replace_all_styles');
            }
        }

	}

	public function replace_styles($url) {
	    if (isset($_GET['purifydebug'])) {
	        echo "<pre>";
            print_r($url);echo "\n";
            print_r($this->get_matching_file($url));
            echo "</pre>";
        }
	    return $url;
    }

	public function get_matching_file($identifier) {
        if (!$this->files) {
            $this->files = PurifycssDb::get_all();
        }
        foreach ($this->files as $file) {
            if ( strpos($file->orig_css, $identifier) !== false ) return $file->css;
        }
        return false;
    }

    public function return_empty($arg) {
	    return "";
    }

    public function before_wp_print_styles() {
        ob_start();
    }

    public function after_wp_print_styles() {
        $wpHTML = ob_get_clean();
    }

    public function debug_hooks() {
	    $this->print_filters_for('wp_print_styles');
    }

    public function print_filters_for( $hook = '' ) {
        global $wp_filter;
        if( empty( $hook ) || !isset( $wp_filter[$hook] ) )
            return;

        print '<pre style="font-size:11px; color:black; line-height:1; font-weight:600">';
        print_r( $wp_filter[$hook] );
        print '</pre>';
    }

    public function debug_enqueued_styles() {
        global $wp_styles;

        if (PurifycssHelper::is_debug()) {
            echo "<table style='font-size:11px;line-height:1;background:white;color:black;border: 1px solid black;margin: 10px;'>";
            foreach( $wp_styles->queue as $style ) {
                if (strpos($style, 'purified') != false) continue;

                echo "<tr>";
                echo "<td>$style</td>";
                echo "<td>".$wp_styles->registered[$style]->src."</td>";
                echo "<td>".$this->get_matching_file($wp_styles->registered[$style]->src)."</td>";
                echo "</tr>";

                foreach ($wp_styles->registered[$style]->deps as $dep) {
                    echo "<tr>";
                    echo "<td> └─ dep: $dep</td>";
                    echo "<td>".$wp_styles->registered[$dep]->src."</td>";
                    echo "<td>".$this->get_matching_file($wp_styles->registered[$dep]->src)."</td>";
                    echo "</tr>";
                }

                $inline_style = $wp_styles->print_inline_style($wp_styles->registered[$style]->handle, false);
                if ($inline_style) {
                    $identifier = PurifycssHelper::get_css_id_by_content($inline_style);
                    echo "<tr>";
                    echo "<td> └─ inline</td>";
                    echo "<td>".$identifier."... </td>";
                    echo "<td>".$this->get_matching_file($identifier)."</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        }
    }

    public function start_html_buffer(){
        ob_start();
    }

    public function end_html_buffer(){
        $skip = apply_filters('purifycss_skip_replace_inline_styles', false);

        global $wp_styles;
        $wpHTML = ob_get_clean();

        if (!$skip) {
            $matches = '';
            preg_match_all('/<style[^>]*>([^<]*)<\/style>/im', $wpHTML, $matches);

            foreach ($matches[1] as $key => $match) {
                $css_identifier = PurifycssHelper::get_css_id_by_content($match);

                $files = PurifycssDb::get_by_src($css_identifier);

                foreach($files as $file) {
                    // there is a purified version, so remove original inline styles and enqueue corresponding file
                    // wp_enqueue_style('inline_style_'.$key.'_purified', plugin_dir_url( ( __FILE__ ) ).$file->css, array(), false, 'all' );

                    $purifiedcss_inline = file_get_contents( PurifycssHelper::get_cache_dir_path() . $file->css );
                    $wpHTML = str_replace($match,$purifiedcss_inline, $wpHTML);
                }
            }
        }

        //preg_replace('/<style[^>]*><\/style>/is','',$wpHTML);
        $wpHTML = apply_filters('purifycss_before_final_print', $wpHTML);
        echo $wpHTML;
    }

	public function enqueue_styles() {
		// echo PurifycssHelper::get_css_file();
		// get_option('purifycss_manual_css')==false
		if ( true ){
			$needed_styled = unserialize(get_option( "purifycss_neededstyles" ));
			// print_r($needed_styled);
			if ( is_array($needed_styled) && count($needed_styled)>0 ){
				$i=0;
				foreach ( $needed_styled as $style ){
					wp_enqueue_style( $this->plugin_name.'_'.$i, $style, array(), $this->version, 'all' );
					$i++;
				}
			}

			return;

			global $wp;
			// $url = home_url(add_query_arg(array(), $wp->request));
			// echo $url;
			$files = PurifycssDb::get_by_src($url);
			$i=0;
			foreach ($files as $file){
				// echo ($file->css);
				wp_enqueue_style( $this->plugin_name.'_'.$i, PurifycssHelper::get_cache_dir_url() . $file->css, array(), $this->version, 'all' );
				$i++;
			}
		} else {
			wp_enqueue_style( $this->plugin_name, PurifycssHelper::get_css_file(), array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Purifycss_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Purifycss_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

	}


	public function get_corresponding_css($content) {
        $css_identifier = PurifycssHelper::get_css_id_by_content($content);
        $files = PurifycssDb::get_by_src($css_identifier);

        foreach($files as $file) {
            return file_get_contents( PurifycssHelper::get_cache_dir_path() . $file->css );
        }

        /*echo "<pre>";
        print_r('ERROR: didnt find anything in DB for following inline css');
        print_r($css_identifier);
        echo "</pre>";*/
        return $content;
    }

    public function get_style_dependents($depsrc) {
        $files = PurifycssDb::get_by_src($depsrc);

        foreach($files as $file) {
            return PurifycssHelper::get_cache_dir_url() . $file->css;
        }

        return false;
    }

}
