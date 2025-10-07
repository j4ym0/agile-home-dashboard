<?php
$template->append('postRelLinks', ['rel' => 'javascript', 'href' => '/js/smartlife.js']);
$template->append('postRelLinks', ['rel' => 'javascript', 'href' => '/js/main.js']);


try{
    // check if configured
    if (!$settings->get('tuya_configured')){
        throw new Exception('Not configured');
    }

    // check tuya is working
    $tuya = new Tuya($db, $settings);

} catch (Exception $e){
    $template->assign('tuya_config_error', true);
}

$template->assign('tuya_auto_refresh', $settings->get('home_mini_live_data', false) ? ' checked' : '');
