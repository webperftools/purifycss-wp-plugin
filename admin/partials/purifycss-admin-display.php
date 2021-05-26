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
    $status = 'off';
    if (get_option('purifycss_livemode')=='1') { $status = 'live'; }
    else if (get_option('purifycss_testmode')=='1') { $status = 'test';}
    else {$status = 'off';}
    ?>

   <script>var purifyStatus = '<?=$status?>';</script>
   <p>
         <strong style="font-size: 16px;">Status: </strong>
        <button class="button inspan <?=$status=='off' ? 'button-primary':''?> " id="off_button" title="Click to turn off PurifyCSS">Off</button>
        <button class="button inspan <?=$status=='test' ?'button-primary':''?>" id="test_button" title="Click to enable test mode. Only you and other logged in admin users will see the changes.">Test</button>
        <button class="button inspan <?=$status=='live' ?'button-primary':''?>" id="live_button" title="Click to enable live mode. All visitors will see the changes created by PurifyCSS! Make sure to test before enabling this!">Live</button>

      Purifycss is currently
      <span class="purifycss_status off" <?=($status!='off')?'style="display:none"':''?>> not active</span>
      <span class="purifycss_status test" <?=($status!='test')?'style="display:none"':''?>> active only for admin users</span>
      <span class="purifycss_status live" <?=($status!='live')?'style="display:none"':''?>> active for all users.</span>.
   </p>

    <?php
    $cacheDir = PurifycssHelper::check_cache_dir();
    if (!$cacheDir['is_writable']) { echo "<p>Cache directory is not writable. Couldn't create folder: ".$cacheDir['dir']."</p>";}
    ?>

    <div class="manage-menus">

        <p><?=__('PurifyCSS API license key:','purifycss')?> <a href="https://www.webperftools.com/purifycss/purchase-license/" target="_blank"><?=__('Get licence key','purifycss')?></a> </p>
        <p>
            <input name="api-key" type="text" id="api-key" value="<?=get_option('purifycss_api_key')?>" autocomplete="off" class="regular-text">
            <button class="button button-primary " id="activate_button"><?=__('Activate','purifycss')?></button>
        </p>

        <p class="expand-click"> <span class="dashicons dashicons-arrow-right"></span> <span class="clickable"><?=__('PurifyCSS options','purifycss')?> </span> </p>
        <div class="d-none pl-5 expand-block">
            <?=__('Custom HTML Code:','purifycss')?> <br/>
            <textarea class="html_editor" name="" id="customhtml_text" cols="100" rows="10" autocomplete="off"><?=stripslashes(base64_decode(get_option('purifycss_customhtml')))?></textarea>
        </div>

        <p>
            <button class="button button-primary mr-3" style="display:none" id="abort"><?=__('Abort job','purifycss')?></button>
            <button class="button button-primary mr-3 " id="startJob"><?=__('Start job','purifycss')?></button>


            <?php
            if ($status == 'live') {
                echo "  Note: This will turn off PurifyCSS to enable the background processes to fetch the original CSS sources.";
            }
            if (function_exists('w3tc_config')) {
                $w3tc_config = w3tc_config();
                if ($w3tc_config->get_boolean('pgcache.enabled', false)) {
                    echo "  Note: This will also purge your W3 Total Cache page cache.";
                }
            }
            ?>
        </p>
       <div class="crawl-summary">

          <?php

          echo get_option('purifycss_runningjob');

         echo "<table>";
          echo "<tr>";
          echo "<td>URL</td>";
          echo "<td>PurifyCSS</td>";
          echo "<td>CriticalCSS</td>";
          echo "</tr>";

          foreach(PurifycssHelper::get_pages_files_mapping() as $item) {
             if ( $item->criticalcss ) {
                 $criticalFileSize = filesize(PurifycssHelper::get_cache_dir_path() . $item->criticalcss);
                 if ($criticalFileSize) {
                     $criticalFileSize = round($criticalFileSize / 1024 , 1).' kb';
                 }
             }
              echo "<tr>";
              echo "<td>".$item->url ."</td>";
              echo "<td align='center'>".($item->css ? $item->used : '--') ."</td>";
              echo "<td align='center' title='".$item->criticalcss."'>".($item->criticalcss ? $criticalFileSize : '--') ."</td>";
              echo "</tr>";
          }
          echo "</table>";

          ?>


       </div>
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
      <input name="skipCriticalCss" type="checkbox" id="skipCriticalCss" <?php echo get_option('purifycss_skip_critical_css', 'false') === 'true' ? 'checked' : '';?> autocomplete="off" class="regular-text">
      <label for="skipCriticalCss">Skip critical CSS</label>
   </p>

    <p>
        <button class="button button-primary" id="save_button"><?=__('Save settings','purifycss')?></button>
    </p>

</div>
