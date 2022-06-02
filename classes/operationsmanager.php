<?

class OperationsManager {
    function __construct(){
        global $session;

        $this->db = $session->db;
    }

    function addOperation($params) {
        // Check if operation exists
        $return = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'operation',
            'table' => 'admin_operations',
            'where' => ['operation = ?', $params->operationName]
        ]);
        if ($return === false) {
            // Add new operation
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_operations',
                'columns' => 'operation, role',
                'values' => [$params->operationName, 'none'],
                'update' => true
            ]);
            return new AjaxResponse(true);
        }

        return new AjaxResponse(false);
    }

    function getOperations(){
        $operations = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'operation, role',
            'table' => 'admin_operations',
            'order' => 'role="none" DESC, operation, role'
        ]);
        $operations = $this->db->groupResults($operations, 'operation', ['roles', 'role']);

        return new AjaxResponse($operations);
    }

    function getRoles($params) {
        $roles = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'id roleId',
                'name roleName',
                'title_gr',
                'title_en'
            ],
            'table' => 'admin_roles',
            'order' => 'title_gr'
        ]);

        return new AjaxResponse($roles);
    }

    function addRole($params) {
        $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_operations',
            'columns' => ['operation', 'role'],
            'values' => [$params->operation, $params->role],
            'update' => true
        ]);

        // If role 'any' was added
        if ($params->role === 'any') {
            // Remove all but 'any'
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_operations',
                'where' => ['operation = ? AND role != ?', [
                    $params->operation, $params->role
                ]]
            ]);
        } elseif ($params->role !== 'none') {
            // Make sure role none is not left
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_operations',
                'where' => ['operation = ? AND role = ?', [
                    $params->operation, 'none'
                ]]
            ]);    
        }

        return new AjaxResponse(true);
    }

    function removeRole($params) {
        // Remove Role
        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_operations',
            'where' => ['operation = ? AND role = ?', [
                $params->operation, $params->role
            ]]
        ]);

        // If there's no Roles left, then add none role
        $result = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'COUNT(role) noRoles',
            'table' => 'admin_operations',
            'where' => ['operation = ?', $params->operation]
        ]);
        if ($result[0]->noRoles === '0') {
            $this->addRole((object) [
                'operation' => $params->operation,
                'role' => 'none'
            ]);
        }

        return new AjaxResponse(true);
    }
}