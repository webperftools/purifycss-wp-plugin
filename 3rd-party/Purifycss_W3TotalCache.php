<?php


class Purifycss_W3TotalCache extends Purifycss_ThirdPartyExtension {

    public function run() {
        if($this->w3tc_enabled()) {
            add_action('purifycss_before_api_request', array($this, 'flush_cache'));
            add_action('purifycss_after_onoff', array($this, 'flush_cache'), 100, 1);
        }
    }

    public function flush_cache(...$args) {
        error_log("Purifycss: w3tc_flush_all");
        w3tc_flush_all();
        sleep(1); // ugly workaround to wait for flushing to take effect
    }

    private function w3tc_enabled() {
        if (function_exists('w3tc_config')) {
            $w3tc_config = w3tc_config();
            if ($w3tc_config->get_boolean('pgcache.enabled', false)) {
                return true;
            }
        }
        return false;
    }

}
