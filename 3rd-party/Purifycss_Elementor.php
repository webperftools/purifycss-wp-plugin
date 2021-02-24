<?php


class Purifycss_Elementor extends Purifycss_ThirdPartyExtension {

    public function run() {
        if (!PurifycssHelper::is_enabled()) return;
    }

}
