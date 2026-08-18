from time import timezone
from datetime import datetime, timezone, timedelta  
import requests
from requests.auth import HTTPBasicAuth
import re
from requests.exceptions import ProxyError, ConnectionError
import json
from urllib.parse import urlencode, urljoin, parse_qs
from config import Config
from settings import Settings
from db_handler import DBHandler
import tools

class Octopus:
    restAPI = 'https://api.octopus.energy/v1'
    graphQL = 'https://api.octopus.energy/v1/graphql/'
    api_key = None
    account_number = None
    graphToken = None
    _db = None # Database Handler Objects for interacting

    def __init__(self, db: DBHandler):
        self._db = db
        self.api_key = Settings.api_key
        self.account_number = Settings.account_number

    def fetchFromApi(self, api_endpoint, _params: dict[str, str] = {}, _data: dict[str, str] = {}):

        base_url = self.restAPI + api_endpoint

        # Check for URL parameters
        if _params:
            query_params = urlencode(_params)
            base_url += '?' + query_params

        response = requests.get(base_url, auth = HTTPBasicAuth(self.api_key, ''), params = _params, data = _data)

        try:
            response.raise_for_status()
        except requests.RequestException as e:
            raise Exception('API request failed: ' + str(e))

        response_json = response.json()

        if response.status_code != 200:
            raise Exception("API request failed with HTTP code: " + str(response.status_code))

        return response_json

    def getGraphToken(self):
        _query = {
            "query": 'mutation { obtainKrakenToken(input: {APIKey: "' + self.api_key + '"}) { token } }'
        }

        response = requests.post(self.graphQL, json = _query)
        data = response.json()
        self.graphToken = data['data']['obtainKrakenToken']['token']

    def queryGraphQL(self, _query: dict[str, str]):

        if not self.graphToken:
            self.getGraphToken()

        _headers = {
            'Content-Type': 'application/json',
            'Authorization': f'JWT {self.graphToken}'
        }

        response = requests.post(self.graphQL, json = _query, headers = _headers)

        if response.status_code != 200:
            raise Exception(f"API request failed with HTTP code: {response.status_code}")

        data = response.json()
        return data

    def getCurrentTariffFromAccount(self, _account_number: str):
        try:
            # Get the account details
            account_data = self.fetchFromApi(f'/accounts/{_account_number}/')
            if not account_data:
                raise Exception('Failed to fetch account data')

            current_agreement = self.getCurrentElectricityAgreement(account_data)
            if current_agreement is None:
                raise Exception('No current electricity agreement found')

            # Get the current tariff and product code
            tariff_code = current_agreement['tariff_code'];
            match = re.search(r'-([A-Z]+-\d{2}-\d{2}-\d{2})-', tariff_code, re.IGNORECASE)
            if match:
                product_code = match.group(1)  # AGILE-24-10-01
            else:
                # Pattern 2: Match standard tariff pattern like E-1R-OE-VAR-24-12-14-M
                match = re.search(r'^E-1R-(.+)-M$', tariff_code)
                if match:
                    product_code = match.group(1)  # OE-VAR-24-12-14

            electricity_tariffs = {
                'electricity_product_code': product_code or '',
                'electricity_tariff_code': tariff_code
            }
            
            # Get the electricity meters for the account
            electricity_meters = self.getElectricityMeterPointData(account_data)

            # Return the tariff and product code and Meters
            return {**electricity_tariffs, **electricity_meters}

        except requests.exceptions.RequestException as e:
            # Re-throw exception
            raise

    def getCurrentElectricityAgreement(self, account_data: dict) -> dict:
        if not account_data['properties'][0]['electricity_meter_points'][0]['agreements']:
            return None

        agreements = account_data['properties'][0]['electricity_meter_points'][0]['agreements']
        current_time = datetime.now(timezone.utc)

        for agreement in agreements:
            valid_from = datetime.fromisoformat(agreement['valid_from'].replace('Z', '+00:00'))
            valid_to = datetime.fromisoformat(agreement['valid_to'].replace('Z', '+00:00')) if agreement.get('valid_to') else None

            if (current_time >= valid_from and (valid_to is None or current_time < valid_to)):
                return agreement

        return None

    def getElectricityMeterPointData(self, account_data: dict) -> dict:
        # Validate the account data structure
        if not account_data['properties'][0]['electricity_meter_points']:
            raise ValueError('No electricity meter points found in account data')

        meter_points = {}
        
        for meterPoint in account_data['properties'][0]['electricity_meter_points']:
            # Validate MPAN (Meter Point Administration Number)
            if not meterPoint['mpan']:
                raise ValueError('MPAN not found')

            # Validate meters array
            if not meterPoint['meters']:
                raise ValueError('No meters found for MPAN: {}'.format(meterPoint['mpan']))

            meters = []
            for meter in meterPoint['meters']:
                if meter['serial_number']:
                    meters.append({
                        'serial_number': meter['serial_number'],
                        'is_export': meter.get('is_export', False),
                        'is_smart': meter.get('is_smart', False)
                    });

            if not meters:
                raise ValueError('No valid meters found for MPAN: {}'.format(meterPoint['mpan']))

            if 'supply' not in meter_points:
                meter_points['supply'] = []
            meter_points['supply'].append({
                'mpan': meterPoint['mpan'],
                'profile_class': meterPoint.get('profile_class'),
                'meters': meters,
                'agreements': meterPoint.get('agreements', [])
            })

        if 'supply' not in meter_points:
            raise ValueError('No valid meter points found')

        return meter_points
    
    def getTariffData(self, product_code: str, tariff_code: str, validFrom: str = '', validTo: str = '') -> dict:
        if not product_code:
            return []

        # Declare our data var
        tariff_data = {}

        # Convert the datetime string to the ISO 8601 format
        validFrom = tools.parse_datetime(validFrom, Config.get('app.timezone', 'UTC'))
        valid_from = validFrom.isoformat()
        validTo = tools.parse_datetime(validTo, Config.get('app.timezone', 'UTC'))
        valid_to = tvalidTo.isoformat()

        # Check if we are using database and try to retrieve the results
        if Settings.save_tariff_data:
            # Calculate the number of intervals needed (Number of half hour slots)
            # + 1 to account for the last full
            diff = validTo - validFrom
            half_hours = int(diff.total_seconds() // 1800) + 1
            tariff_data = self._db.getTariffData(product_code, tariff_code, valid_from, valid_to, half_hours)

        # if no data get the data from the API
        if tariff_data == {}:
            tariff_data = self.fetchFromApi(f"/products/{product_code}/electricity-tariffs/{tariff_code}/standard-unit-rates", {'period_from': valid_from,  'period_to': valid_to});
            tariff_data['results'].reverse() # Reverse the data so it goes from oldest to newest
            if Settings.save_tariff_data:
                self._db.saveTariffData(product_code, tariff_code, tariff_data['results'])
            
        results = []
        average_price = 0

        # Revers the array so it goes from oldest to newest
        for item in tariff_data['results']:
            results.append({
                'price_inc_vat': item['value_inc_vat'],
                'valid_from': tools.parse_datetime(tools.parse_datetime(item['valid_from']), Config.get('app.timezone', 'UTC')).isoformat(),
                'valid_to': tools.parse_datetime(tools.parse_datetime(item['valid_to']), Config.get('app.timezone', 'UTC')).isoformat()
            })
            average_price += int(item['value_inc_vat'])

        # Add the average cost to the results
        return {'tariff': results, 'average_price_inc_vat': round(average_price / len(tariff_data['results']), 4)}

    def getConsumptionData(self, meter_MPAN: str, meter_serial: str, intervalStart: str = '', intervalEnd: str = '') -> dict:

        # Declare our data var
        consumptionData = {};

        # Convert the datetime string to the ISO 8601 format
        intervalStart = tools.parse_datetime(intervalStart, Config.get('app.timezone', 'UTC'))
        interval_start = intervalStart.isoformat()
        intervalEnd = tools.parse_datetime(intervalEnd, Config.get('app.timezone', 'UTC'))
        interval_end = intervalEnd.isoformat()

        # Check if we are using database and try to retrieve the results
        if Settings.save_consumption_data:
            # Calculate the number of intervals needed (Number of half hour slots)
            # + 1 to account for the last full
            diff = intervalEnd - intervalStart
            half_hours = int(diff.total_seconds() // 1800) + 1
            consumption_data = self._db.getConsumptionData(meter_MPAN, meter_serial, interval_start, interval_end, half_hours)

        # if no data get the data from the API
        if consumption_data == {}:
            consumption_data = self.fetchFromApi(F"/electricity-meter-points/{meter_MPAN}/meters/{meter_serial}/consumption/",{'period_from': interval_start,  'period_to': interval_end})
            consumption_data['results'].reverse() # Reverse the data so it goes from oldest to newest
            if Settings.save_consumption_data:
                self._db.saveConsumptionData(meter_MPAN, meter_serial, consumption_data['results']);

        results = []
        average_consumption = 0
        total_consumption = 0

        # Revers the array so it goes from oldest to newest
        for item in consumption_data['results']:
            if tools.parse_datetime(tools.parse_datetime(item['interval_start']), Config.get('app.timezone', 'UTC')) < intervalEnd:
                results.append({
                    'consumption': item['consumption'],
                    'valid_from': tools.parse_datetime(tools.parse_datetime(item['interval_start']), Config.get('app.timezone', 'UTC')).isoformat(),
                    'valid_to': tools.parse_datetime(tools.parse_datetime(item['interval_end']), Config.get('app.timezone', 'UTC')).isoformat()
                })
                average_consumption += int(item['consumption'])
                total_consumption += int(item['consumption'])

        # Add the average cost to the results
        return {'electricity': results, 
                'electricity_total_consumption':  round(total_consumption, 3), 
                'electricity_average_consumption': round(average_consumption / len(consumption_data['results']), 4) if len(consumption_data['results']) else 0
                }

    def getStandardTariff(self, current_tariff_code: str, intervalStart: str = '') -> dict:
        # Declare our data var
        standard_tariffs = {};

        # Convert the datetime string to the ISO 8601 format
        intervalStart = tools.parse_datetime(intervalStart, Config.get('app.timezone', 'UTC'))
        interval_start = intervalStart.isoformat()

        # Get the DNO from the current tariff
        area_code = current_tariff_code[len(current_tariff_code) - 1];
        product_code = 'VAR-22-11-01';
        tariff_code = f"E-1R-VAR-22-11-01-{area_code}";

        # Check if we are using database and try to retrieve the results
        if Settings.save_standard_tariff_data:
            standard_tariffs = self._db.getStandardTariffData(product_code, tariff_code, interval_start);

        # if no data get the data from the API
        if standard_tariffs == [] or standard_tariffs['results'][0]['valid_to'] if standard_tariffs else None == None:
            standard_tariffs = self.fetchFromApi(f"/products/{product_code}/electricity-tariffs/{tariff_code}/standard-unit-rates/", {'period_from': interval_start});
            standard_tariffs['results'].reverse() # Reverse the data so it goes from oldest to newest
            if Settings.save_standard_tariff_data:
                self._db.saveStandardTariffData(product_code, tariff_code, standard_tariffs['results']);
            
        results = []

        for item in standard_tariffs['results']:
            tariffValidFrom = tools.parse_datetime(item['valid_from'], 'UTC')
            tariffValidTo = tools.parse_datetime(item['valid_to'] if item['valid_to'] else intervalStart, 'UTC')
            if item['payment_method'] == 'DIRECT_DEBIT' and \
                    tariffValidFrom <= intervalStart and \
                    tariffValidTo >= intervalStart:
                results.append({
                    'valid_from': tools.convert_timezone(tariffValidFrom, Config.get('app.timezone', 'UTC'), 'ISO'),
                    'value_inc_vat': item['value_inc_vat']
                })


        # Add the average cost to the results
        return {'electricity_standard_tariff': results};

    def getDeviceID(self):
        query = {
            'query': f'''
                query {{
                    account(accountNumber: "{Settings.account_number}") {{
                        electricityAgreements(active: true) {{
                            meterPoint {{
                                meters(includeInactive: false) {{
                                    smartDevices {{
                                        deviceId
                                    }}
                                }}
                            }}
                        }}
                    }}
                }}
            '''
        }
        
        data = self.queryGraphQL(query);
        return data['data']['account']['electricityAgreements'][0]['meterPoint']['meters'][0]['smartDevices'][0]['deviceId'];
    
    def getHomeTelemetry(self, device_id):
        now = datetime.now(timezone.utc)
        start = (now - timedelta(minutes=1)).isoformat()
        end = now.isoformat()

        query = {
            'query': f'''
                query {{
                    smartMeterTelemetry(
                        deviceId: "{device_id}"
                        grouping: TEN_SECONDS
                        start: "{start}"
                        end: "{end}"
                    ) {{
                        readAt
                        consumptionDelta
                        demand
                        consumption
                    }}
                }}
            '''
        }
        
        data = self.queryGraphQL(query);
        return data['data']['smartMeterTelemetry'];
