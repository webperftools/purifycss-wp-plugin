<?php


class PurifycssDebugger {
    public static $logs = array();
    public static $cleared = false;

    public function debug_enqueued_styles() {
        global $wp_styles;
    }

    static public function log($msg) {
        self::$logs[] = $msg;

        $logfile = WP_CONTENT_DIR.PurifycssHelper::$cache_dir.'debug_log.txt';

        if (isset($_GET['purify_clearlogs']) && !self::$cleared) {
            file_put_contents($logfile,'');
            self::$cleared = true;
        }

        file_put_contents($logfile,$msg."\n", FILE_APPEND);
    }

    static public function isDebugEnabled() {
        if (isset($_GET['purifydebug']) && $_GET['purifydebug'] == 'true') return true;
        if (isset($_COOKIE['purifydebug']) && $_COOKIE['purifydebug'] == 'true') return true;
        return false;
    }

    static public function print_debug_logs() {
        if (!self::isDebugEnabled()) return;

        echo "<!--PurifycssDebugger--><script>";
        foreach (self::$logs as $msg) {
            echo "console.log(".json_encode($msg).");";
        }
        echo "</script>";
    }
}
