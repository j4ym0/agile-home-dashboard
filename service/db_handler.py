from typing import Dict, List, Any, Optional, Union
from db_sqlite import SQLiteHandler
from db_mysql import MySQLHandler
from config import Config
import tools

class DBHandler:
    
    def __init__(self):
        self.handler = None
        self.db_type = None
        self._initialize_handler()
    
    def _initialize_handler(self) -> None:

        self.db_type = Config.database.type

        # Initialize the database with the correct handler based on config settings (e.g.,"""
        if self.db_type == 'mysql':
            self.handler = MySQLHandler()
        else:
            self.db_type = 'sqlite'
            self.handler = SQLiteHandler()
        
        self.handler.connect()

        # TODO later versions were db needs changing
        # self.handler.upgrade()
    
    def __enter__(self):
        # Context manager
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        # Context manager
        self.close()
    
    def close(self) -> None:
        if self.handler:
            self.handler.disconnect()
    
    def connect(self) -> None:
        self.handler.connect()
    
    def disconnect(self) -> None:
        self.handler.disconnect()
    
    def begin_transaction(self) -> None:
        self.handler.begin_transaction()
    
    def commit_transaction(self) -> None:
        self.handler.commit_transaction()
    
    def rollback_transaction(self) -> None:
        self.handler.rollback_transaction()

