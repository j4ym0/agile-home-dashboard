<?php

class Octopus{
    private $restAPI = 'https://api.octopus.energy/v1';
    private $graphQL = 'https://api.octopus.energy/v1/graphql/';
    private $settings;
    private $db;
    private $save_tariff_data = false;
    private $save_consumption_data = false;
    private $save_standard_tariffs = false;
    private $apiKey = '';
    private $graphToken = '';
    private $accountNumber = '';

    public function __construct($database, $settings) {
        $this->db = $database;
        $this->settings = $settings;

        // Load settings to use in 
        $this->apiKey = $settings->get('api_key', '');
        $this->accountNumber = $settings->get('account_number', '');
        $this->save_tariff_data = $settings->get('save_tariff_data', '') == true;
        $this->save_consumption_data = $settings->get('save_consumption_data', '') == true;
        $this->save_standard_tariffs = $settings->get('save_standard_tariff_data', '') == true;
    }

    /**
     * Calls the Octopus Energy REST API
     * returns decoded json data
     * @throws RuntimeException
     */
    private function fetchFromApi($apiEndpoint, $params = [], $data = []){
        // Initialize cURL handle that we'll reuse
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }
        try {
            $Url = $this->restAPI . rtrim($apiEndpoint, '/')  . '/';

            // Check for URL parameters
            if (!empty($prams)) {
                $Url .= '?' . http_build_query($params);
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
    private function queryGraphQL($query){
        // Initialize cURL handle that we'll reuse
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }
        try {

            if (empty($this->graphToken)){
                $this->getGraphToken();
            }

            // Make sure we have a query
            if (!empty($query) && !empty($this->graphToken)) {

                curl_setopt($ch, CURLOPT_URL, $this->graphQL);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: JWT ' . $this->graphToken
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
            }
        } finally {
            curl_close($ch); // Ensure cURL handle is always closed
        }
    }
    private function getGraphToken(){
        $query = [
            "query" => 'mutation { obtainKrakenToken(input: {APIKey: "' . $this->apiKey . '"}) { token } }'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->graphQL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $this->graphToken = $data['data']['obtainKrakenToken']['token'];
    }

    public function getCurrentTariffFromAccount(): ?array {
        try {
            // Get the account details
            $accountData = $this->fetchFromApi('/accounts/' . urlencode($this->accountNumber) . '/');

            // Get the current agreement
            $currentAgreement = $this->getCurrentElectricityAgreement($accountData);
            if ($currentAgreement === null) {
                throw new RuntimeException('No current electricity agreement found');
            }

            // Get the current tariff and product code
            $tariffCode = $currentAgreement['tariff_code'];
            if (preg_match('/-([A-Z]+-\d{2}-\d{2}-\d{2})-/i', $tariffCode, $matches)) {
                $productCode = $matches[1]; // AGILE-24-10-01
            }elseif (preg_match('/^E-1R-(.+)-M$/', $tariffCode, $matches)) {
                // For standard tariff
                $productCode = $matches[1]; // OE-VAR-24-12-14
            }
            $electricityTariffs = [
                    'electricity_product_code' => $productCode ?? '',
                    'electricity_tariff_code' => $tariffCode
            ];
            
            // Get the electricity meters for the account
            $electricityMeters = $this->getElectricityMeterPointData($accountData);

            // Return the tariff and product code and Meters
            return array_merge($electricityTariffs, $electricityMeters);
        } catch (RuntimeException $e) {
            // Re-throw exception
            throw $e; 
        }
    }

    private function getCurrentElectricityAgreement(array $accountData): ?array {
        if (empty($accountData['properties'][0]['electricity_meter_points'][0]['agreements'])) {
            return null;
        }

        $agreements = $accountData['properties'][0]['electricity_meter_points'][0]['agreements'];
        $currentTime = new DateTime('now', new DateTimeZone('UTC'));

        foreach ($agreements as $agreement) {
            $validFrom = new DateTime($agreement['valid_from']);
            $validTo = isset($agreement['valid_to']) ? new DateTime($agreement['valid_to']) : null;
            
            if ($currentTime >= $validFrom && ($validTo === null || $currentTime < $validTo)) {
                return $agreement;
            }
        }

        return null;
    }

    public function getElectricityMeterPointData(array $accountData): array{
        // Validate the account data structure
        if (empty($accountData['properties'][0]['electricity_meter_points'])) {
            throw new RuntimeException('No electricity meter points found in account data');
        }

        $meterPoints = [];
        
        foreach ($accountData['properties'][0]['electricity_meter_points'] as $meterPoint) {
            // Validate MPAN (Meter Point Administration Number)
            if (empty($meterPoint['mpan'])) {
                throw new RuntimeException('MPAN No found');
            }

            // Validate meters array
            if (empty($meterPoint['meters'])) {
                throw new RuntimeException('No meters found for MPAN: ' . $meterPoint['mpan']);
            }

            $meters = [];
            foreach ($meterPoint['meters'] as $meter) {
                if (!empty($meter['serial_number'])) {
                    $meters[] = [
                        'serial_number' => $meter['serial_number'],
                        'is_export' => $meter['is_export'] ?? false,
                        'is_smart' => $meter['is_smart'] ?? false,
                    ];
                }
            }

            if (empty($meters)) {
                throw new RuntimeException('No valid meters found for MPAN: ' . $meterPoint['mpan']);
            }

            $meterPoints['supply'][] = [
                'mpan' => $meterPoint['mpan'],
                'profile_class' => $meterPoint['profile_class'] ?? null,
                'meters' => $meters,
                'agreements' => $meterPoint['agreements'] ?? []
            ];
        }

        if (empty($meterPoints)) {
            throw new RuntimeException('No valid meter points found');
        }

        return $meterPoints;
    }

    public function getTariffData(string $productCode, string $tariffCode, string $validFrom = '', string $validTo = ''): array{
        if (!$productCode) {
            return [];
        }

        // Declare our data var
        $tariffData = [];

        // Convert the datetime string to the ISO 8601 format
        $validFrom = new DateTime($validFrom, new DateTimeZone('Europe/London'));
        $valid_from = $validFrom->setTimezone(new DateTimeZone('UTC'))->format('c');;
        $validTo = new DateTime($validTo, new DateTimeZone('Europe/London'));
        $valid_to = $validTo->setTimezone(new DateTimeZone('UTC'))->format('c');;

        // Check if we are using database and try to retrieve the results
        if($this->save_tariff_data){
            // Calculate the number of intervals needed (Number of half hour slots)
            // + 1 to account for the last full
            $halfHours = (int)($validFrom->diff($validTo)->i / 30 + $validFrom->diff($validTo)->h * 2 + $validFrom->diff($validTo)->days * 48) + 1;
            $tariffData = $this->db->getTariffData($productCode, $tariffCode, $valid_from, $valid_to, $halfHours);
        }

        // if no data get the data from the API
        if ($tariffData === []){
            $tariffData = $this->fetchFromApi("/products/{$productCode}/electricity-tariffs/{$tariffCode}/standard-unit-rates",['period_from' => $valid_from,  'period_to' => $valid_to]);
            if($this->save_tariff_data){
                $this->db->saveTariffData($productCode, $tariffCode, $tariffData['results']);
            }
        }
        $results = [];
        $average_price = 0;

        // Revers the array so it goes from oldest to newest
        foreach (array_reverse($tariffData['results']) as $item) {
            $results[] = [
                'price_inc_vat' => $item['value_inc_vat'],
                'valid_from' => new DateTime($item['valid_from'])->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'valid_to' => new DateTime($item['valid_to'] == '' ? new DateTime('tomorrow')->format('Y-m-d H:i:s') : $item['valid_to'])->setTimezone(new DateTimeZone('UTC'))->format('c')
            ];
            $average_price += $item['value_inc_vat'];
        } 

        // Add the average cost to the results
        return ['tariff' => $results, 'average_price_inc_vat' => round($average_price / count($tariffData['results']), 4)];
    }

    public function getConsumptionData(string $meterMPAN, string $meterSerial, string $intervalStart = '', string $intervalEnd = ''): array{

        // Declare our data var
        $consumptionData = [];

        // Convert the datetime string to the ISO 8601 format
        $intervalStart = new DateTime($intervalStart, new DateTimeZone('Europe/London'));
        $interval_start = $intervalStart->setTimezone(new DateTimeZone('UTC'))->format('c');;
        $intervalEnd = new DateTime($intervalEnd, new DateTimeZone('Europe/London'));
        $interval_end = $intervalEnd->setTimezone(new DateTimeZone('UTC'))->format('c');;

        // Check if we are using database and try to retrieve the results
        if($this->save_consumption_data){
            // Calculate the number of intervals needed (Number of half hour slots)
            // + 1 to account for the last full
            $halfHours = (int)($intervalStart->diff($intervalEnd)->i / 30 + $intervalStart->diff($intervalEnd)->h * 2 + $intervalStart->diff($intervalEnd)->days * 48) + 1;
            $consumptionData = $this->db->getConsumptionData($meterMPAN, $meterSerial, $interval_start, $interval_end, $halfHours);
        }

        // if no data get the data from the API
        if ($consumptionData === []){
            $consumptionData = $this->fetchFromApi("/electricity-meter-points/{$meterMPAN}/meters/{$meterSerial}/consumption/",['period_from' => $interval_start,  'period_to' => $interval_end]);
            if($this->save_consumption_data){
                $this->db->saveConsumptionData($meterMPAN, $meterSerial, $consumptionData['results']);
            }
        }
        $results = [];
        $average_consumption = 0;
        $total_consumption = 0;

        // Revers the array so it goes from oldest to newest
        foreach (array_reverse($consumptionData['results']) as $item) {
            $results[] = [
                'consumption' => $item['consumption'],
                'interval_start' => new DateTime($item['interval_start'])->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'interval_end' => new DateTime($item['interval_end'])->setTimezone(new DateTimeZone('UTC'))->format('c')
            ];
            $average_consumption += $item['consumption'];
            $total_consumption += $item['consumption'];
        } 

        // Add the average cost to the results
        return ['electricity' => $results, 
                'electricity_total_consumption' => round($total_consumption, 3), 
                'electricity_average_consumption' => count($consumptionData['results']) ? round($average_consumption / count($consumptionData['results']), 4) : 0];
    }
    public function getStandardTariff(string $currentTariffCode, string $intervalStart = ''): array{
        // Declare our data var
        $standard_tariffs = [];

        // Convert the datetime string to the ISO 8601 format
        $intervalStart = new DateTime($intervalStart, new DateTimeZone('Europe/London'));
        $interval_start = $intervalStart->setTimezone(new DateTimeZone('UTC'))->format('c');

        // Get the DNO from the current tariff
        $area_code = substr($currentTariffCode, -1);
        $productCode = 'VAR-22-11-01';
        $tariffCode = "E-1R-VAR-22-11-01-$area_code";

        // Check if we are using database and try to retrieve the results
        if($this->save_standard_tariffs){
            $standard_tariffs = $this->db->getStandardTariffData($productCode, $tariffCode, $interval_start);
        }

        // if no data get the data from the API
        if ($standard_tariffs === []){
            // get the area code from tariff code
            $standard_tariffs = $this->fetchFromApi("/products/$productCode/electricity-tariffs/$tariffCode/standard-unit-rates/",['period_from' => $interval_start]);
            if($this->save_standard_tariffs){
                $this->db->saveStandardTariffData($productCode, $tariffCode, $standard_tariffs['results']);
            }
        }
        $results = [];

        // Revers the array so it goes from oldest to newest
        foreach (array_reverse($standard_tariffs['results']) as $item) {
            if ($item['payment_method'] === 'DIRECT_DEBIT'){
                $results[] = [
                    'valid_from' => new DateTime($item['valid_from'])->setTimezone(new DateTimeZone('UTC'))->format('c'),
                    'value_inc_vat' => $item['value_inc_vat']
                ];
            }
        } 

        // Add the average cost to the results
        return ['electricity_standard_tariff' => $results];
    }
    public function getDeviceID() {
        $query = [
            "query" => '
                query {
                    account(accountNumber: "' . $this->accountNumber . '") {
                        electricityAgreements(active: true) {
                            meterPoint {
                                meters(includeInactive: false) {
                                    smartDevices {
                                        deviceId
                                    }
                                }
                            }
                        }
                    }
                }
            '
        ];
        
        $data = $this->queryGraphQL($query);
        return $data['data']['account']['electricityAgreements'][0]['meterPoint']['meters'][0]['smartDevices'][0]['deviceId'];
    }
    public function getHomeTelemetry($device_id) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $start = (clone $now)->sub(new DateInterval('PT1M'))->format('c');
        $end = $now->format('c');
        
        $query = [
            "query" => '
                query {
                    smartMeterTelemetry(
                        deviceId: "' . $device_id . '"
                        grouping: TEN_SECONDS
                        start: "' . $start . '"
                        end: "' . $end . '"
                    ) {
                        readAt
                        consumptionDelta
                        demand
                        consumption
                    }
                }
            '
        ];
        
        $data = $this->queryGraphQL($query);

        return $data['data']['smartMeterTelemetry'];
    }
}