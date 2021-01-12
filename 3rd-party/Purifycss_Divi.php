<?php


class Purifycss_Divi extends Purifycss_ThirdPartyExtension {

    public function run() {
        if ($this->pageBuilderActive()) {
            add_filter('purifycss_should_run', function() {return false;});
        }
    }

    private function pageBuilderActive() {
        // INFO: this checks several page builder plugins not just divi
        $pagebuilder_params = array('tve', 'elementor-preview', 'fl_builder', 'vc_action', 'et_fb', 'bt-beaverbuildertheme', 'ct_builder', 'fb-edit', 'siteorigin_panels_live_editor' );
        foreach ( $pagebuilder_params as $pb ) {
            if ( array_key_exists( $pb, $_GET ) ) {
                PurifycssDebugger::log("Pagebuilder is active! Purifycss shouldn't run. ");
                return true;
            }
        }
        return false;
    }

}
