<?

class FormsService {
    function __construct(){
        global $session;
        $this->db = $session->db;
        $this->hasher = $session->db->hasher;
        $this->user = $session->authenticationService->user;
    }

    function submitForm($params) {
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_evaluations',
            'columns' => 'forUserId, forCourseId, evaluation',
            'values' => [$params->forUserId, $params->forCourseId, json_encode($params->questions)]
        ]);

        if ($return->rowCount === 1) {
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_evaluations_pending',
                'where' => ['forUserId = ? AND forCourseId = ? AND fromUserId = ?', [
                    $params->forUserId, $params->forCourseId, $this->user->id
                ]]
            ]);
        }
        return new AjaxResponse($return);
    }
}