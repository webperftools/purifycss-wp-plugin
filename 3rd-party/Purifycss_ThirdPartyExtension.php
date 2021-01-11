<?php


abstract class Purifycss_ThirdPartyExtension {
    protected $public;

    public function __construct($public) {
        $this->public = $public;
    }
}
