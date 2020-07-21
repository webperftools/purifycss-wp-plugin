<?php


class Purifycss_Autoptimize extends Purifycss_ThirdPartyExtension {

    public function run() {
        $this->loader->add_filter( 'autoptimize_html_after_minify', $this, 'replace_styles', 20 );
    }

    public function replace_styles($html) {
        $str = "<!--\n";

        foreach ($this->public->files as $file) {
            $str.= $file->orig_css."\n".$file->css."\n\n";
            $html = str_replace($file->orig_css, $file->css, $html);
        }
        $str .= "-->";

        return '<!-- purifycss -->'.$str.$html;
    }

}
