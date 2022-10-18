<?
// Define Dev's email address for error reporting
define("DEV_EMAIL", "***DEV_EMAIL***");

// Define Gmail server settings
define("MAIL_SERVER_USERNAME", "***MAIL_SERVER_USERNAME***");
define("MAIL_SERVER_PASSWORD", "***MAIL_SERVER_PASSWORD***");

// ** MySQL settings - You can get this info from your web host ** //
// The name of the database for JoOS
define('DB_NAME', 'grbcgr_main');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', '***DB_USER***');
define('DB_PASSWORD', '***DB_PASSWORD***');
define('DB_TIMEZONE', 'Europe/Athens');
// Hasn't been implemented yet
define('DB_TABLE_PREFIX', 'admin_');

// Define the site key and the secret used by reCAPTCHA
define('GRECAPTCHA_SECRET', '***GRECAPTCHA_SECRET***');
// Variable site key hasn't been implemented yet
define('GRECAPTCHA_SITE_KEY', '***GRECAPTCHA_SITE_KEY***');

// Define Moodle server settings
define('MODDLE_USERNAME', '***MODDLE_USERNAME***');
define('MODDLE_PASSWORD', '***MODDLE_PASSWORD***');
define('MODDLE_SERVICE', '***MODDLE_SERVICE***');
define('MODDLE_TOKEN', '***MODDLE_TOKEN***');
define('MODDLE_URL', '***MODDLE_URL***');

// Define VivaWallet (Payment Gateway) settings
define('VIVA_MERCHANT_ID', '***VIVA_MERCHANT_ID***');
define('VIVA_API_KEY', '***VIVA_API_KEY***');

// ** DO NOT CHANGE ANYTHING UNDER THIS LINE ** //
// Hide all Errors to avoid breaking Image processing
error_reporting(0);
ini_set('display_errors', 0);

// Setup Class Autoload
set_include_path(get_include_path().PATH_SEPARATOR.'classes/');
spl_autoload_extensions('.php');
spl_autoload_register();

// Set Time Zone
date_default_timezone_set(DB_TIMEZONE);

// Determine Production or Dev and set appURL and Database Connection Settings accordingly
if($_SERVER['SERVER_NAME'] === 'www.grbc.gr') {
    $prod = true;
    $appUrl = 'https://www.grbc.gr/joos';
    $dbSettings = new DBSettings(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_TABLE_PREFIX, DB_TIMEZONE);
} else {
    $prod = false;
    $appUrl = 'http://localhost:4200';
    $dbSettings = new DBSettings('joos_db', 3306, 'joos', 'joos', 'joos', 'admin_', 'Europe/Athens');
}

class DBSettings{
    public $host;
    public $port;
    public $db;
    public $username;
    public $password;
    public $tablePrefix;
    public $timezone;

    function __construct($host, $port, $db, $username, $password, $tablePrefix, $timezone){
        $this->host = $host;
        $this->port = $port;
        $this->db = $db;
        $this->username = $username;
        $this->password = $password;
        $this->tablePrefix = $tablePrefix;
        $this->timezone = $timezone;
    }
}