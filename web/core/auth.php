<?php

class AuthSystem {
    private $conn;
    public $noUsers = false;
    
    public function __construct($database) {
        $this->conn = $database;
        secure_session_start(Config::get('SESSION_NAME'), Config::get('COOKIE_SECURE'), Config::get('COOKIE_HTTPONLY'));
        $this->noUsers = $this->checkForUsers();
    }
    
    // Check if there are any users in the database
    private function checkForUsers() {
        $stmt = $this->conn->fetchOne("SELECT COUNT(*) as user_count FROM users");
        if ($stmt && isset($stmt['user_count'])) {
            return ($stmt['user_count'] == 0);
        }
        return true;
    }

    // Check if user is logged in
    public function is_logged_in() {
        // Check we are securing with login
        if (!Config::get('SECURE_LOGIN')){
            return true;
        }

        // Check session
        if (isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['login_string'])) {
            $user_id = $_SESSION['user_id'];
            $login_string = $_SESSION['login_string'];
            $username = $_SESSION['username'];
            
            // Verify session
            $user_browser = $_SERVER['HTTP_USER_AGENT'];
            $login_check = hash('sha512', $user_id . $username . $user_browser);
            
            if (hash_equals($login_check, $login_string) && $this->check_token($_COOKIE['token'])) {
                return true;
            }
        }
        
        // Check token cookie
        if (isset($_COOKIE['token'])) {
            return $this->check_token($_COOKIE['token']);
        }
        
        return false;
    }
    
    // Login user
    public function login($username, $password, $token = true) {
        // Sanitize input
        $username = filter_var($_POST['username'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        // Get user from database
        $stmt = $this->conn->fetchOne("SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1", [$username]);

        if (($stmt === false || $stmt === null)) {
            // User not found
            return false;
        }else{
            $user_id = $stmt['id'];
            $username = $stmt['username'];
            $password_hash =$stmt['password_hash'];
            
            // Verify password
            if (password_verify($password, $password_hash)) {
                // Password is correct
                $user_browser = $_SERVER['HTTP_USER_AGENT'];
                
                // Create session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['login_string'] = hash('sha512', $user_id . $username . $user_browser);
                
                // Update last login
                $this->update_last_login($user_id);
                
                // Set remember me token if requested
                if ($token) {
                    $this->set_token($user_id);
                }
                
                return true;
            }
        }
        
        return false;
    }
    
    // Logout user
    public function logout() {
        // Unset all session values
        $_SESSION = array();
        
        // Delete token
        if (isset($_COOKIE['token'])) {
            $this->delete_token($_COOKIE['token']);
            setcookie('token', '', time() - 3600, Config::get('COOKIE_PATH'), Config::get('COOKIE_DOMAIN'), Config::get('COOKIE_SECURE'), Config::get('COOKIE_HTTPONLY'));
        }
        
        // Get session parameters
        $params = session_get_cookie_params();
        
        // Delete the actual cookie
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
        
        // Destroy session
        session_destroy();
    }
    
    // Set token
    private function set_token($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + Config::get('TOKEN_LIFETIME'));
        
        $this->conn->execute("UPDATE users SET token = ?, token_expires = ? WHERE id = ?", [$token, $expires, $user_id]);
        
        setcookie(
            'token',
            $token,
            time() + Config::get('TOKEN_LIFETIME'),
            Config::get('COOKIE_PATH'),
            Config::get('COOKIE_DOMAIN'),
            Config::get('COOKIE_SECURE'),
            Config::get('COOKIE_HTTPONLY')
        );
    }
    
    // Check token
    private function check_token($token) {
        // Sanitize input
        $token = filter_var($token, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $this->conn->fetchOne("SELECT id, username FROM users WHERE token = ? AND token_expires > ? LIMIT 1", [$token, $current_time]);
        
        if (($stmt === false || $stmt === null)) {
            // User not found
            return false;
        }else{
            $user_id = $stmt['id'];
            $username = $stmt['username'];

            // Create session
            $user_browser = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['login_string'] = hash('sha512', $user_id . $username . $user_browser);
            
            // Update last login
            $this->update_last_login($user_id);
            
            // Refresh token
            $this->set_token($user_id);
            
            return true;
        }
    }
    
    // Delete token
    private function delete_token($token) {
        // Sanitize input
        $token = filter_var($token, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $this->conn->execute("UPDATE users SET token = NULL, token_expires = NULL WHERE token = ?", [$token]);
    }
    
    // Update last login time
    private function update_last_login($user_id) {
        $this->conn->execute("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?", [$user_id]);
    }

     // Check for user account
    public function userExists(string $username){
        // Sanitize input
        $username = filter_var($username, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($this->conn->fetchOne('SELECT id FROM users WHERE username = ?', [$username])){
            return true;
        }else{
            return false;
        }
    }

    // Create a user
    public function addNewUser($username, $password) {
        // Sanitize input
        $username = filter_var($username, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Check if user already exists
        $stmt = $this->conn->fetchOne("SELECT id FROM users WHERE username = ? LIMIT 1", [$username]);
        if ($stmt) {
            return false; // User already exists
        }
        
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user into database
        $this->conn->execute("INSERT INTO users (username, password_hash) VALUES (?, ?)", [$username, $password_hash]);
        
        return $this->userExists($username);
    }

    // Delete a user
    public function deleteUser($username, $password) {
        // Sanitize input
        $username = filter_var($username, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Check if user exists
        $stmt = $this->conn->fetchOne("SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1", [$username]);

        if (($stmt === false || $stmt === null)) {
            // User not found
            return true;
        }else{
            $user_id = $stmt['id'];
            $username = $stmt['username'];
            $password_hash =$stmt['password_hash'];
            
            // Verify password
            if (password_verify($password, $password_hash)) {
                $this->conn->execute("DELETE FROM users WHERE id = ? LIMIT 1", [$user_id]);
            }
        }
        return !$this->userExists($username);
    }
}