<?php

/**
 * The file that defines htlper function
 *
 * @link       https://github.com/f2re
 * @since      1.0.0
 *
 * @package    Purifycss
 * @subpackage Purifycss/includes
 */

class PurifycssHelper {

    /**
     * folder of style files store
     *
     * @var string
     */
    public static $cache_dir = 'generatedcss/';
    public static $style = 'style.pure.css';

    /**
     * Get css file content
     *
     * @return string
     */
    static public function get_css() {
        $file = self::get_cache_dir_path() . self::$style ;

        // return $file;
        if ( file_exists( $file ) ){
            return file_get_contents($file);
        }
        return "";
    }

    /**
     * Checks if PurifyCSS is enabled
     *
     * @return boolean
     */
    public static function is_enabled() {
        if (self::force_enabled()) return true;
        if (self::force_disabled()) return false;
        // if (is_admin()) return false;

        if (self::check_test_mode()) return true;
        if (self::check_live_mode()) return true;

        return false;
    }

    /**
     * Using parameter ?purify=false
     *
     * @return boolean
     */
    static public function force_disabled(){
        if (isset($_GET['purify']) && $_GET['purify']=='false' ) {
            return true;
        }
        return false;
    }

    /**
     * Using parameter ?purify=false
     *
     * @return boolean
     */
    static public function force_enabled(){
        if (isset($_GET['purify']) && $_GET['purify']=='true' ) {
            return true;
        }
        return false;
    }


    /**
     * get path to css file
     *
     * @return string $file
     */
    static public function get_css_file(){
        $file = self::get_cache_dir_path() . self::$style ;
        return $file;
    }

    /**
     * Check if LIVE mode enabled
     *
     * @return boolean
     */
    static public function check_live_mode(){
        if ( get_option('purifycss_livemode')=='1' ){
            return true;
        }
        return false;
    }

    /**
     * Check if TEST mode enabled
     *
     * @return boolean
     */
    static public function check_test_mode(){
        if(!function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }

        $cur_user = wp_get_current_user();
        if ( get_option('purifycss_testmode')=='1' && $cur_user->ID!==0 ){
            return true;
        }
        return false;
    }

    /**
     * write to db css map and files
     *
     * @param [type] $map
     * @param [type] $css
     * @return void
     */
    static public function save_css_to_db($css){
        self::cleanup_existing_files();

        $todb   = [];

        foreach ($css as $_obj){
            if ( isset($_obj['inline']) && $_obj['inline']==True ){
                $css_identifier = self::get_css_id_by_content($_obj['original']['content']);
            }else{
                $css_identifier = $_obj['url'];
            }

            $filename = md5($css_identifier.uniqid()).'.css';

            $_obj = apply_filters('purifycss_before_filesave', $_obj);

            $cssContent = $_obj['purified']['content'];
            if (isset($_obj['url'])) {
                $cssContent = self::fix_relative_paths($cssContent, $_obj['url']);
            }

            if (!is_dir(self::get_cache_dir_path())) {
                mkdir(self::get_cache_dir_path());
            }

            file_put_contents( self::get_cache_dir_path() . $filename , $cssContent);

            $todb[] = [
                'orig_css' => $css_identifier,
                'css'      => $filename,
                'before'    => $_obj['stats']['before'],
                'after'     => $_obj['stats']['after'],
                'used'      => $_obj['stats']['percentageUsed'],
                'unused'     => $_obj['stats']['percentageUnused'],
            ];
        }

        PurifycssDb::drop_table();
        PurifycssDb::create_table();

        PurifycssDb::insert($todb);


        return;
    }

    /**
     * generates a short string that can be used to identify an inline css block
     *
     * @param string $content content of inline css block
     * @return string
     */
    static public function get_css_id_by_content($content) {
        return substr(trim(preg_replace('/\s+/', ' ', $content)), 0, 100);
    }

    /**
     * converts all relative URLs in a CSS file to absolute URLs
     *
     * @param string $cssContent content of css file
     * @param string $url url of css file that is used for calculating absolute url from a relative url
     * @return string
     */
    public static function fix_relative_paths($cssContent, $url) {
        $path = dirname($url)."/";

        $search = '#url\((?!\s*[\'"]?(?:https?:)?//)\s*([\'"])?#';
        $replace = "url($1{$path}";
        $cssContent = preg_replace($search, $replace, $cssContent);

        return $cssContent;

    }

    /**
     * is debug mode enabled?
     *
     * @return boolean
     */
    public static function is_debug(){
        return isset($_GET['purifydebug']) && $_GET['purifydebug']=1;
    }

    public static function get_css_files_mapping() {
        $files = array();
        foreach(PurifycssDb::get_all() as $file) {
            $file->css_filename = $file->css;
            $file->css = PurifycssHelper::get_cache_dir_url() . $file->css;
            $files[] = $file;
        }
        return $files;
    }

    /**
     * remove existing files in cache directory
     *
     * @return void
     */
    private static function cleanup_existing_files() {
        $files = glob( self::get_cache_dir_path() . '*');
        foreach($files as $file){
            if(is_file($file)){
                unlink($file);
            }
        }
    }

    /**
     * get path of cache directory
     *
     * @return string
     */
    public static function get_cache_dir_path() {
        return plugin_dir_path( __DIR__ ) . self::$cache_dir;
    }

    /**
     * get url of cache directory
     *
     * @return string
     */
    public static function get_cache_dir_url() {
        return plugin_dir_url( __DIR__ ) . self::$cache_dir;
    }

}
