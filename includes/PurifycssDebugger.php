<?php


class PurifycssDebugger {
    public static $logs = array();

    public function debug_enqueued_styles() {
        global $wp_styles;

    }

    static public function log($msg) {
        error_log($msg); // TODO push to message log
        self::$logs[] = $msg;
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
