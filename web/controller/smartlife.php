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

    if (isset($_GET['device_id'])){
        $template->assign('tuya_device_id', $_GET['device_id']);
        $device = $tuya->getDeviceDetails($_GET['device_id']);
        $template->assign('tuya_device_name', $device['name'] ?? 'Unknown Device');
        $template->assign('tuya_device_local_key', $device['local_key'] ?? 'Unknown');
        $template->assign('tuya_device_model', $device['model'] ?? 'Unknown');
        $template->assign('tuya_device_product_name', $device['product_name'] ?? 'Unknown');
        $template->assign('tuya_device_icon', $device['icon'] ?? 'Unknown');
        $template->assign('tuya_device_online', $device['online'] ?? 'Unknown');
        $template->assign('tuya_device_update_time', $device['update_time'] ?? 'Unknown');
        $template->assign('tuya_device_status', $device['status'] ?? []);
        
        $template->assign('tuya_device_current_power', $tuya->getCurrentPower($device) ?? '');
        
    }

} catch (Exception $e){
    $template->assign('tuya_config_error', true);
}

$template->assign('tuya_auto_refresh', $settings->get('tuya_auto_refresh', false) ? ' checked' : '');
$template->assign('tuya_auto_refresh_interval', Config::get('TUYA_AUTO_REFRESH_INTERVAL', 30));

