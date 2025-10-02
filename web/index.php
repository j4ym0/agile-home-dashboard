<?php
// Include required files
require_once 'core/config.php';
require_once 'core/text.php';
require_once 'core/core.php';
require_once 'core/database.php';
require_once 'core/auth.php';
require_once 'core/settings.php';
require_once 'core/octopus.php';
require_once 'core/tuya.php';
require_once 'core/engine.php';

// Init the config
Config::init();

// Get the requested endpoint
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'home'; 
$returnPath = $endpoint; 

// Create database connection
$db = new Database();

// Create database connection
$settings = new Settings($db);

// Load Auth System
$auth = new AuthSystem($db);

// Check if js file is wanted and return a min version
if (strpos($endpoint, 'js/') === 0) {
    $base = __DIR__ . '/template/';
    $fullPath = realpath($base . ltrim($endpoint, '/\\'));
    if ($fullPath && 
        strpos($fullPath, $base) === 0 && 
        is_file($fullPath) && 
        is_readable($fullPath)
    ){
        header('Content-Type: application/javascript');
        // check and skip min files
        if (strcmp(substr($fullPath, -3), ".js") === 0 &&
            substr($fullPath, -7) !== '.min.js'
        ){
            $miniJs = minifyJsContent($fullPath);
            // Output directly with proper headers for JavaScript
            echo $miniJs;
        }else{
            readfile($fullPath);
        }
    }
    exit;
}
// Check if endpoint starts with 'api/'
if (strpos($endpoint, 'api/') === 0) {
    // Remove the 'api/' prefix
    $path = substr($endpoint, 4);

    // Split the remaining path by '/'
    $parts = explode('/', $path);
    
    // Check if we have at least 2 parts (filename and function)
    if (count($parts) < 2) {
        die('Invalid API format');
    }
    
    $filename = $parts[0];
    $endFunction = $parts[1];

    // Validate filename (alphanumeric and underscores)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $filename)) {
        die('Invalid API endpoint');
    }
    if (!file_exists(__DIR__ . "/api/$filename.php")) {
        die('Invalid API endpoint');
    }
    
    // Validate function name (alphanumeric and underscores)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $endFunction)) {
        die('Invalid API endpoint');
    }

    include __DIR__ . "/api/$filename.php";
    exit;
}

// Check if user is logged in
if (!$auth->is_logged_in()) {
    if ($endpoint != 'login'){
        header("Location: /login?return=" . urlencode("/$endpoint"));
        exit();
    }
    $endpoint = 'login';
}else{
    // If logged in and requesting the login screen redirect to home
    if ($endpoint == 'login'){
        header("Location: /home");
        exit();
    }
}

// Create template engine instance
$template = new TemplateEngine(__DIR__ . '/template', $db);

// Add url vars to template
foreach ($_GET as $key => $value) {
    $template->assign($key, $value);
}
// Assign data to template
$template->assign('page_title', 'Agile Home Dashboard');
$template->assign('endpoint', $endpoint);
$template->assign('SECURE_LOGIN', Config::get('SECURE_LOGIN'));
$template->assign('APP_NAME', $APP_NAME);
$template->assign('APP_VERSION', $APP_VERSION);
$template->assign('relLinks', [['rel' => 'stylesheet', 'href' => '/template/css/css.css']]);
$template->assign('tuya_configured', $settings->get('tuya_configured', false));

// Run controller script
try {
    $base = __DIR__ . '/controller/';
    $fullPath = realpath($base . ltrim($endpoint . '.php', '/\\'));
    
    // Validate:
    // 1. Path resolution succeeded
    // 2. Path is within base directory
    // 3. File exists and is readable
    if ($fullPath && 
        strpos($fullPath, $base) === 0 && 
        is_file($fullPath) && 
        is_readable($fullPath)
    ) {
        include $fullPath;
    } else {
        echo $fullPath;
        throw new RuntimeException("Invalid file path");
    }
} catch (RuntimeException $e) {
    echo $e;
    // Handle errors gracefully
    header("HTTP/1.1 500 Internal Server Error");
    include __DIR__ . '/errors/500.php'; // TODO: create
}

// Render individual components
$sidebar = $endpoint != 'login' ? $template->render('sidebar.html') : '';
$mainContent = $template->render($endpoint.'.html');

// Render the full page using a layout
$fullPage = $template->renderLayout('layout.html', $mainContent, [
    'sidebar' => $sidebar
]);

// Output the final page
echo $fullPage;

// Close database connection
$db->close();
?>