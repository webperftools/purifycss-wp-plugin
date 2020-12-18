<div class="purifycss-body">
    <h1>PurifyCSS</h1>

   <?php
      if(isset($_COOKIE['purifycss_api_host'])) {
         echo "<strong style='color:#b00;font-size:16px;'>Warning! Using custom API host: ".$_COOKIE['purifycss_api_host']."</strong>. ".
              "<a href=\"javascript:document.cookie='purifycss_api_host=;path=/;domain=.webperftools.com;expires=Thu, 01 Jan 1970 00:00:01 GMT'\">Remove cookie</a>";
      }
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

        <p class="result-block <?=get_option('purifycss_resultdata')!=''?'':'d-none'?>"><?=__('Result:','purifycss')?> <?=get_option('purifycss_resultdata')?> </p>

       <div class="editor-container" style="display:none">
          <p><?=__('Clean CSS code:','purifycss')?></p>
         <textarea class="css_editor" name="" id="purified_css" cols="100" rows="10"><?=PurifycssHelper::get_css();?></textarea>
       </div>
       <div class="file-mapping-container">
          <?php foreach (PurifycssHelper::get_css_files_mapping() as $key => $mapping) {?>
              <div class="purified-result-item">
                 <a href='<?=$mapping->orig_css;?>'><?=$mapping->orig_css;?></a><br>
                 &middot; clean css file: <a target="_blank" href='<?=$mapping->css;?>'><?=$mapping->css_filename;?></a><br>
                 &middot; <span style="display:inline-block;padding-right: 15px;">before: <strong><?=$mapping->before;?></strong></span>
                 <span style="display:inline-block;padding-right: 15px;">after: <strong><?=$mapping->after;?></strong> </span>
                 <span style="display:inline-block;padding-right: 15px;">used: <strong><?=$mapping->used;?></strong> </span>
                 <span style="display:inline-block;padding-right: 15px;">unused: <strong><?=$mapping->unused;?></strong> </span>
                 <br>
               </div>
          <?php } ?>
       </div>
    </div>

    <p>
        <button class="button button-primary" id="save_button"><?=__('Save settings','purifycss')?></button>
    </p>

</div>
