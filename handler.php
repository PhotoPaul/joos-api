<?
// Set Custom Error & Exception Handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Initialize API
$ajax = new Ajax();

// Interpret the API request
$ajax->interpretAjax(
    [   // Public Classes
        'OperationsManager',
        'GrBC',
        'NotificationService',
        'FileSystem',
        'Home',
        'AcademicsService',
        'Applications',
        'Admissions',
        'AcademicProgress',
        'FormsService',
        'FinancesService',
        'Transcript',
        'Schedule',
        'LibraryService',
        'Viva',
        'Moodle'
    ]
);

// Log the API request
$ajax->logRequest();

class Ajax {
    function __construct(){
        if(count($_GET) >= count($_POST)){
            $this->method = 'GET';
            $this->r = $this->getArgs("GET");
        } else {
            $this->method = 'POST';
            $this->r = $this->getArgs("POST");
        }

        global $session;
        if(isset($this->r->t)){
            $session = new Session($this->r->t);
            $this->session = $session;
        } else {
            $session = new Session();
            $this->session = $session;
        }

// ---------------------------------------------
// include_once 'exporter.php';
// export($this->session);
// ---------------------------------------------

    }

    public function getArgs($method = "GET"){
        $this->args = null;
        if($method == "GET"){
            if(isset($_GET["r"])) $this->args = $_GET["r"];
        } else {
            if(isset($_POST["r"])) $this->args = $_POST["r"];
        }

        if(isset($this->args)) {
            // JSON decode the request
            $jsonDecodedRequest = json_decode($this->args);

            // Check and report JSON decoding errors
            if($jsonDecodedRequest === null) {
                interpretJSONLastError(json_last_error());
            }
            // Return the decoded JSON
            return $jsonDecodedRequest;
        }
    }

    public function interpretAjax($classes){
        if(is_object($this->r)){
            if($this->r){
                // $this->r->q needs to be an Array so that it can support multiple queries coming from a single Ajax request
                if(!is_array($this->r->q)){
                    $this->r->q = [$this->r->q];
                }
                // $response is an Array so that it can hold multiple results of multiple queries
                $response = [];
                // Iterate queries
                foreach($this->r->q as $q){
                    $methodFound = false;
                    // Iterate known $classes to find the one that has the method requested by the query
                    foreach($classes as $className) {
                        $class = new $className($this->session);
                        if (method_exists($className, $q)) {
                            $methodFound = true;
                            // Check if Access Control allows for User to call the method
                            if ($this->session->authenticationService->authenticateOperation($q)) {
                                // User has Access to this method
                                array_push($response, $class->{$q}(isset($this->r->p) ? $this->r->p : null));
                            } else {
                                // User does not have access to this method
                                throw new Exception(__METHOD__.': Access Denied: <font style="color: red">'.$q.'</font>');
                            }
                            break;
                        }
                    }
                    if(!$methodFound){
                        throw(new Exception(__METHOD__.': Unknown API Method: <font style="color: red">'.$q.'</font>'));
                        // array_push($response, new AjaxError(__METHOD__.': Unknown API Method: <font style="color: red">'.$q.'</font>'));
                    }
                }
                if(!empty($response)) {
                    if (count($response) > 1) {
                        $jsonEncodedResponse = json_encode($response);
                    } else {
                        $jsonEncodedResponse = json_encode($response[0]);
                    }
                    if ($jsonEncodedResponse === false) {
                        echo json_encode(new AjaxError(interpretJSONLastError(json_last_error())));
                    } else {
                        echo $jsonEncodedResponse;
                    }
                }
            }
        }
    }

    public function logRequest(){
        if(
            isset($this->r) &&
            $this->r->q[0] !== "getImage" &&
            $this->r->q[0] !== "getNotificationsLength" &&
            $this->r->q[0] !== "getNotifications"
        ){
            $userId = isset($this->session->authenticationService->user->id)? $this->session->authenticationService->user->id: null;
            $return = $this->session->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_logs',
                'columns' => 'user_id, post_data',
                'values' => [
                    $userId,
                    $this->args
                ]
            ]);
        }
    }
}

class AjaxResponse {
    function __construct($data = null, $success = true, $reason = null) {
        $this->data = $data;
        $this->success = $success;
        if(!$success){
            $this->errorReason = $reason;
        }
    }
}

