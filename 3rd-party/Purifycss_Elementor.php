<?php


class Purifycss_Elementor extends Purifycss_ThirdPartyExtension {

    public function run() {
        $this->loader->add_action( 'elementor/frontend/after_enqueue_styles', $this->public, 'replace_all_styles', PHP_INT_MAX );
    }

}
