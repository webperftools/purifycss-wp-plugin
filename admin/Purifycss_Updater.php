<?php

class Purifycss_Updater {

    private $plugin_name;
    private $current_version;
    private $latest_version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->current_version = $version;
        $this->latest_version = false;
    }

    public function check_plugins_updates($update_transient) {
        global $wp_version;

        if ( ! isset( $update_transient->response ) ) {
            return $update_transient;
        }

        if ($this->update_available()) {
            $transient_obj = (object) array(
                "id" => "purifycss/purifycss",
                "slug" => "purifycss",
                "plugin" => "purifycss/purifycss.php",
                "new_version" => $this->latest_version,
                "url" => "https://www.webperftools.com/purifycss/purifycss-worpdress-plugin/",
                "package" => "https://www.webperftools.com/dist/purifycss.zip",
                "icons" => array(
                    "2x" => "https://placehold.it/128x128/3F71AB/FFFFFF?text=PurifyCSS",
                    "1x" => "https://placehold.it/128x128/3F71AB/FFFFFF?text=PurifyCSS"
                ),
                "banners" => array(
                    "2x" => "https://placehold.it/1544x500/3F71AB/FFFFFF?text=PurifyCSS+WordPress+Plugin",
                    "1x" => "https://placehold.it/772x250/3F71AB/FFFFFF?text=PurifyCSS+WordPress+Plugin"
                ),
                "banners_rtl" => array(),
                "tested" => $this->current_version,
                "requires_php" => "5.2",
                "compatibility" => array()
            );

            $update_transient->response['purifycss/purifycss.php'] = $transient_obj;
        }

        return $update_transient;
    }

    private function update_available() {
        if (!$this->latest_version) {
            $this->latest_version = file_get_contents('https://www.webperftools.com/dist/purifycss-latest-version.txt');
        }
        if (!$this->latest_version) {
            error_log('Purifycss failed to get the latest version number?');
            return false;
        }

        $this->latest_version = trim($this->latest_version);
        $semver = explode('.',$this->latest_version);
        if (count($semver) !== 3) {
            error_log('Purifycss latest_version number is invalid?'. $this->latest_version);
            return false;
        }
        if (!is_numeric($semver[2])) {
            error_log('Purifycss latest_version number is invalid?'. $this->latest_version);
            return false;
        }

        $current_semver = explode('.',trim($this->current_version));
        if (count($current_semver) !== 3) {
            error_log('Purifycss has an invalid version number? '.$this->current_version);
            return false;
        }
        if (!is_numeric($this->current_version[2])) {
            error_log('Purifycss has an invalid version number? '.$this->current_version);
            return false;
        }

        if ($semver[0] > $current_semver[0]) return true;
        if ($semver[1] > $current_semver[1]) return true;
        if ($semver[2] > $current_semver[2]) return true;

        return false;
    }

}
