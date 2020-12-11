<?php


abstract class Purifycss_ThirdPartyExtension {
    protected $public;
    protected $loader;

    public function __construct($loader, $public) {
        $this->loader = $loader;
        $this->public = $public;
    }
}
