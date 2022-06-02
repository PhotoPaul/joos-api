<?
// Declare this API as an Ajax capable Resource
// header("Access-Control-Allow-Origin: *");
if(isset($_SERVER['HTTP_ORIGIN'])){
    header("Access-Control-Allow-Origin: ".$_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

$session;

// Set Custom Parse and Fatal Error Handler
register_shutdown_function('shutdownFunction');

// Load Settings
include 'settings.php';

// Load Handler
include_once 'handler.php';

function customErrorHandler($number, $string, $file, $line) {
    // Ignore "errors" from the following third-party libraries
    $file = str_replace('\\', '/', $file);
    $filename = explode('/', $file);
    $filename = $filename[count($filename) - 1];
    if($filename === 'uploadhandler.php' || $filename === 'simpleimage.php') {
        return true;
    }
    // Ignore imagecreatefromjpeg(): gd-jpeg, libjpeg: recoverable error
    if (substr($string, 0, 58) === 'imagecreatefromjpeg(): gd-jpeg, libjpeg: recoverable error') {
        return true;
    }
    global $session;
    if(!isset($session)){
        global $dbSettings;
        include_once 'classes/dbcontroller.php';
        $session = (object) [
            "db" => new DBController($dbSettings)
        ];
    }
    global $ajax;
    $uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" . ((isset($ajax) && $ajax->method) === 'POST' ? '?r='.rawurlencode($ajax->args) : '');

    $return = $session->db->sql([
        'statement' => 'INSERT INTO',
        'table' => 'admin_errors',
        'columns' => 'source, userId, message, uri, file, line',
        'values' => [
            'php',
            isset($session->authenticationService->user->id) ?
                $session->authenticationService->user->id : null,
            $string,
            $uri,
            $file,
            $line
        ]
    ]);

    echo json_encode(new AjaxResponse((object) [
        "source" => 'php',
        "code" => 'CUSTOM_ERROR_'.$number,
        "message" => $string,
        "uri" => $uri,
        "file" => $file,
        "line" => $line
    ], false, $string));
}

function shutdownFunction() {
    if ($error = error_get_last()){
        customErrorHandler($error["type"], error_type($error["type"]).': '.$error["message"], $error["file"], $error["line"]);
    }
}

function error_type($id) {
    switch($id) {
        case E_ERROR:// 1
            return 'E_ERROR';
        case E_WARNING:// 2
            return 'E_WARNING';
        case E_PARSE:// 4
            return 'E_PARSE';
        case E_NOTICE:// 8
            return 'E_NOTICE';
        case E_CORE_ERROR:// 16
            return 'E_CORE_ERROR';
        case E_CORE_WARNING:// 32
            return 'E_CORE_WARNING';
        case E_COMPILE_ERROR:// 64
            return 'E_COMPILE_ERROR';
        case E_COMPILE_WARNING:// 128
            return 'E_COMPILE_WARNING';
        case E_USER_ERROR:// 256
            return 'E_USER_ERROR';
        case E_USER_WARNING:// 512
            return 'E_USER_WARNING';
        case E_USER_NOTICE:// 1024
            return 'E_USER_NOTICE';
        case E_STRICT:// 2048
            return 'E_STRICT';
        case E_RECOVERABLE_ERROR:// 4096
            return 'E_RECOVERABLE_ERROR';
        case E_DEPRECATED:// 8192
            return 'E_DEPRECATED';
        case E_USER_DEPRECATED:// 16384
            return 'E_USER_DEPRECATED';
    }
    return 'UNKNOWN';
}