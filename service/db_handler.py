from typing import Dict, List, Any, Optional, Union
from db_sqlite import SQLiteHandler
from db_mysql import MySQLHandler
from config import Config

class DBHandler:
    
    def __init__(self):
        self.handler = None
        self._initialize_handler()
    
    def _initialize_handler(self) -> None:
        # Initialize the database with the correct handler based on config settings (e.g.,"""
        if Config.database.type == 'mysql':
            self.handler = MySQLHandler()
        else:
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
        self.handler.create_table(table_name, columns, primary_key)
    
    def drop_table(self, table_name: str) -> None:
        self.handler.drop_table(table_name)
    
    def insert(self, table_name: str, data: Dict[str, Any]) -> int:
        return self.handler.insert_record(table_name, data)
    
    def insert_many(self, table_name: str, data_list: List[Dict[str, Any]]) -> List[int]:
        return self.handler.insert_many(table_name, data_list)
    
    def update(self, table_name: str, data: Dict[str, Any],
              where_clause: str, where_params: tuple) -> int:
        return self.handler.update_record(table_name, data, where_clause, where_params)
    
    def upsert(self, table_name: str, data: Dict[str, Any]) -> int:
        return self.handler.upsert_record(table_name, data)
    
    def delete(self, table_name: str, where_clause: str, 
               where_params: tuple) -> int:
        return self.handler.delete_record(table_name, where_clause, where_params)
    
    def select(self, table_name: str, columns: List[str] = None,
              where_clause: str = None, where_params: tuple = None,
              order_by: str = None, limit: int = None,
              offset: int = None) -> List[Dict]:
        return self.handler.select_records(table_name, columns, where_clause,
                                          where_params, order_by, limit, offset)
    
    def get_by_id(self, table_name: str, record_id: int, 
                 id_column: str = 'id') -> Optional[Dict]:
        return self.handler.get_by_id(table_name, record_id, id_column)
    
    def count(self, table_name: str, where_clause: str = None,
             where_params: tuple = None) -> int:
        return self.handler.count_records(table_name, where_clause, where_params)
    
    def exists(self, table_name: str, where_clause: str,
              where_params: tuple) -> bool:
        return self.handler.exists(table_name, where_clause, where_params)
    
    def create_index(self, table_name: str, index_name: str, 
                    columns: List[str], unique: bool = False) -> None:
        self.handler.create_index(table_name, index_name, columns, unique)
    
    def execute_raw_query(self, query: str, params: Optional[tuple] = None) -> List[Dict]:
        return self.handler.execute_query(query, params)
    
    def execute_raw_non_query(self, query: str, params: Optional[tuple] = None) -> int:
        return self.handler.execute_non_query(query, params)
    
    def vacuum(self) -> None:
        # Optimize SQLite database
        if self.db_type == 'sqlite':
            self.handler.vacuum()
        else:
            raise NotImplementedError("Vacuum is only available for SQLite")
    
    def optimize_table(self, table_name: str) -> None:
        # Optimize MySQL table
        if self.db_type == 'mysql':
            self.handler.optimize_table(table_name)
        else:
            raise NotImplementedError("Table optimization is only available for MySQL")
    
    def get_connection_info(self) -> Dict:
        # Get connection information
        if self.db_type == 'mysql':
            return self.handler.get_connection_info()
        else:
            raise NotImplementedError("Connection info is only available for MySQL")
    
    def get_db_type(self) -> str:
        # Get the current database type
        return self.db_type
    
    def is_connected(self) -> bool:
        # Check if connected to database
        if self.db_type == 'sqlite':
            return self.handler.connection is not None
        else:
            return self.handler.connection is not None and self.handler.connection.is_connected()