#    def upgrade() ->  None : 
        # Upgrade the database
        # Nothing to do yet but later if we need to add
    
    def create_table(self, table_name: str, columns: Dict[str, str], 
                    primary_key: str = 'id') -> None:
        if not is_connected():
            self.connect()
        self.handler.create_table(table_name, columns, primary_key)
    
    def drop_table(self, table_name: str) -> None:
        if not is_connected():
            self.connect()
        self.handler.drop_table(table_name)
    
    def insert(self, table_name: str, data: Dict[str, Any]) -> int:
        if not is_connected():
            self.connect()
        return self.handler.insert_record(table_name, data)
    
    def insert_many(self, table_name: str, data_list: List[Dict[str, Any]]) -> List[int]:
        if not is_connected():
            self.connect()
        return self.handler.insert_many(table_name, data_list)
    
    def update(self, table_name: str, data: Dict[str, Any],
              where_clause: str, where_params: tuple) -> int:
        if not is_connected():
            self.connect()
        return self.handler.update_record(table_name, data, where_clause, where_params)
    
    def upsert(self, table_name: str, data: Dict[str, Any]) -> int:
        if not is_connected():
            self.connect()
        return self.handler.upsert_record(table_name, data)
    
    def delete(self, table_name: str, where_clause: str, 
               where_params: tuple) -> int:
        if not is_connected():
            self.connect()
        return self.handler.delete_record(table_name, where_clause, where_params)
    
    def select(self, table_name: str, columns: List[str] = None,
              where_clause: str = None, where_params: tuple = None,
              order_by: str = None, limit: int = None,
              offset: int = None) -> List[Dict]:
        if not is_connected():
            self.connect()
        return self.handler.select_records(table_name, columns, where_clause,
                                          where_params, order_by, limit, offset)
    
    def get_by_id(self, table_name: str, record_id: int, 
                 id_column: str = 'id') -> Optional[Dict]:
        if not is_connected():
            self.connect()
        return self.handler.get_by_id(table_name, record_id, id_column)
    
    def count(self, table_name: str, where_clause: str = None,
             where_params: tuple = None) -> int:
        if not is_connected():
            self.connect()
        return self.handler.count_records(table_name, where_clause, where_params)
    
    def exists(self, table_name: str, where_clause: str,
              where_params: tuple) -> bool:
        if not is_connected():
            self.connect()
        return self.handler.exists(table_name, where_clause, where_params)
    
    def create_index(self, table_name: str, index_name: str, 
                    columns: List[str], unique: bool = False) -> None:
        if not is_connected():
            self.connect()
        self.handler.create_index(table_name, index_name, columns, unique)
    
    def execute_raw_query(self, query: str, params: Optional[tuple] = None) -> List[Dict]:
        if not is_connected():
            self.connect()
        return self.handler.execute_query(query, params)
    
    def execute_raw_non_query(self, query: str, params: Optional[tuple] = None) -> int:
        if not is_connected():
            self.connect()
        return self.handler.execute_non_query(query, params)
    
    def vacuum(self) -> None:
        # Optimize SQLite database
        if not is_connected():
            self.connect()
        if self.db_type == 'sqlite':
            self.handler.vacuum()
        else:
            raise NotImplementedError("Vacuum is only available for SQLite")
    
    def optimize_table(self, table_name: str) -> None:
        # Optimize MySQL table
        if not is_connected():
            self.connect()
        if self.db_type == 'mysql':
            self.handler.optimize_table(table_name)
        else:
            raise NotImplementedError("Table optimization is only available for MySQL")
    
    def get_connection_info(self) -> Dict:
        # Get connection information
        if not is_connected():
            self.connect()
        if self.db_type == 'mysql':
            return self.handler.get_connection_info()
        else:
            raise NotImplementedError("Connection info is only available for MySQL")
    
    def get_db_type(self) -> str:
        # Get the current database type
        return self.db_type
    
    def is_connected(self) -> bool:
        # Check if connected to database
        if self.handler.connection is None:
            return False
        
        if self.db_type == 'sqlite':
            try:
                self.handler.cursor.execute("SELECT 1")
                return True
            except Exception:
                return False
        else:
            try:
                return self.handler.connection.is_connected()
            except Exception:
                return False

    def getTariffData(self, product_code: str, tariff_code: str, UTC_valid_from: str, UTC_valid_to: str, check_count: int = -1) -> List[Dict]:
        ret = {}

        params = (product_code, tariff_code, UTC_valid_from, UTC_valid_to);

        data = self.select('tariff_data', 
                            ['valid_from', 'valid_to', 'value_inc_vat', 'value_exc_vat'],
                            'product_code = ? AND tariff_code = ? AND valid_from >= ? AND valid_from <= ?',
                            params,
                            'valid_from ASC')

        # Check if the number or rows received is what is expected
        if check_count >= 0:
            if len(data) == check_count:
                ret = {'results': data}
        else:
            ret = {'results': data}

        return ret
    
    def saveTariffData(self, product_code: str, tariff_code: str, tariff_data: list[dict]):

        # Check we have data to save
        if len(tariff_data) < 1:
            return;

        sql = ''

        if self.db_type == 'sqlite':
            sql = 'INSERT OR '
        
        sql += "REPLACE INTO tariff_data (product_code, tariff_code, valid_from, valid_to, value_inc_vat, value_exc_vat) VALUES "

        # Build the values
        placeholders = [];
        values = [];
        for item in tariff_data:
            if self.db_type == 'sqlite':
                placeholders.append("(?, ?, ?, ?, ?, ?)")
            else:
                placeholders.append("(%s, %s, %s, %s, %s, %s)")
            values.extend([
                product_code,
                tariff_code,
                tools.convert_timezone(tools.parse_datetime(item['valid_from']), 'UTC', '%Y-%m-%d %H:%M:%S'),
                tools.convert_timezone(tools.parse_datetime(item['valid_to']), 'UTC', '%Y-%m-%d %H:%M:%S'),
                item['value_inc_vat'],
                item['value_exc_vat']
            ])
        
        sql += ", ".join(placeholders)

        self.execute_raw_non_query(sql, values)

    def getConsumptionData(self, meter_mpan: str, meter_serial:str, UTC_interval_start: str, UTC_interval_end: str, check_count: int = -1) -> dict:
        ret = {}

        params = [meter_mpan, meter_serial, UTC_interval_start, UTC_interval_end];

        data = self.select('consumption_data', 
                            ['consumption', 'interval_start', 'interval_end'],
                            'meter_mpan = ? AND meter_serial = ? AND interval_start >= ? AND interval_start <= ?',
                            params,
                            'interval_start ASC')

        # Check if the number or rows received is what is expected
        if check_count >= 0:
            if len(data) == check_count:
                ret = {'results': data}
        else:
            ret = {'results': data}

        return ret
    
    def saveConsumptionData(self, meter_mpan: str, meter_serial: str, consumption_data: list[dict]) -> None:

        # Check we have data to save
        if len(consumption_data) < 1:
            return;

        sql = ''

        if self.db_type == 'sqlite':
            sql = 'INSERT OR '
        
        sql += "REPLACE INTO consumption_data (meter_mpan, meter_serial, consumption, interval_start, interval_end) VALUES "

        # Build the values
        placeholders = [];
        values = [];
        for item in consumption_data:
            if self.db_type == 'sqlite':
                placeholders.append("(?, ?, ?, ?, ?)")
            else:
                placeholders.append("(%s, %s, %s, %s, %s)")
            values.extend([
                meter_mpan, 
                meter_serial, 
                item['consumption'], 
                tools.convert_timezone(tools.parse_datetime(item['interval_start']), 'UTC', '%Y-%m-%d %H:%M:%S'),
                tools.convert_timezone(tools.parse_datetime(item['interval_end']), 'UTC', '%Y-%m-%d %H:%M:%S'),
            ])
        
        sql += ", ".join(placeholders)

        self.execute_raw_non_query(sql, values)

    def getStandardTariffData(self, product_code: str, tariff_code: str, UTC_valid_from: str) -> dict:
        ret = {}

        params = [product_code, tariff_code, UTC_valid_from];

        data = self.select('standard_tariff_data', 
                            ['valid_from', 'valid_to', 'value_inc_vat', 'value_exc_vat', '\'DIRECT_DEBIT\' AS payment_method'],
                            'product_code = ? AND tariff_code = ? AND ? BETWEEN valid_from AND valid_to',
                            params,
                            'valid_from DESC',
                            1)

        if len(data) > 0:
            ret = {'results': data}

        return ret

    def saveStandardTariffData(self, product_code: str, tariff_code: str, tariff_data: list[dict]):

        # Check we have data to save
        if len(tariff_data) < 1:
            return

        sql = ''

        if self.db_type == 'sqlite':
            sql = 'INSERT OR '
        
        sql += "REPLACE INTO standard_tariff_data (product_code, tariff_code, valid_from, valid_to, value_inc_vat, value_exc_vat) VALUES "

        # Build the values
        placeholders = [];
        values = [];
        for item in tariff_data:
            if self.db_type == 'sqlite':
                placeholders.append("(?, ?, ?, ?, ?, ?)")
            else:
                placeholders.append("(%s, %s, %s, %s, %s, %s)")
            values.extend([
                product_code, 
                tariff_code, 
                tools.convert_timezone(tools.parse_datetime(item['valid_from']), 'UTC', '%Y-%m-%d %H:%M:%S'),
                tools.convert_timezone(tools.parse_datetime(item['valid_to']), 'UTC', '%Y-%m-%d %H:%M:%S') if item['valid_to'] else None,
                item['value_inc_vat'],
                item['value_exc_vat']
            ])
        
        sql += ", ".join(placeholders)

        self.execute_raw_non_query(sql, values)

