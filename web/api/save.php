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

function api_info(){
    global $settings;
    $ret = ['error' 	=> false,
            'message' 	=> 'Saved'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        // Get and trim all input
        $api_key = trim($_POST['api_key'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $meter_MPAN = trim($_POST['meter_MPAN'] ?? '');
        $meter_serial = trim($_POST['meter_serial'] ?? '');

        // Prep for database insertion
        $api_key = htmlspecialchars($api_key, ENT_QUOTES, 'UTF-8');
        $account_number = htmlspecialchars($account_number, ENT_QUOTES, 'UTF-8');
        $meter_MPAN = htmlspecialchars($meter_MPAN, ENT_QUOTES, 'UTF-8');
        $meter_serial = htmlspecialchars($meter_serial, ENT_QUOTES, 'UTF-8');
        
        // Save to database
        if (!$settings->set('api_key', $api_key)){
            $ret['error'] = true;
            $ret['message'] = 'Unable to save API Key';
        }
        if (!$settings->set('account_number', $account_number)){
            $ret['error'] = true;
            $ret['message'] = 'Unable to save your account number';
        }
        if ($api_key === ''){
            $settings->set('electricity_meter_MPAN', '');
            $settings->set('electricity_meter_serial', '');
            $settings->set('electricity_product_code', '');
            $settings->set('electricity_tariff_code', '');
        }

        // Update the is_setup
        if ($ret['error']){
            $settings->set('is_setup', false);
        }else{
            $settings->set('is_setup', true);
        }

    }else{
        $ret['error'] = true;
        $ret['message'] = 'Unknown error';
    }
    return $ret;
}
function tuya_api(){
    global $db;
    global $settings;
    $ret = ['error' 	=> false,
            'message' 	=> 'Saved'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        // Get and trim all input
        $tuya_access_id = trim($_POST['tuya_access_id'] ?? '');
        $tuya_secret = trim($_POST['tuya_secret'] ?? '');

        // Prep for database insertion
        $tuya_access_id = htmlspecialchars($tuya_access_id, ENT_QUOTES, 'UTF-8');
        $tuya_secret = htmlspecialchars($tuya_secret, ENT_QUOTES, 'UTF-8');
        
        // Save to database
        if (!$settings->set('tuya_access_id', $tuya_access_id)){
            $ret['error'] = true;
            $ret['message'] = 'Unable to save Access ID/Client ID';
        }
        if (!$settings->set('tuya_secret', $tuya_secret)){
            $ret['error'] = true;
            $ret['message'] = 'Unable to save your client secret';
        }

        try{
            new Smartlife($db, $settings);
            $settings->set('tuya_configured', true);
        } catch (Exception $e){
            $settings->set('tuya_configured', false);
            $ret['error'] = true;
            $ret['message'] = 'Tuya Error: ' . $e->getMessage();
        }

        
    }else{
        $ret['error'] = true;
        $ret['message'] = 'Unknown error';
    }
    return $ret;
}

function save_setting(){
    global $settings;
    $ret = ['error' 	=> false,
            'message' 	=> 'Saved'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Get and trim all input
        $_key = trim($_POST['name'] ?? '');
        $_value = trim($_POST['value'] ?? '');

        // Prep for database insertion
        $_key = htmlspecialchars($_key, ENT_QUOTES, 'UTF-8');
        $_value = htmlspecialchars($_value, ENT_QUOTES, 'UTF-8');

        // convert to bool
        $_value = $_value === 'true' || $_value === 'false' ? strtolower($_value) === 'true' : $_value;

        $settings->set($_key, $_value);
    }else{
        $ret['error'] = true;
        $ret['message'] = 'Unknown error';
    }
    return $ret;
}
function account(){
    global $auth;
    $ret = ['error' 	=> false,
            'message' 	=> 'Account form error'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Get and trim all input
        $_username = trim($_POST['username'] ?? '');
        $_password = trim($_POST['password'] ?? '');
        $submit_type = $_POST['submit_type'] ?? '';

        // Prep for database insertion
        $_username = htmlspecialchars($_username, ENT_QUOTES, 'UTF-8');

        if($submit_type === 'create_account'){
            $ret['message'] = 'Account created';
            if (!$auth->addNewUser($_username,$_password)){
                if ($auth->userExists($_username)){
                    $ret['error'] = true;
                    $ret['message'] = 'Account not created, username already exists';
                }else{
                    $ret['error'] = true;
                    $ret['message'] = 'Account not created, unknown error';
                }
            }
        }
        if($submit_type === 'delete_account'){
            $ret['message'] = 'Account Deleted';
            if (!$auth->deleteUser($_username,$_password)){
                if ($auth->userExists($_username)){
                    $ret['error'] = true;
                    $ret['message'] = 'Account not deleted, username and password do not match';
                }else{
                    $ret['error'] = true;
                    $ret['message'] = 'Account not deleted, unknown error';
                }
            }
        }
    }else{
        $ret['error'] = true;
        $ret['message'] = 'Unknown error';
    }
    return $ret;
}
