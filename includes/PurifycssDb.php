<?php

class PurifycssDb {

    public static $table_name = 'purifycss';

    public static function pages_table_exists(){
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";
        $res = $wpdb->query("SHOW TABLES LIKE '$table_name';");

        return $res != 0 ;
    }

    public static function create_pages_table(){
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";
        $charset_collate = $wpdb->get_charset_collate();

        error_log("create table");
        $sql = "CREATE TABLE $table_name (
				`id` int(0) UNSIGNED AUTO_INCREMENT,
				`url` varchar(512) NULL,
				`css` varchar(512) NULL,
				`before` varchar(20) NULL,
				`after` varchar(20) NULL,
				`used` varchar(20) NULL,
				`unused` varchar(20) NULL,
				`criticalcss` varchar(512) NULL,
				PRIMARY KEY (`id`)
				) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }


    public static function drop_table() {
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";
        $wpdb->query("DROP TABLE $table_name");
    }
    public static function drop_pages_table() {
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";
        $wpdb->query("DROP TABLE $table_name");
    }

    public static function insert($values) {
        if ( count($values) == 0 ) { return; }

        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";

        $values =  array_reduce( $values, function( $acc, $item ) {
            $acc[] =" (".
                "'".esc_sql($item['orig_css'])."', ".
                "'".$item['css']."', ".
                "'".$item['before']."', ".
                "'".$item['after']."', ".
                "'".$item['used']."', ".
                "'".$item['unused']."' ".
                ") ";
            return $acc;
        } );

        $wpdb->query("INSERT INTO $table_name (`orig_css`, `css`, `before`, `after`, `used`, `unused`) VALUES ".join(',',$values).";");
    }

    public static function insert_pages($values) {
        if ( count($values) == 0 ) { return; }
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";

        $values =  array_reduce( $values, function( $acc, $item ) {

            $acc[] =" (".
                "'".$item['url']."', ".
                "'".$item['css']."', ".
                "'".$item['before']."', ".
                "'".$item['after']."', ".
                "'".$item['used']."', ".
                "'".$item['unused']."', ".
                "'".esc_sql($item['criticalcss'])."' ".
                ") ";
            return $acc;
        } );

        if (!self::pages_table_exists()) {
            self::create_pages_table();
        }

        $wpdb->query("INSERT INTO $table_name (`url`, `css`, `before`, `after`, `used`, `unused`, `criticalcss`) VALUES ".join(',',$values).";");
    }

    public static function clear_url($url) {
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";

        $url_trimmed = untrailingslashit($url); // workaround //  TODO: normalize urls before insert

        if (self::pages_table_exists()) {
            $res = $wpdb->query("DELETE FROM $table_name WHERE `url` = '$url' OR `url` = '$url_trimmed'");
        }
    }

    public static function get_all() {
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";

        if (!self::pages_table_exists()) {
            return false;
        }

        return $wpdb->get_results( "SELECT `orig_css`, `css`, `before`, `after`, `used`, `unused` from $table_name;" );
    }

    public static function get_all_pages() {
        if (!self::pages_table_exists()) { return []; }
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";
        return $wpdb->get_results( "SELECT `url`, `css`, `before`, `after`, `used`, `unused`, `criticalcss` from $table_name;" );
    }

    public static function insert_data($data, $single) {
        PurifycssDebugger::log("inserting data to db. Single page:".$single);

        $values = [];
        if (!$single) {
            self::drop_pages_table();
            self::create_pages_table();
        } else if (!self::pages_table_exists()) {
            self::create_pages_table();
        }

        foreach ($data['urls'] as $urlData) {
            if ($urlData['crawl']['status'] == 'failed') continue;

            PurifycssDebugger::log("  url:".$urlData['url']);

            $before = $urlData['fullCss']['stats']['bytes'];
            $after = $urlData['purifyCss']['stats']['bytes'];
            $values[] = [
                'url' => $urlData['url'],
                'css' => self::hasValidPurifyData($urlData) ? $urlData['purifyCss']['filename'] : '',
                'before' => self::format($before),
                'after' => self::format($after),
                'used' => self::percent($after, $before),
                'unused' => self::percent($before-$after, $before),
                'criticalcss' => self::hasValidCriticalData($urlData) ? $urlData['criticalCss']['filename'] : '',
            ];
        }


        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss_pages";

        $values =  array_reduce( $values, function( $acc, $item ) {
            $acc[] =" (".
                "'".$item['url']."', ".
                "'".$item['css']."', ".
                "'".$item['before']."', ".
                "'".$item['after']."', ".
                "'".$item['used']."', ".
                "'".$item['unused']."', ".
                "'".$item['criticalcss']."' ".
                ") ";


            return $acc;
        } );

        if ($single) {
            foreach ($data['urls'] as $urlData) {
                $deleteQuery = "DELETE FROM $table_name WHERE `url` = '".$urlData['url']."'; ";
                $wpdb->query($deleteQuery);
            }
        }
        $query = "INSERT INTO $table_name (`url`, `css`, `before`, `after`, `used`, `unused`, `criticalcss`) VALUES ".join(',',$values).";";
        $res = $wpdb->query($query);

        PurifycssDebugger::log("  query result:".print_r($res,1));
        if (!$res) {
            PurifycssDebugger::log($wpdb->last_error);
        }

    }

    private static function hasValidPurifyData($urlData) {
        return array_key_exists('purifyCss',$urlData)
            && array_key_exists('filename', $urlData['purifyCss'])
            && PurifycssHelper::file_exists($urlData['purifyCss']['filename']);
    }

    private static function hasValidCriticalData($urlData) {
        return array_key_exists('criticalCss',$urlData)
            && array_key_exists('filename', $urlData['criticalCss'])
            && PurifycssHelper::file_exists($urlData['criticalCss']['filename']);
    }

    private static function format($bytes) {
        return $bytes;
    }

    private static function percent($a, $b) {
        if ($b == 0) return '-';
        return round(100*$a/$b, 2)."%";
    }

}
