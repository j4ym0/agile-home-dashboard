<?php

class Smartlife{
    private $restAPI = 'https://openapi.tuyaeu.com'; // West Europe endpoint
    private $settings;
    private $db;
    private $apiId = '';
    private $apiSecret = '';
    private $apiToken = '';


    public function __construct($database, $settings) {
        $this->db = $database;
        $this->settings = $settings;

        // Load settings to use in 
        $this->apiId = $settings->get('tuya_access_id', '');
        $this->apiSecret = $settings->get('tuya_secret', '');

        $this->getToken();

        if ($this->apiToken == ''){
            throw new RuntimeException('Error getting token');
        }
    }

    /**
     * Calls the Octopus Energy REST API
     * returns decoded json data
     * @throws RuntimeException
     */
    private function fetchFromApi($apiEndpoint, $prams = [], $data = []){
        // Initialize cURL handle that we'll reuse
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }
        try {
            $Url = $this->restAPI . rtrim($apiEndpoint, '/')  . '/';

            // Check for URL parameters
            if (!empty($prams)) {
                $Url .= '?' . http_build_query($prams);
            }

            $options = [
                CURLOPT_URL => $Url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $this->apiKey,
                CURLOPT_FAILONERROR => false, // We'll handle errors manually
            ];

            // Add POST data if exists
            if (!empty($data)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
                
                // If you need to send as form data instead of JSON:
                // $options[CURLOPT_POSTFIELDS] = http_build_query($data);
                // And change Content-Type to:
                // 'Content-Type: application/x-www-form-urlencoded'
            }

            curl_setopt_array($ch, $options);

            $Response = curl_exec($ch);
            if ($Response === false) {
                throw new RuntimeException('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new RuntimeException("API request failed with HTTP code: $httpCode");
            }

            $Data = json_decode($Response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Failed to decode JSON response');
            }

            // Return the decoded json data
            return $Data;

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
            throw new Exception($data['msg'] . ' (Code: ' . $data['code'] . ')');
        }
        
        $this->apiToken = $data['result']['access_token'];
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



}