<?php

class PurifycssDb {

    public static $table_name = 'purifycss';

    public static function create_table(){
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
				`id` int(0) UNSIGNED AUTO_INCREMENT,
				`orig_css` varchar(512) NULL,
				`css` varchar(512) NULL,
				`before` varchar(20) NULL,
				`after` varchar(20) NULL,
				`used` varchar(20) NULL,
				`unused` varchar(20) NULL,
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

    public static function insert($values) {
        if ( count($values) == 0 ) { return; }

        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";

        $values =  array_reduce( $values, function( $acc, $item ) {
            $acc[] =" (".
                "'".$item['orig_css']."', ".
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

    public static function get_all() {
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";
        return $wpdb->get_results( "SELECT `orig_css`, `css`, `before`, `after`, `used`, `unused` from $table_name;" );
    }

    public static function get_by_src($src) {
        global $wpdb;
        $table_name = $wpdb->prefix ."purifycss";
        return $wpdb->get_results( "SELECT `css` from $table_name WHERE  `orig_css` LIKE '%$src%' ;" );
    }

}
