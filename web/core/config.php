<?php
include 'default.config.php';

// Config

class Config {
    private static $instance = null;
    private static $config = [];
    private static $loaded = false;

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }   
    
    public static function init() {
        if (self::$loaded) return;

        // Check if tz environment variable is set and valid, then set timezone
        $tzEnv = getenv('TZ');
        if ($tzEnv && in_array($tzEnv, timezone_identifiers_list())) {
            date_default_timezone_set($tzEnv);
        }
        self::$config['app']['timezone'] = date_default_timezone_get();
        
        // Load defaults
        self::$config = DefaultConfig::$settings;

        // Load user config file (JSON or PHP)
        self::loadConfigFile('config.php');  // Then try PHP

        // Override with environment variables
        self::loadEnvVars();

        self::$loaded = true;
    }

    private static function loadConfigFile($file) {
        if (!file_exists($file)) return;

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        
        include $file;
        if (is_array($CONFIG)) {
            self::$config = array_replace_recursive(self::$config, $CONFIG);
        }
    }

    private static function loadEnvVars() {

        foreach ($_ENV as $key => $value) {
            $keys = explode('__', $key);
            $current = &self::$config;

            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (!isset($current[$k]) || !is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
        }
    }

    public static function get($key, $default = null) {
        self::init();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}