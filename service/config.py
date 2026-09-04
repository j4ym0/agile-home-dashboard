import os
import json
import re
import ast
from pathlib import Path
from typing import Any, Dict, Optional

class ConfigMeta(type):
    
    def __getattr__(cls, name: str) -> Any:
        # Used for getting class level attributes.
        if name.startswith('_'):
            raise AttributeError(f"'{cls.__name__}' has no attribute '{name}'")
        
        if not cls._loaded:
            cls.init()
        
        if name in cls._config:
            value = cls._config[name]
            if isinstance(value, dict):
                # Create a new Config instance for nested access
                return Config._create_nested_config(value)
            return value
        
        raise AttributeError(f"Config key '{name}' not found")
    
    def __setattr__(cls, name: str, value: Any) -> None:
        # Used for setting class level attributes.
        if name in ('_instance', '_config', '_loaded', '_initialized', 
                   '_config_file', '_default_config_file') or name.startswith('_'):
            super().__setattr__(name, value)
            return
        
        if not cls._loaded:
            cls.init()
        cls._config[name] = value


class Config(metaclass=ConfigMeta):
    _instance: Optional['Config'] = None
    _config: Dict[str, Any] = {}
    _loaded: bool = False
    _config_file: str = '/var/www/html/config.php'
    _default_config_file: str = '/var/www/html/core/default.config.php'

    def __new__(cls, *args, **kwargs):
        # Only create singleton if this is the main Config class
        # and we're not creating a nested config
        if cls is Config and cls._instance is None:
            cls._instance = super(Config, cls).__new__(cls)
            return cls._instance
        return super(Config, cls).__new__(cls)
    
    def __init__(self, config_data: Optional[Dict] = None):
        if not hasattr(self, '_initialized'):
            object.__setattr__(self, '_initialized', True)
            if config_data is not None:
                object.__setattr__(self, '_config', config_data)
            else:
                self.init()
    
    @classmethod
    def _create_nested_config(cls, data: Dict) -> 'Config':
        # Create a new instance without using the singleton
        new_config = object.__new__(cls)
        new_config.__init__(data)
        return new_config
    
    def __getattr__(self, name: str) -> Any:
        # Filter out any _var for internal
        if name.startswith('_'):
            raise AttributeError(f"'{self.__class__.__name__}' has no attribute '{name}'")
        
        self.init()
        
        if name in self._config:
            value = self._config[name]
            if isinstance(value, dict):
                # Return a new Config instance for nested access
                return Config._create_nested_config(value)
            return value
        
        raise AttributeError(f"Config key '{name}' not found")
    
    def __setattr__(self, name: str, value: Any) -> None:
        # Set internal vars in the class
        if name in ('_instance', '_config', '_loaded', '_initialized', 
                   '_config_file', '_default_config_file') or name.startswith('_'):
            object.__setattr__(self, name, value)
            return
        
        self.init()
        self._config[name] = value
    
    def __contains__(self, key: str) -> bool:
        self.init()
        
        keys = key.split('.')
        value = self._config
        
        for k in keys:
            if not isinstance(value, dict) or k not in value:
                return False
            value = value[k]
        
        return True
    
    def __repr__(self):
        # Get representation of Setting
        return f"Config({self._config})"
    
    @classmethod
    def get_instance(cls) -> 'Config':
        # Get the singleton instance
        if cls._instance is None:
            cls._instance = cls()
        return cls._instance
    
    @classmethod
    def init(cls) -> None:
        # Init Settings
        if cls._loaded:
            return
        
        cls._load_default_config()
        cls._load_config_file()
        cls._load_env_vars()
        
        cls._loaded = True
        print("config loaded")
    
    @classmethod
    def _load_default_config(cls) -> None:
        # Load default configuration
        default_path = Path('/var/www/html/core/default.config.php')
        
        if default_path.exists():
            try:
                cls._config = cls._parse_php_config_file(str(default_path))
                print(f"Loaded default config from: {default_path}")
            except Exception as e:
                print(f"WARNING: Failed to load default config: {e}")
        else:
            print(f"ERROR: Default config not found: {default_path}")
    
    @classmethod
    def _load_config_file(cls) -> None:
        # Load user configuration file
        config_path = Path('/var/www/html/config.php')
        
        if config_path.exists():
            try:
                data = cls._parse_php_config_file(str(config_path))
                if data:
                    cls._config = cls._array_replace_recursive(cls._config, data)
                    print(f"Loaded user config from: {config_path}")
            except Exception as e:
                print(f"WARNING: Failed to load user config: {e}")
        else:
            print(f"ERROR: User config not found: {config_path}")
    
    @classmethod
    def _load_env_vars(cls) -> None:
        # Load environment variables
        env_vars_loaded = 0
        
        for key, value in os.environ.items():
            # Split double _ for nested vars
            keys = key.split('__')
            
            if not keys or not keys[0]:
                continue
            
            current = cls._config
            
            for i, k in enumerate(keys):
                k = k.lower()
                
                if i == len(keys) - 1:
                    current[k] = cls._cast_env_value(value)
                    env_vars_loaded += 1
                else:
                    if k not in current or not isinstance(current[k], dict):
                        current[k] = {}
                    current = current[k]
        
        if env_vars_loaded > 0:
            print(f"Loaded {env_vars_loaded} environment variables")
    
    @classmethod
    def _cast_env_value(cls, value: str) -> Any:
        # Cast environment variable to type
        if value.lower() in ('true', '1', 'yes', 'on'):
            return True
        if value.lower() in ('false', '0', 'no', 'off'):
            return False
        if value.lower() in ('null', 'none', '~'):
            return None
        if value.isdigit():
            return int(value)
        try:
            if value.replace('.', '', 1).isdigit():
                return float(value)
        except:
            pass
        try:
            if value.startswith(('{', '[')):
                return json.loads(value)
        except:
            pass
        return value
    
    @classmethod
    def _parse_php_config_file(cls, file_path: str) -> Dict:

        # Parse PHP file
        with open(file_path, 'r') as f:
            content = f.read()

        content = re.sub(r'<\?php|\?>', '', content).strip()
        include_pattern = r'include\s*["\'](.+?)["\']\s*;'

        def replace_include(match):
            include_file = match.group(1)
            include_path = Path(file_path).parent / include_file

            if include_path.exists():
                try:
                    with open(include_path, 'r') as f:
                        return f.read()
                except:
                    return ''

            return ''

        content = re.sub(include_pattern, replace_include, content)
        patterns = [
            r'\$CONFIG\s*=\s*array\s*\((.*?)\)\s*;',
            r'public\s+static\s+\$settings\s*=\s*\[(.*?)\]\s*;',
        ]

        match = None
        array_type = None

        for pattern in patterns:
            match = re.search(pattern, content, re.DOTALL | re.IGNORECASE)

            if match:
                array_type = 'array' if 'array' in pattern else 'bracket'
                break

        if match:

            array_content = match.group(1)
            array_content = cls._convert_php_array_to_python(array_content, array_type)

            try:
                return ast.literal_eval('{' + array_content + '}')

            except:

                try:
                    return eval('{' + array_content + '}')

                except:
                    return {}

        return {}


    @classmethod
    def _convert_php_array_to_python(cls, php_array: str, array_type: str = 'array') -> str:
        # Convert PHP array syntax to Python
        php_array = re.sub(r'//.*?$', '', php_array, flags=re.MULTILINE)

        # Remove # comments
        php_array = re.sub(r'#.*?$', '', php_array, flags=re.MULTILINE)

        # Remove /* ... */ comments
        php_array = re.sub(r'/\*.*?\*/', '', php_array, flags=re.DOTALL)

        # PHP array()
        if array_type == 'array':
            php_array = re.sub(r'array\s*\(', '{', php_array, flags=re.IGNORECASE)
            php_array = re.sub(r'\)', '}', php_array)

        # PHP short arrays [...]
        #
        # Nested [ ... ] arrays need to become Python
        # dictionaries when they contain =>.
        if array_type == 'bracket':
            php_array = re.sub(r'\[', '{', php_array)
            php_array = re.sub(r'\]', '}', php_array)

        # PHP associative array operator
        php_array = re.sub(r'=>', ':', php_array)

        # PHP values
        php_array = re.sub(r'\bnull\b', 'None', php_array, flags=re.IGNORECASE)
        php_array = re.sub(r'\btrue\b', 'True', php_array, flags=re.IGNORECASE)
        php_array = re.sub(r'\bfalse\b', 'False', php_array, flags=re.IGNORECASE)

        return php_array
            
    @classmethod
    def _array_replace_recursive(cls, base: Dict, new: Dict) -> Dict:
        # Recursively merge dictionaries
        result = base.copy()
        for key, value in new.items():
            if (key in result and 
                isinstance(result[key], dict) and 
                isinstance(value, dict)):
                result[key] = cls._array_replace_recursive(result[key], value)
            else:
                result[key] = value
        return result
    
    @classmethod
    def get(cls, key: str, default: Any = None) -> Any:
        # Get config value using dot notation
        cls.init()
        
        keys = key.split('.')
        value = cls._config
        
        for k in keys:
            if not isinstance(value, dict) or k not in value:
                return default
            value = value[k]
        
        return value
    
    @classmethod
    def set(cls, key: str, value: Any) -> None:
        # Set config value using dot notation
        cls.init()
        
        keys = key.split('.')
        current = cls._config
        
        for i, k in enumerate(keys):
            if i == len(keys) - 1:
                current[k] = value
            else:
                if k not in current or not isinstance(current[k], dict):
                    current[k] = {}
                current = current[k]
    
    @classmethod
    def get_all(cls) -> Dict[str, Any]:
        cls.init()
        return cls._config.copy()
    
    @classmethod
    def reload(cls) -> None:
        # Reload configuration
        cls._loaded = False
        cls._config = {}
        cls.init()

    @classmethod
    def to_dict(cls, obj=None) -> Dict:
        # Convert Config object or nested Config objects to dict
        if obj is None:
            return cls._config.copy()
        
        if isinstance(obj, Config):
            return obj._config.copy()
        elif isinstance(obj, dict):
            result = {}
            for key, value in obj.items():
                if isinstance(value, Config):
                    result[key] = value._config.copy()
                elif isinstance(value, dict):
                    result[key] = cls.to_dict(value)
                else:
                    result[key] = value
            return result
        return obj