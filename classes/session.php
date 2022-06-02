<?

class Session {
    function __construct($authenticationToken = null){
        // Start session
        session_start();

        // Load the DBController
        global $dbSettings;
        $this->db = new DBController($dbSettings);

        // Load the AuthenticationService
        $this->authenticationService = new AuthenticationService($this->db,$authenticationToken);
    }
}