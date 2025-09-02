<?php
// Include required files
require_once 'core/config.php';
require_once 'core/text.php';
require_once 'core/core.php';
require_once 'core/database.php';
require_once 'core/auth.php';
require_once 'core/settings.php';
require_once 'core/octopus.php';
require_once 'core/engine.php';

// Init the config
Config::init();

// Get the requested endpoint
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'home'; 
$returnPath = $endpoint; 

// Create database connection
$db = new Database(Config::getInstance());

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
// Check if endpoint starts with 'save/'
if (strpos($endpoint, 'save/') === 0) {
    include __DIR__ . '/api/save.php';
    exit;
}
// Check if endpoint starts with 'get/'
if (strpos($endpoint, 'get/') === 0) {
    include __DIR__ . '/api/get.php';
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