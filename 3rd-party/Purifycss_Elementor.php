<?php


class Purifycss_Elementor extends Purifycss_ThirdPartyExtension {

    public function run() {
        if (!PurifycssHelper::is_enabled()) return;

        add_action( 'elementor/frontend/after_enqueue_styles', array($this->public, 'replace_all_styles'), PHP_INT_MAX );
    }

}
