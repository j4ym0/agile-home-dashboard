<?php

$template->append('postRelLinks', ['rel' => 'javascript', 'href' => '/js/main.js']);

// Update the Octopus account info in the database
try{
    $octopus = new Octopus($db, $settings);
    $octopusTariff = $octopus->getCurrentTariffFromAccount();
    $settings->set('electricity_product_code', $octopusTariff['electricity_product_code']);
    $settings->set('electricity_tariff_code', $octopusTariff['electricity_tariff_code']);
    $settings->set('electricity_meter_MPAN', $octopusTariff['supply'][0]['mpan']);
    // Get the last meter that is listed / installed
    $settings->set('electricity_meter_serial', $octopusTariff['supply'][0]['meters'][count($octopusTariff['supply'][0]['meters']) - 1]['serial_number']);
    $settings->set('is_setup', true);
    // TODO: multiple meters and catch errors
}catch (Exception $e){
    // TODO: some error handling
    $settings->set('is_setup', false);
}

$template->assign('api_key', $settings->get('api_key', ''));
$template->assign('account_number', $settings->get('account_number', 'A-'));
$template->assign('electricity_meter_MPAN', $settings->get('electricity_meter_MPAN', ''));
$template->assign('electricity_meter_serial', $settings->get('electricity_meter_serial', ''));
$template->assign('electricity_product_code', $settings->get('electricity_product_code', ''));
$template->assign('electricity_tariff_code', $settings->get('electricity_tariff_code', ''));

$template->assign('tuya_access_id', $settings->get('tuya_access_id', ''));
$template->assign('tuya_secret', $settings->get('tuya_secret', ''));
$template->assign('tuya_account_uid', $settings->get('tuya_account_uid', ''));

$template->assign('save_tariff_data', $settings->get('save_tariff_data', false) ? 'checked' : '');
$template->assign('save_consumption_data', $settings->get('save_consumption_data', false) ? 'checked' : '');
$template->assign('save_standard_tariff_data', $settings->get('save_standard_tariff_data', false) ? 'checked' : '');
$template->assign('home_mini_live_data', $settings->get('home_mini_live_data', false) ? 'checked' : '');

