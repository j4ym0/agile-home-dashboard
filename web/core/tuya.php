<?php

class Tuya{
    private $restAPI = 'https://openapi.tuyaeu.com'; // West Europe endpoint
    private $settings;
    private $db;
    private $apiId = '';
    private $apiSecret = '';
    private $apiToken = '';
    private $apiUid = '';
    private $accountUid = '';


    public function __construct($database, $settings) {
        $this->db = $database;
        $this->settings = $settings;

        // Load settings to use in 
        $this->apiId = $settings->get('tuya_access_id', '');
        $this->apiSecret = $settings->get('tuya_secret', '');
        $this->accountUid = $settings->get('tuya_account_uid', '');

        $this->getToken();

        if ($this->apiToken == ''){
            throw new RuntimeException('Error getting token');
        }
    }

    /**
     * Calls the Tuya REST API
     * returns decoded json data
     * @throws RuntimeException
     */
    private function fetchFromApi($apiEndpoint, $params = [], $data = []){
        if ($this->apiToken == ''){
            throw new RuntimeException('No token');
        }
        
        // Initialize cURL handle that we'll reuse
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }
        try {
            // add url params to api endpoint
            if (!empty($params)) {
                $apiEndpoint .= '?' . http_build_query($params);
            }
            $timestamp = round(microtime(true) * 1000);
            $nonce     = $this->generateUUID();
            $sign = $this->generateSignature($timestamp, $nonce, $this->apiToken, $apiEndpoint);
        
            $headers = [
                'client_id: ' . $this->apiId,
                'access_token: ' . $this->apiToken,
                'sign: ' . $sign,
                'nonce: ' . $nonce,
                't: ' . $timestamp,
                "sign_method: HMAC-SHA256",
                "Content-Type: application/json"
            ];
        
            $options = [
                CURLOPT_URL =>  $this->restAPI . $apiEndpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
            ];

            // Add POST data if exists
            if (!empty($data)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
            }

            curl_setopt_array($ch, $options);
        
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('HTTP: ' . $httpCode);
            }

            return json_decode($response, true);

        } finally {
            curl_close($ch); // Ensure cURL handle is always closed
        }
    }
    private function getToken(){
        $this->apiToken = '';

        $timestamp = round(microtime(true) * 1000);
        $url ='/v1.0/token?grant_type=1';
        $nonce     = $this->generateUUID();
        $sign = $this->generateSignature($timestamp, $nonce, '', $url);
        
        $headers = [
            'client_id: ' . $this->apiId,
            'sign: ' . $sign,
            'nonce: ' . $nonce,
            't: ' . $timestamp,
            "sign_method: HMAC-SHA256",
            "Content-Type: application/json"
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL =>  $this->restAPI . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('HTTP: ' . $httpCode);
        }

        $data = json_decode($response, true);

        if (!$data['success']) {
            throw new Exception('(Code: ' . $data['code'] . ') Check ID and Secret');
        }
        
        $this->apiToken = $data['result']['access_token'];
        $this->apiUid = $data['result']['uid'];
    }
    private function generateUUID(){
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    private function generateSignature($timestamp, $nonce, $accessToken = '', $urlPath = ''){
        // empty body string
        $stringToSign = "GET\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\n\n" . $urlPath;
        $stringToHash = $this->apiId . $accessToken . $timestamp . $nonce . $stringToSign;
        return strtoupper(hash_hmac('sha256', $stringToHash, $this->apiSecret));
    }
    
    private function generatePostSignature($timestamp, $nonce, $accessToken, $urlPath, $bodyData){
        $content_sha256 = hash('sha256', $bodyData);
        $stringToSign   = "POST\n" . $content_sha256 . "\n\n" . $urlPath;
        $stringToHash   = $this->apiId . $accessToken . $timestamp . $nonce . $stringToSign;
        return strtoupper(hash_hmac('sha256', $stringToHash, $this->apiSecret));
    }

    public function getDeviceList() {

        $deviceList = $this->fetchFromApi("/v1.0/users/$this->accountUid/devices", ['page_size' => Config::get('TUYA_PAGE_SIZE')],'');
        if (!$deviceList['success']){
            if ($deviceList['msg'] == 'permission deny'){
                throw new Exception('Error: check your UID in settings'); 
            }else{
                throw new Exception('Error Getting List');
            }
        }

        // init device list
        $devices = [];
        foreach($deviceList['result'] as $device){
            // scan for devices we want
            if (in_array($device['product_name'], Config::get('TUYA_SUPPORTED_PRODUCTS'))){
                // get the relevant attributes 
                $devices[] = [
                    'id' => $device['id'],
                    'name' => $device['name'],
                    'local_key' => $device['local_key'],
                    'model' => $device['model'],
                    'product_name' => $device['product_name'],
                    'icon' => 'https://images.tuyaeu.com/' . $device['icon'],
                    'online' => $device['online'],
                    'status' => $device['status'],
                    'uid' => $device['uid'],
                    'uuid' => $device['uuid'],
                    'update_time' => $device['update_time']
                ];
            }
        }

        return $devices;
    }

}