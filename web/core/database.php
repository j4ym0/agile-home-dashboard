<?php

class Database {
    private $connection;
    private $type;
    
    public function __construct() {
        $this->type = Config::get('database.type');
        
        switch ($this->type) {
            case 'mysql':
                $this-> connectMySQL();
                break;
            case 'sqlite':
                $this->connectSQLite();
                break;
            default:
                throw new InvalidArgumentException("Unsupported database type: " . $this->type);
        }
    }
    
    private function connectMySQL(): void {
        $host = Config::get('database.mysql.host', 'localhost');
        $dbname = Config::get('database.mysql.dbname', 'agile_dashboard');
        $username = Config::get('database.mysql.username', 'root');
        $password = Config::get('database.mysql.password', '');
        $charset = Config::get('database.mysql.charset', 'utf8mb4');
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if users table exists
            $chk = $this->connection->query("SHOW TABLES LIKE 'users'");
            if ($chk->rowCount() == 0) {
                // Users table doesn't exist, create all tables
                $this->createTables();
                //echo "MySQL tables created successfully in existing database";
            } else {
                // Database and users table exist
                //echo "MySQL database and tables already exist";
            }
        } catch (PDOException $e) {
            throw new RuntimeException("MySQL connection failed: " . $e->getMessage());
        }
    }
    
    private function connectSQLite(): void {
        $path = Config::get('database.sqlite.path', ':memory:');

        // Check if database file exists
        $dbExists = $path == ':memory:' ? true : file_exists($path);

        try {
            // Connect to SQLite database (it will be created if it doesn't exist)
            $this->connection = new PDO("sqlite:{$path}");
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (!$dbExists) {
                // Create tables for new database
                $this->createTables();
                //echo "SQLite database and tables created successfully";
                $this->connection->exec('PRAGMA foreign_keys = ON');
            } else {
                // Database exists, verify tables (optional)
                //echo "SQLite database already exists";
                $this->connection->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            throw new RuntimeException("SQLite connection failed: " . $e->getMessage());
        }
    }

    private function createTables() {
        // Common table creation for both database types
        $tables = [
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY " . ($this->type === 'mysql' ? 'AUTO_INCREMENT' : 'AUTOINCREMENT') . ",
                username VARCHAR(50) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                token VARCHAR(64) NULL,
                token_expires DATETIME NULL
                );",
            'settings' => "CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) UNIQUE NOT NULL,
                setting_value VARCHAR(255) NOT NULL
                );",
            'tariff_data' => "CREATE TABLE IF NOT EXISTS tariff_data (
                product_code VARCHAR(50) NOT NULL,
                tariff_code VARCHAR(50) NOT NULL,
                valid_from DATETIME NOT NULL,
                valid_to DATETIME,
                value_inc_vat DECIMAL(10, 4),
                value_exc_vat DECIMAL(10, 4),
                PRIMARY KEY (tariff_code, valid_from)
            );",
            'standard_tariff_data' => "CREATE TABLE IF NOT EXISTS standard_tariff_data (
                product_code VARCHAR(50) NOT NULL,
                tariff_code VARCHAR(50) NOT NULL,
                valid_from DATETIME NOT NULL,
                valid_to DATETIME,
                value_inc_vat DECIMAL(10, 4),
                value_exc_vat DECIMAL(10, 4),
                PRIMARY KEY (tariff_code, valid_from)
            );",
            'consumption_data' => "CREATE TABLE IF NOT EXISTS consumption_data (
                meter_mpan VARCHAR(50) NOT NULL,
                meter_serial VARCHAR(50) NOT NULL,
                consumption DECIMAL(10, 4),
                interval_start DATETIME NOT NULL,
                interval_end DATETIME NOT NULL,
                PRIMARY KEY (meter_mpan, meter_serial, interval_start)
            );"
        ];

        foreach ($tables as $tableName => $sql) {
            $this->connection->exec($sql);
        }
    }
    
    public function query(string $sql, array $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            // Check if error is "no such table"
            if (strpos($e->getMessage(), 'no such table') !== false) {
                $this->createTables(); // Create missing tables
                
                // Retry the query after table creation
                try {
                    $stmt = $this->connection->prepare($sql);
                    $stmt->execute($params);
                    return $stmt;
                } catch (\PDOException $e) {
                    // If it fails again after table creation, throw the exception
                    throw new \RuntimeException("Query failed even after table creation: " . $e->getMessage());
                }
            }
            
            // For all other errors, rethrow
            throw $e;
        }
    }
    
    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }
    
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function replace(string $table, array $columns, array $values = []): int {
        if ($this->type === 'sqlite'){
            $sql = "INSERT OR REPLACE INTO $table (" . implode(', ', $columns). ") VALUES (" . rtrim(str_repeat('?, ', count($values)), ', ') . ")";
        }else{
            $sql = "REPLACE INTO $table (" . implode(', ', $columns). ") VALUES (" . rtrim(str_repeat('?, ', count($values)), ', ') . ")";
        }
        $stmt = $this->query($sql, $values);
        return $stmt->rowCount();
    }
    
    public function lastInsertId(): string {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }
    
    public function commit(): bool {
        return $this->connection->commit();
    }
    
    public function rollBack(): bool {
        return $this->connection->rollBack();
    }
    
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    public function getType(): string {
        return $this->type;
    }

    public function close(): void {
        if ($this->connection !== null) {
            // For MySQL - ensure any pending transactions are handled
            if ($this->type === 'mysql' && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            
            // For SQLite - optimize before closing if needed
            if ($this->type === 'sqlite') {
                $this->connection->exec('PRAGMA optimize');
            }
            
            // Nullify the connection
            $this->connection = null;
        }
    }

    // Add destructor to automatically close connection when object is destroyed
    public function __destruct() {
        $this->close();
    }

    // Get the tariff data from the database
    public function getTariffData(string $productCode, string $tariffCode, string $UTC_valid_from, string $UTC_valid_to, int $checkCount = -1){
        $ret = [];

        $sql = 'SELECT valid_from, valid_to, value_inc_vat, value_exc_vat 
                FROM tariff_data 
                WHERE product_code = ?
                AND tariff_code = ?
                AND valid_from >= ?
                AND valid_from <= ?
                ORDER BY valid_from DESC';

        $params = [$productCode, $tariffCode, $UTC_valid_from, $UTC_valid_to];

        $data = $this->fetchAll($sql, $params);

        // Check if the number or rows received is what is expected
        if ($checkCount >= 0){
            if (count($data) == $checkCount){
                $ret['results'] = $data;
            }
        }else{
            $ret['results'] = $data;
        }

        return $ret;
    }
    // Save the tariff data from the API
    public function saveTariffData(string $productCode, string $tariffCode, array $tariffData){

        // Check we have data to save
        if (count($tariffData) < 1){
            return;
        }

        $sql = '';

        if ($this->type === 'sqlite'){
            $sql = 'INSERT OR ';
        }
        $sql .= "REPLACE INTO tariff_data (product_code, tariff_code, valid_from, valid_to, value_inc_vat, value_exc_vat) VALUES ";

        // Build the values
        $placeholders = [];
        $values = [];
        foreach ($tariffData as $item) {
            $placeholders[] = "(?, ?, ?, ?, ?, ?)";
            $values = array_merge($values, [$productCode, $tariffCode, 
                (new DateTime($item['valid_from']))->format('Y-m-d H:i:s'), 
                (new DateTime($item['valid_to']))->format('Y-m-d H:i:s'), 
                $item['value_inc_vat'], $item['value_exc_vat']]);
        }
        
        $sql .= implode(", ", $placeholders);

        $stm = $this->execute($sql, $values);
    }

    public function getStandardTariffData(string $productCode, string $tariffCode, string $UTC_valid_from){
        $ret = [];

        $sql = 'SELECT valid_from, valid_to, value_inc_vat, value_exc_vat, \'DIRECT_DEBIT\' AS payment_method 
                FROM standard_tariff_data 
                WHERE product_code = ?
                AND tariff_code = ?
                AND valid_from <= ?
                ORDER BY valid_from DESC';

        $params = [$productCode, $tariffCode, $UTC_valid_from];

        $data = $this->fetchAll($sql, $params);

        $ret['results'] = $data;
      
        return $ret;
    }
    // Save the tariff data from the API
    public function saveStandardTariffData(string $productCode, string $tariffCode, array $tariffData){

        // Check we have data to save
        if (count($tariffData) < 1){
            return;
        }

        $sql = '';

        if ($this->type === 'sqlite'){
            $sql = 'INSERT OR ';
        }
        $sql .= "REPLACE INTO standard_tariff_data (product_code, tariff_code, valid_from, valid_to, value_inc_vat, value_exc_vat) VALUES ";

        // Build the values
        $placeholders = [];
        $values = [];
        foreach ($tariffData as $item) {
            if ($item['payment_method'] === 'DIRECT_DEBIT'){
                $placeholders[] = "(?, ?, ?, ?, ?, ?)";
                $values = array_merge($values, [$productCode, $tariffCode, 
                    (new DateTime($item['valid_from']))->format('Y-m-d H:i:s'), 
                    $item['valid_to'] ? (new DateTime($item['valid_to']))->format('Y-m-d H:i:s') : null, 
                    $item['value_inc_vat'], $item['value_exc_vat']]);
            }
        }
        
        $sql .= implode(", ", $placeholders);

        $this->execute($sql, $values);
    }

    // Get the consumption data from the database
    public function getConsumptionData(string $meter_mpan, string $meter_serial, string $UTC_interval_start, string $UTC_interval_end, int $checkCount = -1){
        $ret = [];

        $sql = 'SELECT consumption, interval_start, interval_end 
                FROM consumption_data 
                WHERE meter_mpan = ?
                AND meter_serial = ?
                AND interval_start >= ?
                AND interval_start <= ?
                ORDER BY interval_start ASC';

        $params = [$meter_mpan, $meter_serial, $UTC_interval_start, $UTC_interval_end];

        $data = $this->fetchAll($sql, $params);

        // Check if the number or rows received is what is expected
        if ($checkCount >= 0){
            if (count($data) == $checkCount){
                $ret['results'] = $data;
            }
        }else{
            $ret['results'] = $data;
        }

        return $ret;
    }
    // Save the tariff data from the API
    public function saveConsumptionData(string $meter_mpan, string $meter_serial, array $consumptionData){

        // Check we have data to save
        if (count($consumptionData) < 1){
            return;
        }

        $sql = '';

        if ($this->type === 'sqlite'){
            $sql = 'INSERT OR ';
        }
        $sql .= "REPLACE INTO consumption_data (meter_mpan, meter_serial, consumption, interval_start, interval_end) VALUES ";

        // Build the values
        $placeholders = [];
        $values = [];
        foreach ($consumptionData as $item) {
            $placeholders[] = "(?, ?, ?, ?, ?)";
            $values = array_merge($values, [$meter_mpan, $meter_serial, 
                $item['consumption'], 
                (new DateTime($item['interval_start']))->format('Y-m-d H:i:s'), 
                (new DateTime($item['interval_end']))->format('Y-m-d H:i:s')]);
        }
        
        $sql .= implode(", ", $placeholders);

        $this->execute($sql, $values);
    }
}
?>