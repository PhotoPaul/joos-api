<?

class AuthenticationService{
    function __construct($db, $authenticationToken){
        $this->db = $db;
        $this->user = new User(null);
        $this->authenticateRequest($authenticationToken);
    }

    function authenticateOperation($operation) { // Used to authenticate Operations (including API calls)
        $whereString = ['role = "any"'];
        $whereValues = [];
        $roles = isset($this->user->roles) ? $this->user->roles : [];
        foreach ($roles as $role) {
            array_push($whereString, 'role = ?');
            array_push($whereValues, $role->roleName);
        }
        $whereString = 'operation = ? AND ('.implode(' OR ', $whereString).')';
        array_unshift($whereValues, $operation);
        return $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'role',
            'table' => 'admin_operations',
            'where' => [$whereString, $whereValues]
        ]);
    }

    private function authenticateRequest($authenticationToken){
        if($authenticationToken){
            if(
                isset($_SESSION['authenticationToken']) &&
                $authenticationToken === $_SESSION['authenticationToken'] &&
                isset($_SESSION['user'])){
                $this->user = unserialize($_SESSION['user']);
            } else {
                $this->user = $this->authenticateToken($authenticationToken)->data;
                $_SESSION['user'] = serialize($this->user);
            };
        }
    }

    function authenticateToken($authenticationToken){
        // Get User based on the Authentication Token
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id',
                'firstName',
                'lastName',
                'email',
                'admin_roles.name roleName',
                'forProgramId',
                'language',
                'authentication_token',
                'photoURI'
            ],
            'table' => 'admin_users',
            'joins' => [
                'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId'
            ],
            'where' => ['authentication_token = ?', $authenticationToken]
        ]);
        $return = $this->db->groupResults($return, 'id', ['roles', ['roleName', 'forProgramId']]);

        if ($return){
            $_SESSION['authenticationToken'] = $return[0]->authentication_token;
            return new AjaxResponse(new User($return[0]));
        }
        return new AjaxResponse(null,false,'Token Authentication Failed');
    }

    public function authenticateLogin($loginDetails){
        if (isset($loginDetails->email)) {
            // Get User based on the Email
            $return = $this->db->sql([
                'statement' => 'SELECT',
                'columns' => [
                    'admin_users.id',
                    'firstName',
                    'lastName',
                    'email',
                    'password',
                    'admin_roles.name roleName',
                    'forProgramId',
                        'language',
                    'authentication_token',
                    'photoURI'
                ],
                'table' => 'admin_users',
                'joins' => [
                    'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id',
                    'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                    'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId'
                ],
                'where' => ['email = ?', strtolower($loginDetails->email)]
            ]);
            if (count($return)) {
                $return = $this->db->groupResults($return, 'id', ['roles', ['roleName', 'forProgramId']]);
            } else {
                $return = false;
            }
        } else {
            $return = false;
        }

        if(isset($return[0])) {
            // Email found
            if($this->db->hasher->CheckPassword($loginDetails->password, $return[0]->password)){
                // Correct Password
                $_SESSION['authenticationToken'] = $return[0]->authentication_token;
                return new AjaxResponse(new User($return[0]));
            } else {
                // Incorrect Password
                return new AjaxResponse(-1,false,'Incorrect Password');
            }
        } else {
            // Email not found
            return new AjaxResponse(0,false,'Email not found');
        }
    }
}

class User {
    function __construct($user){
        if(is_object($user) && isset($user->id)){
            $this->id = $user->id;
            $this->firstName = $user->firstName;
            $this->lastName = $user->lastName;
            $this->email = $user->email;
            $this->roles = $user->roles;
            $this->language = $user->language;
            $this->authenticationToken = $user->authentication_token;
            $this->photoURI = $user->photoURI;
        }
    }
}
