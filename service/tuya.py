import time
import hashlib, hmac
from datetime import datetime, timezone, timedelta  
import requests
from requests.auth import HTTPBasicAuth
import re
import random
from requests.exceptions import ProxyError, ConnectionError
import json
from urllib.parse import urlencode, urljoin, parse_qs
from config import Config
from settings import Settings
from db_handler import DBHandler
import tools

class Tuya:
    restAPI = 'https://openapi.tuyaeu.com'
    apiId = None
    apiSecret = None
    accountUid = None
    apiToken = None
    apiUid = None
    token_expires_at = None
    tmp = 100
    _db = None # Database Handler Objects for interacting

    def __init__(self, db: DBHandler):
        self._db = db

        self.apiId = Settings.get('tuya_access_id', '')
        self.apiSecret = Settings.get('tuya_secret', '')
        self.accountUid = Settings.get('tuya_account_uid', '')

        self.getToken()

        if self.apiToken == '':
            raise Exception('Error getting token')

    def addApiCall(self):
        Settings.set(f'tuya_api_calls_{datetime.now().strftime('%y%m')}', int(Settings.get(f'tuya_api_calls_{datetime.now().strftime('%y%m')}', 0)) + 1)

    def fetchFromApi(self, api_endpoint: str, _params: dict[str, str] = {}, _data: dict[str, str] = {}):
        self.getToken()
        
        if self.apiToken == '':
            raise Exception('No API token')
                
        # Check for URL parameters
        if _params:
            query_params = urlencode(_params)
            api_endpoint += '?' + query_params

        base_url = self.restAPI + api_endpoint

        try:

            timestamp = int(round(time.time() * 1000))
            nonce     = self.generateUUID()
        
            headers = {
                'client_id': self.apiId,
                'access_token': self.apiToken,
                'sign_method': 'HMAC-SHA256',
                'Content-Type': 'application/json',
                'nonce': nonce,
                't': str(timestamp)
            }
        
            if _data:
                json_data = json.dumps(_data) if isinstance(_data, dict) else _data
                sign = self.generate_post_signature(timestamp, nonce, self.apiToken, api_endpoint, json_data)
                headers['sign'] = sign
                response = requests.post(base_url, headers=headers, data=json_data)
            else:
                sign = self.generate_signature(timestamp, nonce, self.apiToken, api_endpoint)
                headers['sign'] = sign
                response = requests.get(base_url, headers=headers)
            
            self.addApiCall()
            
            response_json = response.json()

            if response.status_code != 200:
                raise Exception("API request failed with HTTP code: " + str(response.status_code))

        except requests.RequestException as e:
            raise RuntimeError(f'API request failed: {str(e)}')

        return response_json

    def getToken(self):

        if self.apiToken and self.apiUid and self.token_expires_at and datetime.now() < self.token_expires_at:
            print('got cached token', flush=True)
            return

        api_endpoint = '/v1.0/token?grant_type=1'
        base_url = self.restAPI + api_endpoint

        timestamp = int(round(time.time() * 1000))
        nonce     = self.generateUUID()
        
        headers = {
            'client_id': self.apiId,
            'sign_method': 'HMAC-SHA256',
            'Content-Type': 'application/json',
            'sign_method': 'HMAC-SHA256',
            'nonce': nonce,
            't': str(timestamp)
        }
        
        try:

            sign = self.generate_signature(timestamp, nonce, '', api_endpoint)
            headers['sign'] = sign
                
            response = requests.get(base_url, headers=headers)
            
            self.addApiCall()
            
            response_json = response.json()
        
            self.apiToken = response_json['result']['access_token'];
            self.apiUid = response_json['result']['uid'];
            print(response_json['result']['expire_time'], flush=True)
            self.tmp = int(response_json['result']['expire_time']) + 30
            self.token_expires_at = datetime.now() + timedelta(seconds=(int(response_json['result']['expire_time']) - 30)) # take a little off in case

            if response.status_code != 200:
                raise Exception("API request failed with HTTP code: " + str(response.status_code))

        except requests.RequestException as e:
            raise RuntimeError(f'API request failed: {str(e)}')

    def generateUUID(self):
        return '{:04x}{:04x}-{:04x}-{:04x}-{:04x}-{:04x}{:04x}{:04x}'.format(
            random.randint(0, 0xffff), random.randint(0, 0xffff),
            random.randint(0, 0xffff),
            random.randint(0, 0x0fff) | 0x4000,
            random.randint(0, 0x3fff) | 0x8000,
            random.randint(0, 0xffff),
            random.randint(0, 0xffff),
            random.randint(0, 0xffff)
        )
    
    def generate_signature(self, timestamp: str, nonce: str, access_token: str = '', url_path: str = ''):
        # Genarate signature for GET requests

        content_sha256 = hashlib.sha256(b'').hexdigest()
        string_to_sign = f"GET\n{content_sha256}\n\n{url_path}"
        string_to_hash = f"{self.apiId}{access_token}{timestamp}{nonce}{string_to_sign}"
        signature = hmac.new(self.apiSecret.encode('utf-8'), string_to_hash.encode('utf-8'), hashlib.sha256).hexdigest().upper()
        
        return signature

    def generate_post_signature(self, timestamp: str, nonce: str, access_token: str, url_path: str, body_data: str):
        #Generate signature for POST requests

        content_sha256 = hashlib.sha256(body_data.encode('utf-8')).hexdigest()
        string_to_sign = f"POST\n{content_sha256}\n\n{url_path}"
        string_to_hash = f"{self.apiId}{access_token}{timestamp}{nonce}{string_to_sign}"
        signature = hmac.new(self.apiSecret.encode('utf-8'), string_to_hash.encode('utf-8'), hashlib.sha256).hexdigest().upper()
        
        return signature

    def getDeviceList(self):
        
        deviceList = self.fetchFromApi(f"/v1.0/users/{self.accountUid}/devices", {'page_size': Config.get('TUYA_PAGE_SIZE')}, {})

        if not deviceList['success']:
            if deviceList['msg'] == 'permission deny':
                raise Exception('Error: check your UID in settings')
            else:
                raise Exception('Error Getting List')

        # init device list
        devices = {}
        for device in deviceList['result']:
            # scan for devices we want
            if device['product_name'] in Config.TUYA_SUPPORTED_PRODUCTS:
                # get the relevant attributes 
                devices = {
                    'id': device['id'],
                    'name': device['name'],
                    'local_key': device['local_key'],
                    'model': device['model'],
                    'product_name': device['product_name'],
                    'icon': f'https://images.tuyaeu.com/{device['icon']}',
                    'online': device['online'],
                    'status': device['status'],
                    'uid': device['uid'],
                    'uuid': device['uuid'],
                    'update_time': device['update_time']
                }

        return devices

    def getDeviceDetails(self, device_id: str):
        
        deviceList = self.fetchFromApi(f"/v1.0/devices/{device_id}", {}, {})
        if not deviceList['success']:
            raise Exception('Error Getting Device Details')

        # init device list
        devices = {}
        if deviceList['result']:
            device = deviceList['result']
            devices = {
                'id': device['id'],
                'name': device['name'],
                'local_key': device['local_key'],
                'model': device['model'],
                'product_name': device['product_name'],
                'icon': f'https://images.tuyaeu.com/{device['icon']}',
                'online': device['online'],
                'status': device['status'],
                'uid': device['uid'],
                'uuid': device['uuid'],
                'update_time': device['update_time']
            }

        return devices

    def setSwitchState(self, deviceId: str, switchState: bool):

        commands = {
            'commands': [
                {
                    'code': 'switch_1',
                    'value': switchState  # true = on, false = off
                }
            ]
        }

        switched = self.fetchFromApi(f"/v1.0/devices/{deviceId}/commands", None, commands)

        if switched.get('success', False):
            return True
        
        error_msg = switched.get('msg', 'Unknown error occurred')
        raise Exception(error_msg)

    def getCurrentPower(self, device, noSuffix: bool = False):
        w = ''
        if not device['online']:
            return 'Offline'

        if not device['online']:
            return 'Offline'

        for status in device['status']:
            if status['code'] == 'cur_power':
                w = (status['value'] / 10) if device['status'][0]['value'] else 0

        if noSuffix:
            return w

        if isinstance(w, (int, float)):
            if w > 1000:
                w = f'{round((w/1000), 3)} kWh'
            else:
                w = f'{w} W'

        return w
    
    def getDeviceVoltage(self, device: str, noSuffix: bool = False):
        w = ''
        if not device['online']:
            return 'Offline'

        if not device['online']:
            return 'Offline'

        for status in device['status']:
            if status['code'] == 'cur_voltage':
                w = (status['value'] / 10) if device['status'][0]['value'] else 0

        if noSuffix:
            return w

        return f'{w} V';

    def getDeviceLogs(self, device_id: str, start_time: int = None, end_time: int = None):

        start_time = int((time.time() - 604800) * 1000) if not start_time else start_time # 604800 7 Days free tier limit
        end_time   = int(time.time() * 1000) if not end_time else end_time
        
        devices = {
                    'id': device_id,
                    'current_row_key': '',
                    'next_row_key': '',
                    'has_next': True,
                    'logs': {},
                    't': None,
                    'success': False,
                }
        has_next = True
        start_row_key = ''
        logs = []

        while devices['has_next']:
            print(devices, flush=True)
            deviceLogs = self.fetchFromApi(f"/v1.0/devices/{device_id}/logs", {'codes': 'add_ele', 'end_time': end_time, 'start_row_key': devices['next_row_key'], 'start_time': start_time, 'type': 7}, {})
            print(deviceLogs, flush=True)
            if deviceLogs['success']:
                if deviceLogs['result']:
                    device = deviceLogs['result']
                    devices = {
                        'id': device['device_id'],
                        'current_row_key': device['current_row_key'] if 'current_row_key' in device else '',
                        'next_row_key': device['next_row_key'] if 'next_row_key' in device else '',
                        'has_next': device['has_next'],
                        'logs': {},
                        't': deviceLogs['t'],
                        'success': deviceLogs['success'],
                    }
                    logs.extend(device['logs'])
                else:
                    devices['has_next'] = False
            else:
                devices['has_next'] = False

        devices['logs'] = logs

        return devices

