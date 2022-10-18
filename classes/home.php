<?

class Home {
    function __construct(){
        global $session;

        $this->db = $session->db;
        $this->dbObject = $session->db->dbObject;
        $this->auth = $session->authenticationService;
        $this->user = $session->authenticationService->user;
    }

    function getHomeData(){
        $columns = [];
        $pdoParams = [];
        // Academics Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeAcademicsTeachingScheduleDaysLeft')) array_push($categoryColumns, '(SELECT DATEDIFF("'.$this->getGraduationDate().'",NOW())) academicsTeachingScheduleDaysLeft');
        if($this->auth->authenticateOperation('homeAcademicsChapelSchedule')) array_push($categoryColumns, '(SELECT " ") academicsChapelSchedule');
        if($this->auth->authenticateOperation('homeAcademicsMyCoursesLength')) {
            array_push($categoryColumns, '(SELECT COUNT(TRUE) FROM admin_course_enrollment WHERE student_id = ?) academicsMyCoursesLength');
            array_push($pdoParams, $this->user->id);
        }
        if($this->auth->authenticateOperation('homeAcademicsAdvisorsLength')) {
            array_push($categoryColumns, '(SELECT COUNT(userId) FROM admin_user_roles WHERE roleId = 11) academicsAdvisorsLength');
        }
        if($this->auth->authenticateOperation('homeAcademicsAdvisor')) {
            array_push($categoryColumns, '(SELECT advisorId FROM admin_advisors WHERE userId = ?) academicsAdvisorId');
            array_push($categoryColumns, '(SELECT lastName FROM admin_advisors JOIN admin_users ON advisorId = id WHERE userId = ?) academicsAdvisorLastName');
            array_push($categoryColumns, '(SELECT firstName FROM admin_advisors JOIN admin_users ON advisorId = id WHERE userId = ?) academicsAdvisorFirstName');
            array_push($categoryColumns, '(SELECT email FROM admin_advisors JOIN admin_users ON advisorId = id WHERE userId = ?) academicsAdvisorEmail');
            array_push($categoryColumns, '(SELECT photoURI FROM admin_user_profiles WHERE id = (SELECT advisorId FROM admin_advisors WHERE userId = ?)) academicsAdvisorPhotoURI');
            array_push($pdoParams, $this->user->id);
            array_push($pdoParams, $this->user->id);
            array_push($pdoParams, $this->user->id);
            array_push($pdoParams, $this->user->id);
            array_push($pdoParams, $this->user->id);
        }
        // This returns only programs with enrolled students. However, this is problematic when there are no programs with enrolled students.
        // if($this->auth->authenticateOperation('homeAcademicsProgramsLength')) array_push($categoryColumns, '(SELECT COUNT(id) FROM (SELECT admin_programs.id FROM admin_programs JOIN admin_program_enrollment ON admin_programs.id = program_id GROUP BY admin_programs.id) AS only_programs_with_enrolled_student) academicsProgramsLength');
        if($this->auth->authenticateOperation('homeAcademicsProgramsLength')) array_push($categoryColumns, '(SELECT COUNT(id) FROM admin_programs) academicsProgramsLength');
        if($this->auth->authenticateOperation('homeAcademicsStudentsLength')) array_push($categoryColumns, '(SELECT COUNT(userId) FROM (SELECT userId FROM admin_user_roles LEFT JOIN admin_program_enrollment ON admin_program_enrollment.student_id = admin_user_roles.userId WHERE roleId = 6 AND active = TRUE GROUP BY userId) AS only_students_active_in_a_program) academicsStudentsLength');
        if($this->auth->authenticateOperation('homeAcademicsGraduatesLength')) array_push($categoryColumns, '(SELECT COUNT(id) FROM admin_program_enrollment WHERE graduated = TRUE) academicsGraduatesLength');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE academics');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));
        // Admissions Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeAdmissionsApplicantsLength')) array_push($categoryColumns, '(SELECT COUNT(*) FROM (SELECT COUNT(id) FROM admin_users LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id JOIN admin_user_applications ON admin_user_applications.userId = admin_users.id WHERE roleId = 5 GROUP BY admin_users.id) admin_admissions_applicants) admissionsApplicantsLength');
        if($this->auth->authenticateOperation('homeAdmissionsApplicantsProcessedLength')) array_push($categoryColumns, '(SELECT COUNT(candidateId) FROM (SELECT candidateId FROM admin_admissions LEFT JOIN admin_users ON admin_admissions.candidateId = admin_users.id LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id WHERE roleId = 5 GROUP BY candidateId) only_candidates_with_votes) admissionsApplicantsProcessedLength');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE admissions');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));
        // Finances Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeFinancesMyDebt')){
            array_push($categoryColumns, '(SELECT IFNULL(SUM(amount), NULL) FROM admin_finances_records WHERE userId = ?) financesMyDebt');
            array_push($pdoParams, $this->user->id);
        }
        if($this->auth->authenticateOperation('homeFinancesTotalAmount')) array_push($categoryColumns, '(IFNULL((SELECT SUM(amount) FROM admin_finances_records), 0)) financesTotalAmount');
        if($this->auth->authenticateOperation('homeFinancesTemplatesLength')) array_push($categoryColumns, '(SELECT COUNT(DISTINCT NAME) FROM admin_finances_template_items) financesTemplatesLength');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE finances');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));
        // Evaluation Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeEvaluationsMyEvaluations')) {
            array_push($categoryColumns, '(SELECT COUNT(forUserId) FROM admin_evaluations WHERE forUserId = ?) evaluationsMyEvaluations');
            array_push($pdoParams, $this->user->id);
        }
        if($this->auth->authenticateOperation('homeEvaluationsAllEvaluations')) array_push($categoryColumns, '(SELECT COUNT(TRUE) FROM admin_evaluations) evaluationsAllEvaluations');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE evaluations');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));
        // Library Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeLibraryMyCheckedOutBooks')){
            array_push($categoryColumns, '(SELECT COUNT(library_users.barcode) FROM admin_users LEFT JOIN library_users ON admin_users.id = library_users.userId LEFT JOIN library_checkouts ON library_users.barcode = library_checkouts.user_barcode WHERE admin_users.id = ? AND library_checkouts.book_barcode IS NOT NULL) libraryMyCheckedOutBooks');
            array_push($pdoParams, $this->user->id);
        }
        if($this->auth->authenticateOperation('homeLibraryMyCheckedOutBooksStatus')){
            array_push($categoryColumns, '(SELECT MIN(DATEDIFF(due_date, CURDATE())) FROM library_users JOIN library_checkouts ON library_users.barcode = library_checkouts.user_barcode WHERE userId = ?) libraryMyCheckedOutBooksStatus');
            array_push($pdoParams, $this->user->id);
        }
        if($this->auth->authenticateOperation('homeLibraryAllCheckedOutBooks')) array_push($categoryColumns, '(SELECT COUNT(book_barcode) FROM library_checkouts) libraryAllCheckedOutBooks');
        if($this->auth->authenticateOperation('homeLibraryLibraryCards')) array_push($categoryColumns, '(SELECT COUNT(barcode) FROM library_users) libraryLibraryCards');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE library');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));
        // Variable Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeVariablesLength')) array_push($categoryColumns, '(SELECT COUNT(name) FROM admin_variables) variablesLength');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE variables');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));
        // System Fields
        $categoryColumns = [];
        if($this->auth->authenticateOperation('homeSystemOpenErrorsLength')) array_push($categoryColumns, '(SELECT COUNT(id) FROM admin_errors WHERE OPEN = TRUE) systemOpenErrorsLength');
        if($this->auth->authenticateOperation('homeSystemUsersLength')) array_push($categoryColumns, '(SELECT COUNT(id) FROM admin_users) systemUsersLength');
        if($this->auth->authenticateOperation('homeSystemOperationsLength')) array_push($categoryColumns, '(SELECT COUNT(DISTINCT operation) FROM admin_operations) systemOperationsLength');
        if($this->auth->authenticateOperation('homeSystemFilesTotalSize')) array_push($categoryColumns, '(SELECT SUM(filesize) / 1024 / 1024 FROM admin_files) systemFilesTotalSize');
        if($this->auth->authenticateOperation('homeSystemTablesTotalSize')) array_push($categoryColumns, '(SELECT SUM((DATA_LENGTH + INDEX_LENGTH)) / 1024 / 1024 size FROM information_schema.TABLES WHERE TABLE_SCHEMA = "grbcgr_main" AND (LEFT(TABLE_NAME, 6) = "admin_") OR LEFT(TABLE_NAME, 8) = "library_") systemTablesTotalSize');
        if($this->auth->authenticateOperation('homeSystemTemplatesLength')) array_push($categoryColumns, '(SELECT COUNT(name) FROM admin_notification_templates) systemTemplatesLength');
        if($this->auth->authenticateOperation('homeSystemLogEntriesLength')) array_push($categoryColumns, '(SELECT COUNT(id) FROM admin_logs) systemLogEntriesLength');
        if(count($categoryColumns)) array_push($categoryColumns, 'TRUE `system`');
        if(count($categoryColumns)) array_push($columns, implode(', ', $categoryColumns));

        $result = count($columns) ?
            $this->db->sql1([
                'statement' => 'SELECT',
                'columns' => implode(', ', $columns),
                'extras' => $pdoParams
            ]) : new stdClass();

        // Get Evaluations
        if($this->auth->authenticateOperation('homeMyEvaluations')){
            $result->myEvaluations = $this->db->sql([
                'statement' => 'SELECT',
                'columns' => [
                    'firstName',
                    'lastName',
                    'photoURI',
                    'code',
                    'admin_courses.name_en',
                    'admin_courses.name_gr',
                    'admin_course_categories.code_en',
                    'admin_course_categories.code_gr',
                    'forUserId',
                    'forCourseId',
                    'title_en',
                    'title_gr',
                    'questionIds'
                ],
                'table' => 'admin_evaluations_pending',
                'joins' => [
                    'LEFT JOIN admin_users ON forUserId = admin_users.id',
                    'LEFT JOIN admin_user_profiles ON forUserId = admin_user_profiles.id',
                    'LEFT JOIN admin_courses ON forCourseId = admin_courses.id',
                    'LEFT JOIN admin_course_categories ON category_id = admin_course_categories.id',
                    'LEFT JOIN admin_form_templates ON admin_form_templates.templateId = 4'
                ],
                'where' => ['fromUserId = ?', $this->user->id]
            ]);

            foreach ($result->myEvaluations as $form) {
                $questionIds = explode(',', $form->questionIds);
                $where = 'questionId = '.implode(' OR questionId = ', $questionIds);
                $order = 'FIELD(questionId, '.implode(', ', $questionIds).')';

                $form->questions = $this->db->sql([
                    'statement' => 'SELECT',
                    'columns' => [
                        'questionId',
                        'title_en',
                        'title_gr',
                        'type',
                        'childrenIds'
                    ],
                    'table' => 'admin_form_questions',
                    'where' => $where,
                    'order' => $order
                ]);

                foreach($form->questions as $key => $question) {
                    if($question->childrenIds) {
                        $question->children = [];
                        $question->childrenIds = explode(',', $question->childrenIds);
                        $where = 'questionId = '.implode(' OR questionId = ', $question->childrenIds);
                        $order = 'FIELD(questionId, '.implode(', ', $question->childrenIds).')';
                        $question->children = $this->db->sql([
                            'statement' => 'SELECT',
                            'columns' => [
                                'questionId',
                                'title_en',
                                'title_gr',
                                'type',
                                'childrenIds'
                            ],
                            'table' => 'admin_form_questions',
                            'where' => $where,
                            'order' => $order
                        ]);
                    }
                }
            }

            if (!isset($result->myEvaluations) || count($result->myEvaluations) === 0) {
                unset($result->myEvaluations);
            }
        }

        // Get Applications
        if($this->auth->authenticateOperation('homeApplications')){
            $result->applications = $this->db->sql([
                'statement' => 'SELECT',
                'columns' => [
                    'userId',
                    'applicationId',
                    'applicationStatus',
                    'icon',
                    'editPath',
                    'viewPath',
                    'heading_en',
                    'heading_gr',
                    'dbTable'
                ],
                'table' => 'admin_user_applications',
                'joins' => 'JOIN admin_applications ON applicationId = admin_applications.id',
                'where' => ['userId = ? AND hidden = 0', $this->user->id],
                'order' => 'applicationId'
            ]);

            if(!count($result->applications) && $this->auth->authenticateOperation('homeCandidateGetProgramsDataWhenNoApplication')) {
                // No active program application, return available Academic Programs as Application Packages
                $result->programs = $this->db->sql([
                    'statement' => 'SELECT',
                    'columns' => 'id, program_name_gr, program_name_en, number_of_semesters, !ISNULL(auditor_applications) canAudit',
                    'table' => 'admin_programs'
                ]);
            }
        }

        return new AjaxResponse($result);
    }

    function getGraduationDate(){
        $result = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'value',
            'table' => 'admin_variables',
            'where' => ['name = ?', 'dateGraduation']
        ]);
        if (isset($result->value)) {
            return $result->value;
        }
    }

    function getAdviseesData(){
        $professor_id = $this->user->id;

        return new AjaxResponse([
            "myAdvisees" => $this->db->sql([
                'statement' => 'SELECT',
                'columns' => 'userId, firstName, lastName, email, photoURI',
                'table' => 'admin_advisors',
                'joins' => [
                    'JOIN admin_users ON userId = admin_users.id',
                    'JOIN admin_user_profiles ON userId = admin_user_profiles.id'
                ],
                'where' => ['advisorId = ?', $professor_id]
            ])
        ]);
    }
}