<?php

class PurifycssHelper {

    public static $cache_dir = '/cache/purifycss/';
    public static $style = 'style.pure.css';

    static public function get_css() {
        $file = self::get_cache_dir_path() . self::$style ;

        if ( file_exists( $file ) ){
            return file_get_contents($file);
        }
        return "";
    }

    public static function is_enabled($current_url = null) {
        if (self::is_customizer()) return false;
        if (self::force_enabled()) return true;
        if (self::force_disabled()) return false;

        if (self::check_test_mode()) return true;
        if (self::check_live_mode()) return true;

        return false;
    }

    static public function force_disabled(){
        if (isset($_GET['purify']) && $_GET['purify']=='false' ) {
            return true;
        }
        return false;
    }

    static public function force_enabled(){
        if (isset($_GET['purify']) && $_GET['purify']=='true' ) {
            return true;
        }
        return false;
    }

    public static function isExcluded() {
        if (self::is_customizer()) return true;

        global $wp;
        $url = home_url($wp->request);

        $excludedUrls = get_option('purifycss_excluded_urls');
        foreach (explode("\n",$excludedUrls) as $exclUrl) {
            if (untrailingslashit($exclUrl) === untrailingslashit($url)) return true;
        }
        return false;
    }

    public static function is_customizer() {
        global $wp_customize;
        return isset( $wp_customize );
    }

    static public function get_css_file(){
        $file = self::get_cache_dir_path() . self::$style ;
        return $file;
    }

    static public function check_live_mode(){
        if ( get_option('purifycss_livemode')=='1' ){
            return true;
        }
        return false;
    }

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

    static public function add_pages($html){
        $todb   = [];
        foreach ($html as $_obj){
            $page_url = $_obj['url'];
            $filename = md5($page_url.uniqid()).'.css';

            $_obj = apply_filters('purifycss_before_filesave', $_obj);

            $purifiedObj = $_obj['styles']['purified'];
            $cssContent = array_key_exists('purified',$purifiedObj) ? $purifiedObj['purified']['content'] : '';

            if (!is_dir(self::get_cache_dir_path())) {
                mkdir(self::get_cache_dir_path());
            }
            file_put_contents( self::get_cache_dir_path() . $filename , $cssContent);

            $todb[] = [
                'url'       => $page_url,
                'css'       => $filename,
                'before'    => array_key_exists('stats',$purifiedObj) ? $purifiedObj['stats']['before'] : '',
                'after'     => array_key_exists('stats',$purifiedObj) ? $purifiedObj['stats']['after'] : '',
                'used'      => array_key_exists('stats',$purifiedObj) ? $purifiedObj['stats']['percentageUsed'] : '',
                'unused'    => array_key_exists('stats',$purifiedObj) ? $purifiedObj['stats']['percentageUnused'] : '',
                'criticalcss'  => array_key_exists('critical',$purifiedObj) ? $purifiedObj['critical'] : '',
            ];
        }

        PurifycssDb::insert_pages($todb);
    }

    static public function get_css_id_by_content($content) {
        return substr(trim(preg_replace('/\s+/', ' ', $content)), 0, 100);
    }

    public static function is_debug(){
        return isset($_GET['purifydebug']) && $_GET['purifydebug']=1;
    }

    public static function get_pages_files_mapping() {
        $files = array();
        foreach(PurifycssDb::get_all_pages() as $file) {
            $file->css = PurifycssHelper::get_cache_dir_url() . $file->css;
            $files[] = $file;
        }
        return $files;
    }

    private static function cleanup_existing_files() {
        $files = glob( self::get_cache_dir_path() . '*');
        foreach($files as $file){
            if(is_file($file)){
                unlink($file);
            }
        }
    }

    public static function get_cache_dir_path() {
        return WP_CONTENT_DIR . self::$cache_dir;
    }

    public static function get_cache_dir_url() {
        return WP_CONTENT_URL . self::$cache_dir;
    }

    public static function file_exists($filename) {
        return is_file(PurifycssHelper::get_cache_dir_path() . $filename);
    }
}
