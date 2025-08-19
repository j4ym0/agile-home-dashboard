<?php

class Settings {
    private $db;
    private $settings = [];

    public function __construct($database) {
        $this->db = $database;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    public function set($key, $value) {
        if($this->db->replace('settings', ['setting_key', 'setting_value'], [$key, $value ?? '']) > 0 ){
            $this->settings[$key] = $value;
            return true;
        }else{
            return false;
        }
    }

    public function __get($name) {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }
        throw new RuntimeException("Setting '$name' not found");
    }

    public function __isset($name) {
        return isset($this->settings[$name]);
    }

    public function getAll() {
        return $this->settings;
    }
}
