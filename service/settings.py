from db_handler import DBHandler
from config import Config
from typing import Any, Optional, Dict
from datetime import datetime
import json


class SettingsMeta(type):
    
    def __getattr__(cls, name: str) -> Any:
        # Used for getting class level attributes.
        if name.startswith('_'):
            raise AttributeError(f"'{cls.__name__}' has no attribute '{name}'")
        
        if not cls._loaded:
            cls.init()
        
        instance = cls._instance or cls()
        if name in instance._settings:
            return instance._settings[name]
        
        raise AttributeError(f"Setting key '{name}' not found")
    
    def __setattr__(cls, name: str, value: Any) -> None:
        # Used for setting class level attributes.
        if name in ('_instance', '_settings', '_loaded', '_initialized', '_db') or name.startswith('_'):
            super().__setattr__(name, value)
            return
        
        # Set the setting
        cls.set(name, value)


class Settings(metaclass=SettingsMeta):
    _instance: Optional['Settings'] = None
    _settings: Dict[str, Any] = {}
    _loaded: bool = False
    _last_reload = -1
    
    def __new__(cls, *args, **kwargs):
        # Create singleton instance
        if cls._instance is None:
            cls._instance = super(Settings, cls).__new__(cls)
        return cls._instance
    
    def __init__(self):
        if not hasattr(self, '_initialized'):
            object.__setattr__(self, '_initialized', True)
            self.init()
    
    def __getattr__(self, name: str) -> Any:
        # Filter out any _var for internal
        if name.startswith('_'):
            raise AttributeError(f"'{self.__class__.__name__}' has no attribute '{name}'")
        
        self.init()
        
        if name in self._settings:
            return self._settings[name]
        
        raise AttributeError(f"Setting key '{name}' not found")
    
    def __setattr__(self, name: str, value: Any) -> None:
        # Set internal vars in the class
        if name in ('_instance', '_settings', '_loaded', '_initialized', '_last_reload') or name.startswith('_'):
            object.__setattr__(self, name, value)
            return

        # Set the setting
        self.set(name, value)
    
    def __contains__(self, key: str) -> bool:
        self.init()
        return key in self._settings
    
    def __repr__(self):
        # Get representation of Setting
        return f"Settings({self._settings})"
    
    @classmethod
    def get_instance(cls) -> 'Settings':
        # Get the singleton instance
        if cls._instance is None:
            cls._instance = cls()
        return cls._instance
    
    @classmethod
    def init(cls) -> None:
        # Init Settings
        if cls._loaded:
            return
        
        instance = cls._instance or cls()
        
        instance._load_settings()
        
        cls._loaded = True
        print("Settings loaded from database")
    
    def _load_settings(self) -> None:
        # Get settings from DB
        try:
            with DBHandler() as _db:
                cursor = _db.select("settings", ["setting_key", "setting_value"])
                self._settings = {}
                for row in cursor:
                    # Try to parse JSON values
                    try:
                        self._settings[row["setting_key"]] = json.loads(row["setting_value"])
                    except (json.JSONDecodeError, TypeError):
                        self._settings[row["setting_key"]] = row["setting_value"]
                print(f"Loaded {len(self._settings)} settings")
        except Exception as e:
            print(f"ERROR: Failed to load settings: {e}")
            self._settings = {}
    
    @classmethod
    def get(cls, key: str, default: Optional[Any] = None) -> Any:
        cls.init()
        instance = cls._instance or cls()
        
        # Check if settings outdated and reload if neccessary
        now = datetime.now()
        if not instance._last_reload == now.minute:
            instance._last_reload = now.minute
            cls.reload()

        return instance._settings.get(key, default)
    
    @classmethod
    def set(cls, key: str, value: Any) -> bool:
        cls.init()
        instance = cls._instance or cls()
        
        try:
            instance._settings[key] = value
            # Store as JSON to handle different types
            instance._db.upsert("settings", {
                "setting_key": key,
                "setting_value": json.dumps(value)
            })
            return True
        except Exception as e:
            print(f"ERROR: Failed to set {key}: {e}")
            return False
    
    @classmethod
    def get_all(cls) -> Dict[str, Any]:
        cls.init()
        instance = cls._instance or cls()
        return instance._settings.copy()
    
    @classmethod
    def reload(cls) -> None:
        # Reload all settings
        cls._loaded = False
        cls._settings = {}
        cls.init()
    
    @classmethod
    def delete(cls, key: str) -> bool:
        """Delete a setting from database and cache."""
        cls.init()
        instance = cls._instance or cls()
        
        try:
            if key in instance._settings:
                del instance._settings[key]
                instance._db.delete("settings", {
                    "setting_key": key,
                    "setting_value": json.dumps(value)
                })
                return True
            return False
        except Exception as e:
            print(f"ERROR: Failed to delete {key}: {e}")
            return False
    
    @classmethod
    def to_dict(cls) -> Dict[str, Any]:
        # Convert to dict
        cls.init()
        instance = cls._instance or cls()
        return instance._settings.copy()