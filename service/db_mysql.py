import mysql.connector
from mysql.connector import Error
from typing import Dict, List, Any, Optional, Tuple
from config import Config

class MySQLHandler:

    def __init__(self):
        self.connection = None
        self.cursor = None
    
    def connect(self) -> None:

        try:
            self.connection = mysql.connector.connect(
                host=Config.get('database.mysql.host',          '172.17.0.1'),
                database=Config.get('database.mysql.dbname',    'agile_dashboard'),
                user=Config.get('database.mysql.user',          'agile_dashboard_user'),
                password=Config.get('database.mysql.password',  'agile_dashboard_password'),
                port=Config.get('database.mysql.port',          3306),
                charset=Config.get('database.mysql.charset',    'utf8mb4'),
                use_unicode=True
            )
            self.cursor = self.connection.cursor(dictionary=True)
        except Error as e:
            raise Exception(f"MySQL connection error: {e}")
    
    def disconnect(self) -> None:
        if self.connection and self.connection.is_connected():
            self.cursor.close()
            self.connection.close()
    
    def execute_query(self, query: str, params: Optional[tuple] = None) -> List[Dict]:
        # Execute SELECT query and return results
        try:
            if params:
                self.cursor.execute(query, params)
            else:
                self.cursor.execute(query)
            return self.cursor.fetchall()
        except Error as e:
            raise Exception(f"MySQL query error: {e}")
            raise
    
    def execute_non_query(self, query: str, params: Optional[tuple] = None) -> int:
        # Execute INSERT, UPDATE, DELETE query and return row count
        try:
            if params:
                self.cursor.execute(query, params)
            else:
                self.cursor.execute(query)
            self.connection.commit()
            return self.cursor.rowcount
        except Error as e:
            raise Exception(f"MySQL non-query error: {e}")
            self.connection.rollback()
            raise
    
    def create_table(self, table_name: str, columns: Dict[str, str], 
                    primary_key: str = 'id') -> None:
        columns_sql = ', '.join([f"{col_name} {col_type}" for col_name, col_type in columns.items()])
        query = f"""CREATE TABLE IF NOT EXISTS {table_name} (
                    {primary_key} INT AUTO_INCREMENT PRIMARY KEY, 
                    {columns_sql}
                   ) ENGINE=InnoDB DEFAULT CHARSET={Config.get('database.mysql.charset', 'utf8mb4')}"""
        self.execute_non_query(query)
    
    def drop_table(self, table_name: str) -> None:
        query = f"DROP TABLE IF EXISTS {table_name}"
        self.execute_non_query(query)
    
    def insert_record(self, table_name: str, data: Dict[str, Any]) -> int:
        columns = ', '.join(data.keys())
        placeholders = ', '.join(['%s' for _ in data])
        query = f"INSERT INTO {table_name} ({columns}) VALUES ({placeholders})"
        self.execute_non_query(query, tuple(data.values()))
        return self.cursor.lastrowid
    
    def insert_many(self, table_name: str, data_list: List[Dict[str, Any]]) -> List[int]:
        inserted_ids = []
        for data in data_list:
            inserted_id = self.insert_record(table_name, data)
            inserted_ids.append(inserted_id)
        return inserted_ids
    
    def update_record(self, table_name: str, data: Dict[str, Any],
                     where_clause: str, where_params: tuple) -> int:
        where_clause = where_clause.replace('?', '%s')
        set_clause = ', '.join([f"{key} = %s" for key in data.keys()])
        query = f"UPDATE {table_name} SET {set_clause} WHERE {where_clause}"
        params = tuple(data.values()) + where_params
        return self.execute_non_query(query, params)
    
    def upsert_record(self, table_name: str, data: Dict[str, Any]) -> int:
        columns = ', '.join(data.keys())
        placeholders = ', '.join(['%s' for _ in data])
        query = f"REPLACE INTO {table_name} ({columns}) VALUES ({placeholders})"
        self.execute_non_query(query, tuple(data.values()))
    
    def delete_record(self, table_name: str, where_clause: str,
                     where_params: tuple) -> int:
        where_clause = where_clause.replace('?', '%s')
        query = f"DELETE FROM {table_name} WHERE {where_clause}"
        return self.execute_non_query(query, where_params)
    
    def select_records(self, table_name: str, columns: List[str] = None,
                      where_clause: str = None, where_params: tuple = None,
                      order_by: str = None, limit: int = None,
                      offset: int = None) -> List[Dict]:
        if where_clause:
            where_clause = where_clause.replace('?', '%s')
        cols = ', '.join(columns) if columns else '*'
        query = f"SELECT {cols} FROM {table_name}"
        
        if where_clause:
            query += f" WHERE {where_clause}"
        if order_by:
            query += f" ORDER BY {order_by}"
        if limit:
            query += f" LIMIT {limit}"
        if offset:
            query += f" OFFSET {offset}"
        
        return self.execute_query(query, where_params)
    
    def get_by_id(self, table_name: str, record_id: int, 
                 id_column: str = 'id') -> Optional[Dict]:
        # Get a record by ID
        results = self.select_records(table_name, where_clause=f"{id_column} = %s",
                                    where_params=(record_id,), limit=1)
        return results[0] if results else None
    
    def count_records(self, table_name: str, where_clause: str = None,
                     where_params: tuple = None) -> int:
        if where_clause:
            where_clause = where_clause.replace('?', '%s')
        query = f"SELECT COUNT(*) as count FROM {table_name}"
        if where_clause:
            query += f" WHERE {where_clause}"
        
        result = self.execute_query(query, where_params)
        return result[0]['count'] if result else 0
    
    def exists(self, table_name: str, where_clause: str,
              where_params: tuple) -> bool:
        where_clause = where_clause.replace('?', '%s')
        # Check if a record exists
        return self.count_records(table_name, where_clause, where_params) > 0
    
    def create_index(self, table_name: str, index_name: str, 
                    columns: List[str], unique: bool = False) -> None:
        unique_clause = "UNIQUE " if unique else ""
        columns_str = ', '.join(columns)
        query = f"CREATE {unique_clause}INDEX IF NOT EXISTS {index_name} ON {table_name} ({columns_str})"
        self.execute_non_query(query)
    
    def optimize_table(self, table_name: str) -> None:
        query = f"OPTIMIZE TABLE {table_name}"
        self.execute_non_query(query)
    
    def begin_transaction(self) -> None:
        self.execute_non_query("START TRANSACTION")
    
    def commit_transaction(self) -> None:
        self.connection.commit()
    
    def rollback_transaction(self) -> None:
        self.connection.rollback()
    
    def get_connection_info(self) -> Dict:
        return {
            'host': Config.get('database.mysql.host',       '172.17.0.1'),
            'database': Config.get('database.mysql.dbname', 'agile_dashboard'),
            'user': Config.get('database.mysql.user',       'agile_dashboard_user'),
            'port': Config.get('database.mysql.port',       3306),
            'charset': Config.get('database.mysql.charset', 'utf8mb4'),
            'server_version': self.connection.get_server_info() if self.connection else None
        }