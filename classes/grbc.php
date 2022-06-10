<?

class GrBC {
    function __construct(){
        global $session;

        $this->db = $session->db;
        $this->hasher = $session->db->hasher;
        $this->auth = $session->authenticationService;
        $this->user = $session->authenticationService->user;
    }

    function downloadDBTables($params) {
        $binPath = 'mysqldump';

        global $dbSettings;
        $mysqldumpArgs = ' --user='.$dbSettings->username;
        $mysqldumpArgs.= ' --password='.$dbSettings->password;
        $mysqldumpArgs.= ' --host='.$dbSettings->host;
        $mysqldumpArgs.= ' '.$dbSettings->db;
        if(isset($params->tableNames) && is_array(($params->tableNames))) {
            $mysqldumpArgs.= ' '.implode(' ', $params->tableNames);
        }
        $dump = shell_exec($binPath.$mysqldumpArgs);
        header('Content-disposition: attachment;filename="'.date('Ymd').'_'.$dbSettings->db.(isset($params->tableNames[0]) ? '.'.$params->tableNames[0] : '').'.sql"');
        header('Content-type: application/octet-stream');
        dbg($dump);
    }

    function getDBMeta() {
        global $dbSettings;

        $return = [
            "dbSettings" => $dbSettings
        ];

        $return["tables"] = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'TABLE_NAME name',
                'UPDATE_TIME dateTime',
                '(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 size',
                'TABLE_ROWS noRows'
            ],
            'table' => 'information_schema.TABLES',
            'where' => ['TABLE_SCHEMA = ? AND (LEFT(TABLE_NAME, 6) = ? OR LEFT(TABLE_NAME, 8) = "library_")', [
                $dbSettings->db,
                $dbSettings->tablePrefix
            ]],
            'order' => 'size DESC'
        ]);
        $return["tables"] = JSFDates($return["tables"], 'dateTime');

        return new AjaxResponse($return);
    }

    function getVersion() {
        global $prod;
        if($prod) {
            if(file_exists('../joos/.version')) {
                $environment = file_get_contents('../joos/.version');
                $vStart = mb_strpos($environment, 'version: \'') + 10;
                $vEnd = mb_strpos($environment, '\'', $vStart + 1);
                $v = mb_substr($environment, $vStart, $vEnd - $vStart);
                return new AjaxResponse([
                    "version" => $v
                ]);
            } else {
                return new AjaxError('File .version not found');
            }
        } else {
            return new AjaxResponse([
                "version" => 'dev'
            ]);
        }
    }

    function logClientError($params) {
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_errors',
            'columns' => ['source', 'userId', 'message', 'uri'],
            'values' => ['client', isset($this->user->id) ? $this->user->id : null, isset($params->message) ? $params->message : null, $params->uri]
        ]);

        return new AjaxResponse($return);
    }

    function visitorLogin($params){
        global $session;
        return $session->authenticationService->authenticateLogin($params);
    }

    function authenticateToken($params){
        global $session;
        return $session->authenticationService->authenticateToken($params);
    }

    function setLanguage($params){
        if (isset($this->user->id)) {
            $return = $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_users',
                'columns' => 'language',
                'values' => $params->language,
                'where' => ['id = ?', $this->user->id]
            ]);
            return new AjaxResponse($return);
        } else {
            return new AjaxResponse(true);
        }
    }

    function getUsersData(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id userId',
                'firstName',
                'lastName',
                'email',
                'admin_roles.id roleId',
                'admin_roles.name roleName',
                'admin_programs.id forProgramId',
                'admin_programs.program_name_en',
                'admin_programs.program_name_gr',
                'date_time',
                'photoURI'
            ],
            'table' => 'admin_users',
            'joins' => [
                'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId',
                'LEFT JOIN admin_programs ON admin_programs.id = admin_user_roles.forProgramId'
            ],
            'order' => 'date_time DESC'
        ]);
        $return = JSFDates($return, 'date_time');

        $return = $this->db->groupResults($return, 'userId', ['roles', ['roleName', 'roleId', 'forProgramId', 'program_name_en', 'program_name_gr']]);

        return new AjaxResponse($return);
    }

    function getLogs(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_logs.id',
                'admin_logs.user_id',
                'admin_users.firstName',
                'admin_users.lastName',
                'SUBSTR(post_data, 6, LOCATE(\',"p\', post_data) - 6) q',
                'admin_logs.post_data',
                'admin_logs.date_time'
            ],
            'table' => 'admin_logs',
            'joins' => 'JOIN admin_users ON user_id = admin_users.id',
            'where' => 'admin_logs.date_time >= DATE(NOW() - INTERVAL 1 DAY)',
            'order' => 'user_id = 1, user_id'
        ]);

        return new AjaxResponse($return);
    }

    function clearLogs(){
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_logs'
        ]);

        return new AjaxResponse($return);
    }

    function getOpenSystemErrors(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_errors.id',
                'source',
                'firstName',
                'lastName',
                'userId',
                'message',
                'uri',
                'file',
                'line',
                'dateTime',
                'open'
            ],
            'table' => 'admin_errors',
            'joins' => 'LEFT JOIN admin_users ON admin_errors.userId = admin_users.id',
            'where' => 'open = TRUE',
            'order' => 'dateTime DESC'
        ]);
        
        return new AjaxResponse($return);
    }

    function clearErrors(){
        $result = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_errors',
            'columns' => 'open',
            'values' => 0
        ]);

        return new AjaxResponse($result);
    }

    function getVariablesData() {
        $data = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => ['name', 'type', 'value', 'dateTime'],
            'table' => 'admin_variables',
            'order' => 'name'
        ]);
        $data = JSFDates($data, 'dateTime');

        return new AjaxResponse($data);
    }

    function updateVariable($params) {
        $variable = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'type',
            'table' => 'admin_variables',
            'where' => ['name = ?', $params->name]
        ]);
        if($variable) {
            if($variable[0]->type === 'number') {
                $params->value = floatval(filter_var($params->value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            }
        }

        $result = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_variables',
            'columns' => 'value',
            'values' => $params->value,
            'where' => ['name = ?', $params->name]
        ]);

        return new AjaxResponse($params->value);
    }

    function addUser($params){
        $params->firstName = ucwords(strtolower(trim($params->firstName)));
        $params->lastName = ucwords(strtolower(trim($params->lastName)));
        $params->email = strtolower(trim($params->email));
        
        try {
            $authenticationToken = isset($params->authenticationToken) ?
                                         $params->authenticationToken :
                                         $this->hasher->HashPassword(json_encode($params));
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_users',
                'columns' => 'firstName, lastName, email, language, authentication_token, date_time',
                'values' => [
                    $params->firstName,
                    $params->lastName,
                    $params->email,
                    isset($params->language) ? $params->language : 'en',
                    $authenticationToken,
                    date("Y-m-d H:i:s")
                ]
            ]);
            $userId = $return->lastInsertId;

            if (!isset($params->roleId)) {
                $return = $this->db->sql1([
                    'statement' => 'SELECT',
                    'columns' => 'id',
                    'table' => 'admin_roles',
                    'where' => ['name = ?', $params->roleName]
                ]);
                $params->roleId = $return->id;
            }
            $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_user_roles',
                'columns' => 'userId, roleId, forProgramId',
                'values'=> [
                    $userId,
                    $params->roleId,
                    isset($params->forProgramId) ?
                        $params->forProgramId :
                        null
                ]
            ]);
        } catch(Exception $e){
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        try {
            (new Moodle())->moodleCreateUser(
                $params->firstName,
                $params->lastName,
                $params->email
            );
        } catch(Exception $e){
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        return new AjaxResponse([
            "userId" => $userId,
            "firstName" => $params->firstName,
            "lastName" => $params->lastName,
            "email" => $params->email,
            "roles" => [[
                "roleName" => $params->roleName,
                "roleId" => $params->roleId,
                "roleForProgramId" => isset($params->forProgramId) ? $params->forProgramId : null
            ]],
            "authentication_token" => $authenticationToken,
            "date_time" => date("Y-m-d H:i:s")
        ]);
    }

    function deleteUserPermanently($params){
        // Get User Roles
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'email, admin_roles.name roleName',
            'table' => 'admin_users',
            'joins' => [
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId'
            ],
            'where' => ['admin_users.id = ?', $params->id]
        ]);
        $return = $this->db->groupResults($return, 'id', ['roles', 'roleName']);

        if (isset($return[0])) {
            // If User is an Applicant call resetApplicant()
            if (in_array('candidate', $return[0]->roles)) {
                $params->userId = $params->id;
                (new Admissions())->resetApplicant($params);
            }
            // If User is a Student call deleteStudent()
            if (in_array('student', $return[0]->roles)) {
                $params->studentId = $params->id;
                (new AcademicsService())->deleteStudent($params);
            }
            // Delete User Finances
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_finances_records',
                'where' => ['userId = ?', $params->id]
            ]);

            // Delete User Files
            $files = $this->db->sql([
                'statement' => 'SELECT',
                'columns' => 'filename',
                'table' => 'admin_files',
                'where' => ['owner_id = ?', $params->id]
            ]);
            $fs = new FileSystem();
            foreach ($files as $file) {
                $fs->deleteFile($file);
            }
            // Delete User Logs
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_logs',
                'where' => ['user_id = ?', $params->id]
            ]);

            // Delete User Profile
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_user_profiles',
                'where' => ['id = ?', $params->id]
            ]);

            // Delete Moodle User
            (new Moodle())->moodleDeleteUser($return[0]->email);
            // Delete User
            $return = $this->db->sql([
                'statement' => 'DELETE a, b FROM admin_users a LEFT JOIN admin_user_roles b ON a.id = b.userId',
                'where' => ['id = ?', $params->id]
            ]);

            return new AjaxResponse([
                "id" => $params->id
            ]);
        } else {
            return new AjaxError(__METHOD__.': User not found: '.$params->id);
        }
    }

    function addProgramForRole($params){
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_user_roles',
            'columns' => 'forProgramId',
            'values' => $params->forProgramId,
            'where' => ['userId = ? AND roleId = ? AND forProgramId IS NULL', [
                $params->userId,
                $params->roleId
            ]]
        ]);

        return new AjaxResponse($return);
    }

    function addRoleToUser($params){
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_user_roles',
            'columns' => [
                'userId',
                'roleId',
            ],
            'values' => [
                $params->userId,
                $params->roleId
            ],
            'update' => true
        ]);

        return new AjaxResponse($return);
    }

    function removeRoleFromUser($params){
        $whereString = 'userId = ? AND roleId = ? AND forProgramId ';
        $whereParams = [
            $params->userId,
            $params->roleId
        ];
        if (isset($params->forProgramId)) {
            $whereString.= '= ?';
            array_push($whereParams, $params->forProgramId);
        } else {
            $whereString.= 'IS NULL';
        }
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_user_roles',
            'where' => [$whereString, $whereParams]
        ]);

        return new AjaxResponse($return);
    }

    function getUserData($params){
        $columns = [
            'admin_users.id',
            'firstName',
            'lastName',
            'email',
            'admin_roles.name roleName',
            'date_time'
        ];
        if ($this->auth->authenticateOperation('getUserDataIncludeApplications')) {
            array_push($columns, 'noUserApplications');
        }
        if ($this->auth->authenticateOperation('getUserDataIncludeFiles')) {
            array_push($columns, 'noUserFiles');
        }
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => $columns,
            'table' => 'admin_users',
            'joins' => [
                'LEFT JOIN (SELECT COUNT(userId) noUserApplications, userId FROM admin_user_applications WHERE userId = ? GROUP BY userId) AS admin_user_applications_filtered ON userId = id',
                'LEFT JOIN (SELECT COUNT(owner_id) noUserFiles, owner_id FROM admin_files WHERE owner_id = ? GROUP BY owner_id) AS admin_files_filtered ON owner_id = id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId'
            ],
            'where' => ['admin_users.id = ?', [
                $params->id,
                $params->id,
                $params->id
            ]],
        ]);

        return new AjaxResponse(isset($return[0]) ? $return[0] : false);
    }

    function getUserFiles($params){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'owner_id',
                'filename',
                'filesize',
                'original_filename',
                'original_mime_type',
                'date_time'
            ],
            'table' => 'admin_files',
            'where' => ['owner_id = ?', $params->id],
        ]);

        return new AjaxResponse($return);
    }

    function importUserDataFromApplication($params){
        (new Admissions())->copyUserProfileFromApplication($params->id);
        return $this->getUserData($params);
    }    

    function saveUserProfileData($params){
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_user_profiles',
            'columns' => [
                'id',
                'birthDate',
                'phone',
                'greekCitizen',
                'greekIdNumber',
                'greekSsn',
                'irsOffice',
                'citizenship',
                'euCitizen',
                'passportNumber',
                'residencePermit',
                'address',
                'city',
                'zipCode',
                'country'
            ],
            'values' => [
                $params->id,
                $params->birthDate,
                $params->phone,
                $params->greekCitizen,
                $params->greekIdNumber,
                $params->greekSsn,
                $params->irsOffice,
                $params->citizenship,
                $params->euCitizen,
                $params->passportNumber,
                $params->residencePermit,
                $params->address,
                $params->city,
                $params->zipCode,
                $params->country,
            ],
            'update' => true
        ]);

        return new AjaxResponse($params);
    }

    function updateProfilePicture($params){
        $sql = "REPLACE INTO admin_user_profiles (id, photoURI) SELECT owner_id, filename FROM admin_files WHERE filename = ?;";
        $replaceStatement = $this->db->dbObject->prepare($sql);
        $replaceStatement->execute([$params->filename]);

        return new AjaxResponse([
            "filename" => $params->filename
        ]);
    }

    function resetUserPasswordInit($params){
        if(isset($params->id)){
            $return = $this->db->sql1([
                'statement' => 'SELECT',
                'columns' => 'email, language, authentication_token',
                'table' => 'admin_users',
                'where' => ['id = ?', $params->id],
            ]);
        } elseif(isset($params->email)){
            $return = $this->db->sql1([
                'statement' => 'SELECT',
                'columns' => 'email, language, authentication_token',
                'table' => 'admin_users',
                'where' => ['email = ?', $params->email],
            ]);
        }

        if($return) {
            // User was found
            (new NotificationService())->send([
                "toEmails" => $return->email,
                "templateName" => 'userPasswordReset',
                "language" => $return->language,
                "vars" => [['authenticationToken', rawurlencode($return->authentication_token)]]
            ]);
            return new AjaxResponse(true);
        } else {
            // User was not found
            return new AjaxResponse(false);
        }
        
    }

    function resetUserPassword($params){
        $newPassword = $this->hasher->HashPassword($params->newPassword);
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_users',
            'columns' => ['password', 'authentication_token'],
            'values' => [$newPassword, $newPassword],
            'where' => ['authentication_token = ?', $params->authenticationToken]
        ]);

        try {
            $user = $this->db->sql([
                'statement' => 'SELECT',
                'columns' => 'email, language',
                'table' => 'admin_users',
                'where' => ['authentication_token = ?', $params->authenticationToken],
            ]);

            if (isset($user[0])) {
                (new Moodle())->moodleResetPassword(
                    $user[0]->email,
                    $params->newPassword
                );
            }
        } catch(Exception $e){
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        if($return->rowCount){
            return new AjaxResponse($return);
        } else {
            return new AjaxResponse(null,false,'Password Reset Failed');
        }
    }

    function getAuthenticationToken($params){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'authentication_token',
            'table' => 'admin_users',
            'where' => ['id = ?', $params->id],
        ]);

        return new AjaxResponse(isset($return[0]) ? $return[0] : false);
    }
  
    function registerCandidate($params){
        // Validate reCAPTCHA Token
        try {
            $result = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . GRECAPTCHA_SECRET . "&response=" . $params->recaptchaToken, false, stream_context_create(["ssl" => array("verify_peer" => false, "verify_peer_name" => false)])));
        } catch(Exception $e){
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        // If you need to skip ReCaptcha check then uncomment the following line
        // $result = (object) ["success"=>true];
        // Make sure the line above is commented out in production

        if($result->success){
            $params->authenticationToken = $this->hasher->HashPassword(json_encode($params));
            $params->roleName = 'candidate';
            $params->roleId = 5;
            $return = $this->addUser($params);

            try {
                // Send Activation Email to Candidate
                (new NotificationService())->send([
                    "toEmails" => $params->email,
                    "templateName" => 'applicantActivation',
                    "language" => $params->language,
                    "vars" => [["authenticationToken", rawurlencode($params->authenticationToken)]]
                ]);
            } catch(Exception $e){
                return new AjaxError(__METHOD__.': '.$e->getMessage());
            }

            // Create Moodle user with the same username (email address)
            try {
                (new Moodle())->moodleCreateUser(
                    $params->firstName,
                    $params->lastName,
                    $params->email
                );
            } catch(Exception $e){
                return new AjaxError(__METHOD__.': '.$e->getMessage());
            }
        } else {
            // ReCaptcha server side check failed
            return new AjaxResponse(-1);
        }

        return new AjaxResponse(isset($return->data) ? $return->data["userId"] : null);
    }

    function getAdvisors() {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users_advisors.id advisorId',
                'admin_users_advisors.firstName advisorFirstName',
                'admin_users_advisors.lastName advisorLastName',
                'admin_user_profiles_advisors.photoURI advisorPhotoURI',
                'admin_users_advisees.id adviseeId',
                'admin_users_advisees.firstName adviseeFirstName',
                'admin_users_advisees.lastName adviseeLastName',
                'admin_user_profiles_advisees.photoURI adviseePhotoURI'
            ],
            'table' => 'admin_user_roles',
            'joins' => [
                'LEFT JOIN admin_advisors ON admin_advisors.advisorId = admin_user_roles.userId',
                'LEFT JOIN admin_users admin_users_advisors ON admin_users_advisors.id = admin_user_roles.userId',
                'LEFT JOIN admin_user_profiles admin_user_profiles_advisors ON admin_user_profiles_advisors.id = admin_users_advisors.id',
                'LEFT JOIN admin_users admin_users_advisees ON admin_users_advisees.id = admin_advisors.userId',
                'LEFT JOIN admin_user_profiles admin_user_profiles_advisees ON admin_user_profiles_advisees.id = admin_users_advisees.id'
            ],
            'where' => 'roleId = 11',
            'order' => 'advisorId'
        ]);
        $return = $this->db->groupResults($return, 'advisorId', ['advisees', ['adviseeId', 'adviseeFirstName', 'adviseeLastName', 'adviseePhotoURI']]);
        
        return new AjaxResponse($return);
    }

    function getNonAdvisors() {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'userId advisorId',
                'firstName advisorFirstName',
                'lastName advisorLastName',
                'photoURI advisorPhotoURI'
            ],
            'table' => 'admin_user_roles',
            'joins' => [
                'LEFT JOIN admin_users ON admin_users.id = admin_user_roles.userId',
                'LEFT JOIN admin_user_profiles ON admin_user_profiles.id = admin_users.id'
            ],
            'where' => 'roleId = 7 AND userId NOT IN (SELECT userId FROM admin_user_roles WHERE roleId = 11)',
            'group' => 'userId',
            'order' => 'lastName, firstName'
        ]);

        return new AjaxResponse($return);
    }

    function addAdvisor($params) {
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_user_roles',
            'columns' => 'userId, roleId',
            'values' => [$params->advisorId, 11]
        ]);

        return new AjaxResponse($return);
    }

    function removeAdvisor($params) {
        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_advisors',
            'where' => ['advisorId = ?', $params->advisorId]
        ]);

        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_user_roles',
            'where' => ['userId = ? AND roleId = 11', $params->advisorId]
        ]);

        return new AjaxResponse(true);
    }

    function getNonAdvisees() {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'userId adviseeId',
                'firstName adviseeFirstName',
                'lastName adviseeLastName',
                'photoURI adviseePhotoURI'
            ],
            'table' => 'admin_program_enrollment',
            'joins' => [
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_program_enrollment.student_id',
                'LEFT JOIN admin_users ON admin_users.id = admin_program_enrollment.student_id',
                'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id'
            ],
            'where' => 'active AND roleId = 6 AND userId NOT IN (SELECT userId FROM admin_advisors)',
            'group' => 'adviseeId',
            'order' => 'lastName, firstName'
        ]);

        return new AjaxResponse($return);
    }

    function addAdvisee($params) {
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_advisors',
            'columns' => 'userId, advisorId',
            'values' => [$params->adviseeId, $params->advisorId],
            'update' => true
        ]);

        return new AjaxResponse($return);
    }

    function removeAdvisee($params) {
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_advisors',
            'where' => ['userId = ?', $params->adviseeId]
        ]);

        return new AjaxResponse($return);
    }

    function runExecutiveScript($params) {
        // Use this method if you need to run custom one-time scripts on the server
    }
}

class UrlGenerator {
    function __construct(){
        if(isset($_POST["r"])){
            $this->r = json_decode($_POST["r"]);
        } else {
            $this->r = json_decode($_GET["r"]);
        }
    }

    function getURL($q, $p = []){
        $p = (object) $p;
        $this->newR = new stdClass();
        $this->newR->t = $this->r->t;
        $this->newR->q = $q;
        $this->newR->p = $p;
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://".$_SERVER["HTTP_HOST"].explode("?",$_SERVER["REQUEST_URI"])[0]."?r=".rawurlencode(json_encode($this->newR));
    }
}
