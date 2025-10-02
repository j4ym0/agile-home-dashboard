<?php

// Start secure session
function secure_session_start($session_name, $secureCookie, $httpOnly) {
    ini_set('session.use_only_cookies', 1);
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params(
        $cookieParams["lifetime"],
        $cookieParams["path"],
        $cookieParams["domain"],
        $secureCookie,
        $httpOnly
    );
    session_name($session_name);
    session_start();
    session_regenerate_id(true);
}

// Make a min version of a .js file
function minifyJsContent($scriptFilename) {
    $jsContent = file_get_contents($scriptFilename);
    
    // Remove comments first
    $minified = preg_replace('@/\*.*?\*/@s', '', $jsContent);
    $minified = preg_replace('@//.*$@m', '', $minified);

    // Protect ALL string literals (both single and double quotes)
    $minified = preg_replace_callback('/\'[^\']*\'/', function($matches) {
        return str_replace(' ', '%%SPACE%%', $matches[0]);
    }, $minified);
    
    $minified = preg_replace_callback('/\"[^\"]*\"/', function($matches) {
        return str_replace(' ', '%%SPACE%%', $matches[0]);
    }, $minified);

    // Now do the aggressive whitespace removal
    $minified = preg_replace('/\s+/', ' ', $minified);
    $minified = preg_replace('/\s*([{}()\[\]<>|=;:,+\-*\/%])\s*/', '$1', $minified);
    $minified = preg_replace('/;}/', '}', $minified);
    $minified = trim($minified);

    // Restore spaces in strings
    $minified = str_replace('%%SPACE%%', ' ', $minified);

    return $minified;
}
// Load a script from filename 
function includeScript(string $basePath, string $requestedPath): void {
    // Normalize paths
    $basePath = realpath(rtrim($basePath, '/')) . '/';
    $requestedPath = ltrim($requestedPath, '/');
    
    // Security checks
    if (!is_dir($basePath)) {
        throw new RuntimeException("Base directory does not exist");
    }
    
    // Default to index.php if path is empty
    if (empty($requestedPath)) {
        $requestedPath = 'index.php';
    }
    
    // Add .php extension if not present
    if (!preg_match('/\.php$/i', $requestedPath)) {
        $requestedPath .= '.php';
    }
    
    // Build full path
    $fullPath = realpath($basePath . $requestedPath);
    
    // Verify the path is within the base directory
    if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
        // Log the attempt for security monitoring
        echo $requestedPath;
        error_log("Invalid path attempt: " . $requestedPath);
        include $basePath . 'errors/404.php';
        return;
    }
    
    // Check if file exists
    if (!file_exists($fullPath)) {
        include $basePath . 'errors/404.php';
        return;
    }
    
    // Include the file
    include $fullPath;
}