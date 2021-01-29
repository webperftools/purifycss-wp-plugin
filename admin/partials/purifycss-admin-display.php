<div class="purifycss-body">
    <h1>PurifyCSS</h1>

   <?php


      if (isset($_GET['setCustomApi']) || isset($_COOKIE['purifycss_api_host'])) { ?>
         <script>
            function setPurifyApiHost(){
               const value = document.getElementById('purifycss_api_host').value;
               const nextYear = new Date().getFullYear() + 1;
               document.cookie=`purifycss_api_host=${value};path=/;domain=${window.location.hostname};expires=Sat, 01 Jan ${nextYear} 00:00:01 GMT`;
               afterApiHostChange();
            }
            function clearPurifyApiHost() {
               document.cookie=`purifycss_api_host=;path=/;domain=${window.location.hostname};expires=Thu, 01 Jan 1970 00:00:01 GMT`;
               afterApiHostChange()
            }
            function afterApiHostChange() {
               document.location.replace(document.location.href.replace('&setCustomApi',''));
            }
         </script>

   Custom API URL:
   <input type="text" style="width: 250px;" id="purifycss_api_host" placeholder="API URL" value="<?=$_COOKIE['purifycss_api_host']?>" />
         <button class="button button-primary" onClick="setPurifyApiHost()">Set</button>
         <button class="button button-primary" onClick="clearPurifyApiHost()">Clear</button>
   <?php }
   ?>

    <?php
    $status = "";

    if (get_option('purifycss_livemode')=='1') { $status = "active for all users";}
    else if (get_option('purifycss_testmode')=='1') { $status = "active only for admin users";}
    else { $status = "not active"; }
    ?>
   <p>Purifycss is currently <span class="purifycss_status"><?=$status;?></span>.</p> <?php
    /* TODO update purifycss status after ajax update */ ?>


    <p>
        <button class="button inspan button-primary <?=get_option('purifycss_livemode')=='1'?'active':''?>" id="live_button">
            <span class="enable"><?=__('Enable Live Mode','purifycss')?></span>
            <span class="disable"><?=__('Disable Live Mode','purifycss')?></span>
        </button>
    </p>

    <p>
        <button class="button inspan <?=get_option('purifycss_testmode')=='1'?'active':''?>" id="test_button">
            <span class="enable"><?=__('Enable Test Mode','purifycss')?></span>
            <span class="disable"><?=__('Disable Test Mode','purifycss')?></span>
        </button>
    </p>

    <div class="manage-menus">

        <p><?=__('PurifyCSS API license key:','purifycss')?> <a href="https://www.webperftools.com/purifycss/license-key"><?=__('Get licence key','purifycss')?></a> </p>

        <p> 
            <input name="api-key" type="text" id="api-key" value="<?=get_option('purifycss_api_key')?>" autocomplete="off" class="regular-text"> 
            <button class="button button-primary " id="activate_button"><?=__('Activate','purifycss')?></button>
            <span class="activated-text green-text <?=get_option('purifycss_api_key_activated')==true?'':'d-none'?>"><span class="dashicons dashicons-yes"></span> Activated!</span>
        </p>
        
        <p class="expand-click"> <span class="dashicons dashicons-arrow-right"></span> <span class="clickable"><?=__('PurifyCSS options','purifycss')?> </span> </p>
        <div class="d-none pl-5 expand-block">
            <?=__('Custom HTML Code:','purifycss')?> <br/>
            <textarea class="html_editor" name="" id="customhtml_text" cols="100" rows="10" autocomplete="off"><?=stripslashes(base64_decode(get_option('purifycss_customhtml')))?></textarea>
        </div>

        <p>
            <button class="button button-primary mr-3" style="display:none" id="abort"><?=__('Abort job','purifycss')?></button>
            <button class="button button-primary mr-3 " id="startJob"><?=__('Start job','purifycss')?></button>
            <button class="button button-primary mr-3 " id="css_button"><?=__('Get clean CSS code','purifycss')?></button>
            <?php
            if (function_exists('w3tc_config')) {
                $w3tc_config = w3tc_config();
                if ($w3tc_config->get_boolean('pgcache.enabled', false)) {
                    echo "  Note: This will also purge your W3 Total Cache page cache.";
                }
            }
            ?>
        </p>
       <div class="crawl-summary"></div>
    </div>

   <p class="expand-click2"> <span class="dashicons dashicons-arrow-right"></span> <span class="clickable"><?=__('Exclude Urls / Disables PurifyCSS on these URLs','purifycss')?> </span> </p>
   <div class="d-none pl-5 expand-block2">
      <textarea class="html_editor" name="" id="purifycss_exclude_urls_text" cols="100" rows="10" autocomplete="off"><?=get_option('purifycss_excluded_urls')?></textarea>
   </div>

   <p class="expand-click3"> <span class="dashicons dashicons-arrow-right"></span> <span class="clickable"><?=__('Skip CSS Files','purifycss')?> </span> </p>
   <div class="d-none pl-5 expand-block3">
      <textarea class="html_editor" name="" id="purifycss_skip_css_files_text" cols="100" rows="10" autocomplete="off"><?=get_option('purifycss_skip_css_files')?></textarea>
   </div>


    <p>
        <button class="button button-primary" id="save_button"><?=__('Save settings','purifycss')?></button>
    </p>

</div>
