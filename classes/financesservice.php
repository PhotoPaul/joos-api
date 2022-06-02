<?
class FinancesService {
    function __construct(){
        global $session;
        $this->db = $session->db;
        $this->dbObject = $session->db->dbObject;
        $this->auth = $session->authenticationService;
        $this->user = $session->authenticationService->user;
    }

    function getUsersFinancesData($params){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id',
                'lastName',
                'firstName',
                'photoURI',
                'SUM(GREATEST(amount,0)) owed',
                'SUM(LEAST(amount,0)) paid',
                'MAX(admin_finances_records.date_time) latest_date_time',
                'SUM(amount) balance',
                'roleId',
                'forProgramId'
            ],
            'table' => 'admin_users',
            'joins' => [
                'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_finances_records ON admin_users.id = admin_finances_records.userId'
            ],
            'where' => $params->filter->studentsOnly ? 'roleId = 6' : null,
            'group' => 'admin_users.id, roleId, forProgramId',
            'order' => 'SUM(amount) DESC'
        ]);
        $return = JSFDates($return, 'latest_date_time');
        $return = $this->db->groupResults($return, 'id', [['roles', ['roleId', 'forProgramId']]]);

        return new AjaxResponse($return);
    }

    function getAccountTemplatesData(){
        $accountTemplateItemCategories = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id, name_gr, name_en',
            'table' => 'admin_finances_template_item_categories'
        ]);

        $accountTemplates = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'name',
            'table' => 'admin_finances_template_items',
            'group' => 'name'
        ]);

        foreach($accountTemplates as $accountTemplateItem){
            $accountTemplateItem->accountTemplateItems = $this->db->sql([
                'statement' => 'SELECT',
                'columns' => 'id, category_id, amount, date_time',
                'table' => 'admin_finances_template_items',
                'where' => ['name = ?', $accountTemplateItem->name],
                'order' => 'category_id'
            ]);
        }

        return new AjaxResponse([
            "accountTemplateItemCategories" => $accountTemplateItemCategories,
            "accountTemplates" => $accountTemplates
        ]);
    }

    function saveAccountTemplateItem($params){
        if(isset($params->accountTemplateItem->id)){
            $statement = 'UPDATE';
            $return = $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_finances_template_items',
                'columns' => 'name, category_id, amount, date_time',
                'values' => [
                    $params->accountTemplateItem->name,
                    $params->accountTemplateItem->category_id,
                    $params->accountTemplateItem->amount,
                    date("Y-m-d H:i:s")
                ],
                'where' => ['id = ?', $params->accountTemplateItem->id]
            ]);
        } else {
            $statement = 'INSERT';
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_finances_template_items',
                'columns' => 'name, category_id, amount, date_time',
                'values' => [
                    $params->accountTemplateItem->name,
                    $params->accountTemplateItem->category_id,
                    $params->accountTemplateItem->amount,
                    date("Y-m-d H:i:s")
                ],
                'update' => true
            ]);
        }

        return new AjaxResponse([
            "accountTemplateItem" => (object) [
                "id" => isset($params->accountTemplateItem->id) ? $params->accountTemplateItem->id : $return->lastInsertId,
                "name" => $params->accountTemplateItem->name,
                "category_id" => $params->accountTemplateItem->category_id,
                "amount" => $params->accountTemplateItem->amount
            ],
            "statement" => $statement
        ]);
    }

    function deleteAccountTemplateItem($params){
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_finances_template_items',
            'where' => ['id = ?', $params->id]
        ]);

        return new AjaxResponse([
            "id" => $params->id
        ]);
    }

    function getUserRecordsData($params){
        if (!$params->id || !$this->auth->authenticateOperation('getUserRecordsDataOtherThanSelf')) {
            $params->id = $this->user->id;
        }

        $userData = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'id, firstName, lastName',
            'table' => 'admin_users',
            'where' => ['id = ?', $params->id]
        ]);

        $accountTemplateItemCategories = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id, name_gr, name_en',
            'table' => 'admin_finances_template_item_categories'
        ]);

        $records = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id, userId, amount, category_id, comment, date_time',
            'table' => 'admin_finances_records',
            'where' => ['userId = ?', $params->id],
            'order' => 'date_time DESC'
        ]);

        return new AjaxResponse([
            "userData" => $userData ? $userData : null,
            "accountTemplateItemCategories" => $accountTemplateItemCategories,
            "records" => JSFDates($records, 'date_time')
        ]);
    }

    function saveRecord($params){
        if(isset($params->record->id)){
            $statement = 'UPDATE';
            $return = $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_finances_records',
                'columns' => 'amount, category_id, comment, date_time',
                'values' => [
                    $params->record->amount,
                    $params->record->category_id,
                    isset($params->record->comment) ? $params->record->comment : '',
                    isset($params->record->date_time) ? $params->record->date_time : date("Y-m-d H:i:s")
                ],
                'where' => ['id = ?', $params->record->id]
            ]);
        } else {
            $statement = 'INSERT';
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_finances_records',
                'columns' => 'userId, amount, category_id, comment, date_time',
                'values' => [
                    $params->record->userId,
                    $params->record->amount,
                    $params->record->category_id,
                    isset($params->record->comment) ? $params->record->comment : '',
                    isset($params->record->date_time) ? $params->record->date_time : date("Y-m-d H:i:s")
                ]
            ]);
        }

        return new AjaxResponse([
            "record" => (object) [
                "id" => isset($params->record->id) ? $params->record->id : $return->lastInsertId,
                "userId" => $params->record->userId,
                "amount" => $params->record->amount,
                "category_id" => $params->record->category_id,
                "comment" => isset($params->record->comment)? $params->record->comment: '',
                "date_time" => $params->record->date_time? $params->record->date_time: date("Y-m-d H:i:s")
            ],
            "statement" => $statement
        ]);
    }

    function deleteRecord($params){
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_finances_records',
            'where' => ['id = ?', $params->id]
        ]);
        
        return new AjaxResponse([
            "id" => $params->id
        ]);
    }

    function saveRecordsByAccountTemplateName($params){
        if(isset($params->date)){
            $date = $params->date;
        } else {
            $date = date("Y-m-d H:i:s");
        }
        
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_finances_records',
            'columns' => 'userId, amount, category_id, date_time',
            'select' => 'SELECT ?, amount, category_id, ? FROM admin_finances_template_items WHERE name = ?',
            'extras' => [$params->userId, $date, $params->accountTemplateName]
        ]);

        return new AjaxResponse();
    }

    function sendPaymentReminder($params){
        if (!isset($params->userId)) {
            $params->userId = $this->user->id;
        }
        $return = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id',
                'firstName',
                'lastName',
                'email',
                'language',
                'IFNULL(SUM(amount), 0) AS balanceAmount',
                '(SELECT date_time FROM admin_finances_records WHERE userId = ? ORDER BY date_time DESC LIMIT 1) lastRecordDate'
            ],
            'table' => 'admin_users',
            'joins' => 'JOIN admin_finances_records ON admin_users.id = admin_finances_records.userId',
            'where' => ['admin_users.id = ?', [
                $params->userId,
                $params->userId
            ]]
        ]);

        (new NotificationService())->send([
            "toRoles" => [['roleName' => 'cashier']],
            "toEmails" => $return->email,
            "forUserIds" => $return->id,
            "templateName" => 'paymentReminder',
            "language" => $return->language,
            "vars" => [
                ['lastName', $return->lastName],
                ['firstName', $return->firstName],
                ['balanceAmount', '€ '.$return->balanceAmount],
                ['lastRecordDate', $return->language === 'gr' ? fDate($return->lastRecordDate) : fDate($return->lastRecordDate, '%d %b. %Y', 'English_United_States')],
                ['dueDate', $return->language === 'gr' ?
                    fDate((date('m') === '12' ? date('Y') + 1 : date('Y')).'-'.(date('m') === '12' ? '1' : date('m') + 1).'-'.'20') :
                    fDate((date('m') === '12' ? date('Y') + 1 : date('Y')).'-'.(date('m') === '12' ? '1' : date('m') + 1).'-'.'20', '%d %b. %Y', 'English_United_States')
                ]
            ]
        ]);

        return new AjaxResponse($return);
    }

    function getPriceCalculatorData($params) {
        return new AjaxResponse($this->db->getVariables([
            'feeRegistration',
            'feeStudentCredit',
            'feeAuditorCredit',
            'priceSingleResident',
            'priceSingleNonResidentFullTime',
            'priceCoupleResidentFullTime',
            'priceCoupleResidentCoupleNotFullTimeTwoMeals',
            'priceCoupleResidentCoupleNotFullTimeOneMeal',
            'priceCoupleNonResidentCoupleFullTime',
            'priceCoupleNonResidentCoupleNotFullTime',
        ]));
    }
}
