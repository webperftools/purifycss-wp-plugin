<?php


abstract class Purifycss_ThirdPartyExtension {

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Purifycss_Public    $public    The string used to uniquely identify this plugin.
     */
    protected $public;

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Purifycss_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;


    public function __construct($loader, $public) {
        $this->loader = $loader;
        $this->public = $public;
    }
}