class AjaxError {
    function __construct($errorReason = 'Unknown Error', $errorData = null) {
        global $session;
        $this->errorReason = $errorReason;
        if($errorData !== null) {
            $this->errorData = $errorData;
        }

        if($_SERVER['SERVER_NAME'] === 'www.grbc.gr') {
            (new NotificationService())->send([
                "toEmails" => DEV_EMAIL,
                "subject" => '⚠️ JoOS Error Report',
                "message" => '<b>'.$errorReason.'</b><pre>'.print_r($errorData, true).'</pre><pre>'.print_r($session->authenticationService->user, true).'</pre>'
            ]);
        }
    }
}
    
function customExceptionHandler($e) {
    global $session;
    if(!isset($session)){
        global $dbSettings;
        include_once 'classes/dbcontroller.php';
        $session = (object) [
            "db" => new DBController($dbSettings)
        ];
    }
    $uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $return = $session->db->sql([
        'statement' => 'INSERT INTO',
        'table' => 'admin_errors',
        'columns' => 'source, userId, message, uri, file, line',
        'values' => [
            'api',
            isset($session->authenticationService->user->id) ?
                $session->authenticationService->user->id : null,
            $e->getMessage(),
            $uri,
            $e->getFile(),
            $e->getLine()
        ]
    ]);

    echo json_encode(new AjaxResponse((object) [
        "source" => 'api',
        "code" => $e->getCode(),
        "message" => $e->getMessage(),
        "uri" => $uri,
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ], false, $e->getMessage()));
}

function interpretJSONLastError ($error) {
    switch ($error) {
        case JSON_ERROR_DEPTH:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_DEPTH</font>';
        case JSON_ERROR_STATE_MISMATCH:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_STATE_MISMATCH</font>';
        case JSON_ERROR_CTRL_CHAR:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_CTRL_CHAR</font>';
        case JSON_ERROR_SYNTAX:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_SYNTAX</font>';
        case JSON_ERROR_UTF8:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_UTF8</font>';
        case JSON_ERROR_RECURSION:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_RECURSION</font>';
        case JSON_ERROR_INF_OR_NAN:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_INF_OR_NAN</font>';
        case JSON_ERROR_UNSUPPORTED_TYPE:
            return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_UNSUPPORTED_TYPE</font>';
        // Available since PHP 7.0.0
        // case JSON_ERROR_INVALID_PROPERTY_NAME:
        //     return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_INVALID_PROPERTY_NAME</font>';
        // Available since PHP 7.0.0
        // case JSON_ERROR_UTF16:
        //     return __METHOD__.': Malformed Request: <font style="color: red">JSON_ERROR_UTF16</font>';
        default:
            return __METHOD__.': Malformed Request: <font style="color: red">Unknown error</font>';
    }
}

function dbg($p, $die = true){
    if(is_array($p) || is_object($p)){
        print_r($p);
    } else {
        echo($p);
    }
    if($die){
        die();
    }
}

function booleanize($mySQLTinyIntValue){
    if($mySQLTinyIntValue === "1"){
        return true;
    } elseif($mySQLTinyIntValue === "0"){
        return false;
    } else {
        return $mySQLTinyIntValue;
    }
}

function JSFDates($rows, $columnName) {
    $returnObject = false;
    if(!is_array($rows)) {
        $returnObject = true;
        $rows = [$rows];
    }
    foreach($rows as $i => $row) {
        $rows[$i]->{$columnName} = $rows[$i]->{$columnName} === null ? null : str_replace('-', '/', $rows[$i]->{$columnName});
    }
    return $returnObject ? $rows[0] : $rows;
}

function fDate($date = null, $format = '%d %b. %Y', $locale = 'greek') {
    if(!isset($date)){
        $date = date('Y-m-d');
    }
    setlocale(LC_CTYPE, $locale);
    setlocale(LC_TIME, $locale);

    $return = iconv('Windows-1253', 'UTF-8', ltrim(strftime($format, strtotime($date)), '0'));
    // This only works locally
    $return = str_replace('Μαϊ', 'Μαΐ', $return);
    // This only works online
	$return = str_replace('Μάι', 'Μαΐ', $return);
	return $return;
}
