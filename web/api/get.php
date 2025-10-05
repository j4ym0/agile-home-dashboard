<?php

// Just for testing 
$debug = false;
if ($debug){
    echo 'URL parameters:\n';  
    foreach ($_GET as $key => $value) {
        echo "$key: $value\n";
    }
    die();
}

// Check user is logged in
if (!$auth->is_logged_in()) {
    header("HTTP/1.0 401 Unauthorized");
    die('User not logged in');
}

// If no endfunction was given or function does not exist
if ($endFunction == 'none' || !function_exists($endFunction)){
    header("HTTP/1.0 404 Not Found");
    die('Endpoint not found');
}

// Run and encode response as json
$json = json_encode($endFunction());

// Print the json
header('Content-Type: application/json');
echo $json;
die();

####################################################

function settings_octopus_account_info(){
    global $settings, $db;
    $ret = [];

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
        $settings->set('electricity_meter_MPAN', '');
        $settings->set('electricity_meter_serial', '');
        $settings->set('electricity_product_code', '');
        $settings->set('electricity_tariff_code', '');
    }

    $ret['electricity_product_code'] =$settings->get('electricity_product_code', '');
    $ret['electricity_tariff_code'] = $settings->get('electricity_tariff_code', '');
    $ret['electricity_meter_MPAN'] = $settings->get('electricity_meter_MPAN', '');
    $ret['electricity_meter_serial'] = $settings->get('electricity_meter_serial', '');

    return $ret;
}

function price_list(){
    global $settings, $db;
    $ret = ['error' => false,
            'message' => ''];
    $date = $_GET['date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $ret['error'] = true;
        $ret['message'] = 'Unable to validate date';
    }else{
        $octopus = new Octopus($db, $settings);
        $octopusTariff = $octopus->getTariffData($settings->get('electricity_product_code', 'A-'), 
            $settings->get('electricity_tariff_code', ''),
            "$date 00:00:00",
            "$date 23:59:59");
        
        $ret['tariff'] = $octopusTariff['tariff'];
        $ret['average_price_inc_vat'] = $octopusTariff['average_price_inc_vat'];
    }

    return $ret;
}
function electric_usage(){
    global $settings, $db;
    $ret = ['error' => false,
            'message' => ''];
    $date = $_GET['date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $ret['error'] = true;
        $ret['message'] = 'Unable to validate date';
    }else{
        $octopus = new Octopus($db, $settings);
        $octopusTariff = $octopus->getConsumptionData($settings->get('electricity_meter_MPAN', ''), 
            $settings->get('electricity_meter_serial', ''),
            "$date 00:00:00",
            "$date 23:59:59");
        
        $ret['electricity'] = $octopusTariff['electricity'];
        $ret['electricity_total_consumption'] = $octopusTariff['electricity_total_consumption'];
        $ret['electricity_average_consumption'] = $octopusTariff['electricity_average_consumption'];
    }

    return $ret;
}
function standard_tariff(){
    global $settings, $db;
    $ret = ['error' => false,
            'message' => ''];
    $date = $_GET['date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $ret['error'] = true;
        $ret['message'] = 'Unable to validate date';
    }else{
        $octopus = new Octopus($db, $settings);
        $octopusStandardTariff = $octopus->getStandardTariff($settings->get('electricity_tariff_code', ''), "$date 00:00:00");
        
        $ret['electricity_standard_tariff'] = $octopusStandardTariff['electricity_standard_tariff'];
    }

    return $ret;
}
function current_consumption(){
    global $settings, $db;
    $ret = ['error' => false,
            'message' => ''];
    $date = $_GET['date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $ret['error'] = true;
        $ret['message'] = 'Unable to validate date';
    }else{
        $octopus = new Octopus($db, $settings);
        $octopusTelematryData = $octopus->getHomeTelemetry($octopus->getDeviceID());

        if (count($octopusTelematryData) > 0){
            $ret['electricity_current_consumption'] = ($octopusTelematryData[count($octopusTelematryData)-1]['demand']  / 1000);
        }   
    }
    return $ret;
}
function dashboard_data(){
    $ret = ['error' => false,
            'message' => ''];
    $date = $_GET['date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $ret['error'] = true;
        $ret['message'] = 'Unable to validate date';
    }else{
        // Get the data and combine into one array
        $tariff = price_list();
        $standardTariff = standard_tariff();
        $consumption = electric_usage();
        $current_consumption = current_consumption();
        $ret = array_merge($tariff, $standardTariff, $consumption, $current_consumption);

        // Calculate the electric cost
        $electricity_total_cost = 0;
        $electricity_total_plunge_cost = 0;
        $electricity_total_plunge_consumption = 0;
        $electricity_below_average = 0;

        if (count($consumption['electricity']) >= count($tariff['tariff'])){
            for ($i = 0; $i < count($consumption['electricity']); $i++) {
                // check it is the same time
                // TODO if tariff date mismatch find it
                $the_tariff = $tariff['tariff'][count($tariff['tariff'])-1];
                if (count($tariff['tariff']) > $i){
                    $the_tariff = $tariff['tariff'][$i];
                }
                if(new DateTime($the_tariff['valid_from']) != new DateTime($consumption['electricity'][$i]['interval_start'])){
                    for ($t = 0; $t < count($tariff['tariff']); $t++) {
                        if(new DateTime($tariff['tariff'][$t]['valid_from']) != new DateTime($consumption['electricity'][$i]['interval_start'])){
                            $the_tariff = $tariff['tariff'][$t];
                        }
                    }
                }
                $electricity_total_cost += $the_tariff['price_inc_vat'] * $consumption['electricity'][$i]['consumption'];
                if ($the_tariff['price_inc_vat'] <= 0){
                    $electricity_total_plunge_cost += $the_tariff['price_inc_vat'] * $consumption['electricity'][$i]['consumption'];
                    $electricity_total_plunge_consumption += $consumption['electricity'][$i]['consumption'];
                }
                if ($the_tariff['price_inc_vat'] <= 0){
                    $electricity_total_plunge_cost += $the_tariff['price_inc_vat'] * $consumption['electricity'][$i]['consumption'];
                    $electricity_total_plunge_consumption += $consumption['electricity'][$i]['consumption'];
                }
                if ($the_tariff['price_inc_vat'] < $ret['average_price_inc_vat']){
                    $electricity_below_average += $consumption['electricity'][$i]['consumption'];
                }
            }
        }
        $ret["electricity_total_cost"] = round($electricity_total_cost , 4);;
        $ret["electricity_total_plunge_cost"] = round($electricity_total_plunge_cost, 4);
        $ret["electricity_total_plunge_consumption"] = $electricity_total_plunge_consumption;
        $ret["electricity_consumption_below_average"] = $ret['electricity_total_consumption'] ? round((100 / $ret['electricity_total_consumption']) * $electricity_below_average, 0) : 0;
        $ret["electricity_compare_standard_tariff"] = $ret['electricity_total_consumption'] ? (100 / $ret['electricity_total_consumption']) * $electricity_below_average : 0;
    }
    return $ret;
}