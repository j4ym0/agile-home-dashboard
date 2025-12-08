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

// If no endpoint given or function does not exist
if ($endFunction == 'none' || !function_exists($endFunction)){
    header("HTTP/1.0 404 Not Found");
    die('Endpoint not found');
}

//check tuya is working
try{
    $tuya = new Tuya($db, $settings);
}catch (Exception $e){
    header("HTTP/1.0 500 Server Error");
    die('Tuya Error');
}

// Run and encode response as json
$json = json_encode($endFunction());

// Print the json
header('Content-Type: application/json');
echo $json;
die();

####################################################

function set_switch_status(){
    global $settings, $db, $tuya;
    $ret = [
        'success' => false,
        'message' => 'Unable to switch'
    ];  

    $device_id = $_POST['device'] ?? '';
    $state =  $_POST['value'] ?? '';
    // convert to bool
    $state = filter_var($state, FILTER_VALIDATE_BOOLEAN);

    try{
        if (!$device_id == '' || !$state == ''){
            if ($tuya->setSwitchState($device_id, $state)){
                $ret = [
                    'success' => true,
                    'message' => 'Switched ' . ($state ? 'On' : 'Off')
                ];
            }
        }
    }catch (Exception $e){
        $ret = [
            'success' => false,
            'message' => 'Error setting switch state: ' . $e->getMessage()
        ];  
    }

    return $ret;
}
function device(){
    global $settings, $db, $tuya;
    $ret = [
        'success' => false,
        'message' => 'Unable to get device details'
    ];  

    $device_id = $_POST['device'] ?? '';

    try{
        if (!$device_id == ''){
            $ret = [
                'success' => true,
                'device' => $tuya->getDeviceDetails($device_id)
            ];
        }
    }catch (Exception $e){
        $ret = [
            'success' => false,
            'message' => 'Error getting device: ' . $e->getMessage()
        ];  
    }

    return $ret;
}
