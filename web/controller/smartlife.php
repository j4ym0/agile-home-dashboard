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

$template->assign('tuya_auto_refresh', $settings->get('tuya_auto_refresh', false) ? ' checked' : '');
$template->assign('tuya_auto_refresh_interval', Config::get('TUYA_AUTO_REFRESH_INTERVAL', 30));

if (isset($_GET['device_id'])){
    $template->assign('tuya_device_id', $_GET['device_id']);
}
