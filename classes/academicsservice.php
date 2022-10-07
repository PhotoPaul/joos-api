<?

class AcademicsService {
    function __construct(){
        global $session;
        $this->db = $session->db;
        $this->auth = $session->authenticationService;
        $this->user = $session->authenticationService->user;
    }

    function getProgramsData(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_programs.id',
                'program_name_gr',
                'program_name_en',
                'number_of_semesters',
                'COUNT(admin_program_enrollment.id) enrolled_students',
                'SUM(graduated) graduated_students',
                'SUM(active) active_students'
            ],
            'table' => 'admin_programs',
            'joins' => [
                'LEFT JOIN admin_program_enrollment ON admin_programs.id = program_id',
                'LEFT JOIN admin_users ON admin_program_enrollment.student_id = admin_users.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id'
            ],
            'where' => 'roleId = 6 OR roleId = 7 OR roleId IS NULL',
            'group' => 'admin_programs.id'
        ]);

        return new AjaxResponse($return);
    }

    function getProgramEnrollmentData($params){
        $return = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => [
                'id',
                'program_name_gr',
                'program_name_en',
                'number_of_semesters'
            ],
            'table' => 'admin_programs',
            'where' => ['id = ?', $params->id]
        ]);

        if ($this->auth->authenticateOperation('getProgramEnrollmentDataIncludeInactive')) {
            $where = 'roleId = 5 OR roleId = 6 OR roleId = 7';
        } else {
            $where = 'active = TRUE AND (roleId = 5 OR roleId = 6 OR roleId = 7)';
        }
        $return->programEnrollment = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'programEnrollmentId',
                'userId',
                'photoURI',
                'active',
                'roleId',
                'firstName',
                'lastName',
                'dateTime'
            ],
            'table' => '(SELECT admin_program_enrollment.id programEnrollmentId, student_id userId, photoURI, active, roleId, firstName, lastName, admin_program_enrollment.date_time dateTime FROM admin_program_enrollment LEFT JOIN admin_users ON admin_users.id = admin_program_enrollment.student_id LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id WHERE program_id = ? UNION ALL SELECT NULL programEnrollmentId, admin_user_roles.userId, photoURI, NULL active, roleId, firstName, lastName, NULL dateTime FROM admin_user_roles LEFT JOIN admin_users ON admin_users.id = admin_user_roles.userId LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id WHERE (roleId = 6 AND forProgramId = ?) OR (roleId = 7 AND (forProgramId IS NULL OR forProgramId = ?))) all_users',
            'order' => 'userId DESC',
            'extras' => [$params->id, $params->id, $params->id]
        ]);
        $return->programEnrollment = JSFDates($return->programEnrollment, 'dateTime');
        $return->programEnrollment = $this->db->groupResults($return->programEnrollment, 'userId', ['roles', 'roleId']);

        // Prepare Enrollment Data
        foreach ($return->programEnrollment as $enrollmentItem) {
            // Compute isProfessor
            $enrollmentItem->isProfessor = false;
            foreach ($enrollmentItem->roles as $role) {
                if ($role === 7) {
                    $enrollmentItem->isProfessor = true;
                    break;
                }
            }
        }

        // Sort Enrollment Data
        function sortProgramEnrollmentData($a, $b) {
            $aScore = (isset($a->active) && $a->active) ? 3 : 0;
            $aScore+= (isset($a->programEnrollmentId) && $a->programEnrollmentId) ? 2 : 0;
            $aScore+= (isset($a->isProfessor) && $a->isProfessor) ? 0 : 1;
            $bScore = (isset($b->active) && $b->active) ? 3 : 0;
            $bScore+= (isset($b->programEnrollmentId) && $b->programEnrollmentId) ? 2 : 0;
            $bScore+= (isset($b->isProfessor) && $b->isProfessor) ? 0 : 1;
            return $bScore <=> $aScore;
        }
        usort($return->programEnrollment, 'sortProgramEnrollmentData');

        return new AjaxResponse($return);
    }

    function getProgramIdsFromUserId($userId, $roleIds) {
        if (!is_array($roleIds)) {
            $roleIds = [$roleIds];
        }
        foreach ($roleIds as $roleId) {
            $whereString[] = 'roleId = ?';
            $whereParams[] = $roleId;
        }
        $whereString = 'userId = ? AND ('.implode(' OR ', $whereString).')';
        array_unshift($whereParams, $userId);
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'forProgramId',
            'table' => 'admin_user_roles',
            'where' => [$whereString, $whereParams]
        ]);

         return $return;
    }

    function enrollUserToProgram($params) {
        if(isset($params->programEnrollmentId)){
            $return = $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_program_enrollment',
                'columns' => [
                    'active',
                    'date_time'
                ],
                'values' => [
                    true,
                    date("Y-m-d H:i:s")
                ],
                'where' => ['id = ?', $params->programEnrollmentId]
            ]);
        } else {
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_program_enrollment',
                'columns' => 'student_id, program_id, date_time',
                'values' => [
                    $params->studentId,
                    $params->programId,
                    date("Y-m-d H:i:s")
                ]
            ]);
        }

        return new AjaxResponse([
            "programEnrollmentId" => isset($params->programEnrollmentId) ? $params->programEnrollmentId : $return->lastInsertId,
            "active" => '1',
            "dateTime" => date("Y-m-d H:i:s")
        ]);
    }

    function unenrollUserFromProgram($params){
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_program_enrollment',
            'columns' => 'active',
            'values' => 0,
            'where' => ['id = ?', $params->programEnrollmentId]
        ]);

        return new AjaxResponse($return);
    }

    function eraseUserFromProgram($params){
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_program_enrollment',
            'where' => ['id = ?', $params->programEnrollmentId]
        ]);

        return new AjaxResponse($return);
    }

    function getCoursesData($params){
        $return = [
            "courseCategories" => $this->db->sql([
                'statement' => 'SELECT',
                'columns' => 'id, parent_id, code_gr, code_en, name_gr, name_en',
                'table' => 'admin_course_categories',
                'where' => 'parent_id IS NOT NULL'
            ]),
            "courses" => $this->db->sql([
                'statement' => 'SELECT',
                'columns' => [
                    'admin_courses.id',
                    'admin_courses.program_id',
                    'admin_courses.category_id',
                    'admin_courses.code',
                    'admin_course_categories.code_gr',
                    'admin_course_categories.code_en',
                    'admin_courses.code',
                    'admin_courses.name_gr',
                    'admin_courses.name_en',
                    'admin_courses.year',
                    'admin_courses.semester',
                    'admin_courses.required',
                    'admin_courses.credits',
                    'admin_courses.ects_credits',
                    'admin_courses.moodle_id',
                    'admin_courses.moodle_category_id',
                    'admin_courses.active',
                    'admin_course_fractions.id AS fractionId',
                    'admin_course_fractions.label AS fractionLabel',
                    'filtered_admin_course_enrollment.no_enrolled_students',
                    'admin_course_prerequisites.prerequisiteCourseId AS prerequisiteCourseId',
                    'admin_course_categories_1.code_gr AS prerequisiteCourseCode_gr',
                    'admin_course_categories_1.code_en AS prerequisiteCourseCode_en',
                    'admin_courses_1.code AS prerequisiteCourseCode',
                    'admin_courses_1.name_gr AS prerequisiteCourseName_gr',
                    'admin_courses_1.name_en AS prerequisiteCourseName_en',
                    'admin_courses_1.active AS prerequisiteCourseActive'
                ],
                'table' => 'admin_courses',
                'joins' => [
                    'LEFT JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
                    'LEFT JOIN admin_course_fractions ON admin_courses.id = admin_course_fractions.courseId',
                    'LEFT JOIN admin_course_prerequisites ON (admin_courses.id = admin_course_prerequisites.courseId)',
                    'LEFT JOIN admin_courses AS admin_courses_1 ON (admin_course_prerequisites.prerequisiteCourseId = admin_courses_1.id)',
                    'LEFT JOIN admin_course_categories AS admin_course_categories_1 ON (admin_courses_1.category_id = admin_course_categories_1.id)',
                    'LEFT JOIN (SELECT course_id, COUNT(course_id) no_enrolled_students FROM admin_course_enrollment JOIN admin_users ON admin_course_enrollment.student_id = admin_users.id LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id WHERE active = TRUE AND roleId = 6 GROUP BY course_id) AS filtered_admin_course_enrollment ON admin_courses.id = filtered_admin_course_enrollment.course_id'
                ],
                'where' => [(
                    $params->filter->active === '1' ? 'admin_courses.active = "1" AND ' : (
                        $params->filter->active === '0' ? 'admin_courses.active = "0" AND ': ''
                    )
                ).'admin_courses.program_id = ?', $params->filter->programId],
                'order' => 'admin_course_categories.code_gr, admin_courses.code, fractionLabel, credits'
            ])
        ];
        $return['courses'] = $this->db->groupResults($return['courses'], 'id', [
            ['fractions', ['fractionId', 'fractionLabel']],
            ['prerequisites', [
                'prerequisiteCourseId',
                'prerequisiteCourseCode_gr',
                'prerequisiteCourseCode_en',
                'prerequisiteCourseCode',
                'prerequisiteCourseName_en',
                'prerequisiteCourseName_gr',
                'prerequisiteCourseActive'
            ]]
        ]);
        return new AjaxResponse($return);
    }

    function getMyCoursesData(){
        $student_id = $this->user->id;
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_course_enrollment.course_id',
                $this->auth->authenticateOperation('getProfessorHomeDataIncludeNoStudents') ?
                '(SELECT COUNT(admin_users.id) FROM admin_course_enrollment AS filtered_admin_course_enrollment JOIN admin_users ON filtered_admin_course_enrollment.student_id = admin_users.id LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id WHERE filtered_admin_course_enrollment.course_id = admin_course_enrollment.course_id AND roleId = 6) no_enrolled_students' :
                'FALSE',
                'admin_course_categories.code_gr',
                'admin_course_categories.code_en',
                'admin_courses.code',
                'admin_courses.name_gr',
                'admin_courses.name_en',
                'moodle_id',
                'admin_course_enrollment.active',
                'grade'
            ],
            'table' => 'admin_course_enrollment',
            'joins' => [
                'JOIN admin_courses ON admin_course_enrollment.course_id = admin_courses.id',
                'JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id AND student_id = ?'
            ],
            'order' => 'active DESC, grade IS NULL DESC, grade=-1 DESC, code_gr',
            'extras' => [$student_id]
        ]);

        return new AjaxResponse($return);
    }

    function saveCourse($params){
        // Get Course category code
        $course = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'code_gr, code_en',
            'table' => 'admin_course_categories',
            'where' => ['id = ?', $params->course->category_id]
        ]);

        // Check if Moodle needs to be updated
        if ($params->course->addToMoodle && isset($params->course->moodleTitle)) {
            try {
                $moodleReturn = (new Moodle())->moodleSaveCourse(
                    isset($params->course->moodle_id) ? $params->course->moodle_id : null,
                    $params->course->moodleTitle === "gr" ?
                        $params->course->name_gr :
                        $params->course->name_en,
                    ($params->course->moodleTitle === "gr" ?
                        $course->code_gr :
                        $course->code_en
                    )." ".$params->course->code,
                    $params->course->moodle_category_id
                );
                if (is_array($moodleReturn->data)) {
                    $params->course->moodle_id = $moodleReturn->data[0]->id;
                } elseif (isset($moodleReturn->data->message)) {
                    throw (new Exception($moodleReturn->data->message));
                } elseif (!isset($params->course->moodle_id)) {
                    $params->course->moodle_id = 0;
                }
            } catch(Exception $e){
                return new AjaxError(__METHOD__.': '.$e->getMessage());
            }
        }

        // Update Course
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_courses',
            'columns' => [
                'id',
                'program_id',
                'category_id',
                'code',
                'code_gr',
                'code_en',
                'name_gr',
                'name_en',
                'year',
                'semester',
                'required',
                'credits',
                'ects_credits',
                'moodle_id',
                'moodle_category_id',
                'active'
            ],
            'values' => [
                isset($params->course->id) ? $params->course->id : null,
                $params->course->program_id,
                $params->course->category_id,
                $params->course->code,
                $course->code_gr,
                $course->code_en,
                $params->course->name_gr,
                $params->course->name_en,
                $params->course->year,
                $params->course->semester,
                (isset($params->course->required) && $params->course->required != false) ? $params->course->required : 0,
                $params->course->credits,
                $params->course->ects_credits,
                isset($params->course->moodle_id) ? $params->course->moodle_id : null,
                isset($params->course->moodle_category_id) ? $params->course->moodle_category_id : null,
                isset($params->course->active) ? $params->course->active : true
            ],
            'update' => true
        ]);
        $params->course->id = isset($params->course->id) ? $params->course->id : $return->lastInsertId;

        // Check if prerequisites need to be refreshed
        if (isset($params->course->prerequisites)) {
            // Clear current prerequisites
            $return = $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_course_prerequisites',
                'where' => ['courseId = ?', $params->course->id]
            ]);

            // Add prerequisites
            foreach ($params->course->prerequisites as $prerequisite) {
                $this->db->sql([
                    'statement' => 'INSERT INTO',
                    'table' => 'admin_course_prerequisites',
                    'columns' => 'courseId, prerequisiteCourseId',
                    'values' => [$params->course->id, $prerequisite->prerequisiteCourseId]
                ]);
            }
        }

        return new AjaxResponse([
            "id" => $params->course->id,
            "program_id" => $params->course->program_id,
            "category_id" => $params->course->category_id,
            "code" => $params->course->code,
            "code_gr" => $course->code_gr,
            "code_en" => $course->code_en,
            "name_gr" => $params->course->name_gr,
            "name_en" => $params->course->name_en,
            "fractions" => $params->course->fractions,
            "year" => $params->course->year,
            "semester" => $params->course->semester,
            "required" => isset($params->course->required) && $params->course->required ? '1' : '0',
            "credits" => $params->course->credits,
            "ects_credits" => $params->course->ects_credits,
            "moodle_id" => isset($params->course->moodle_id) ? $params->course->moodle_id : null,
            "moodle_category_id" => isset($params->course->moodle_category_id) ? $params->course->moodle_category_id : null,
            "prerequisites" => $params->course->prerequisites,
            "active" => isset($params->course->active) ? $params->course->active : '1'
        ]);
    }

    function saveCourseFraction($params){
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_course_fractions',
            'columns' => 'id, courseId, label',
            'values' => [
                isset($params->fractionModel->id) ? $params->fractionModel->fractionId : null,
                $params->fractionModel->courseId,
                $params->fractionModel->fractionLabel
            ],
            'update' => true
        ]);
        $courseFractionId = isset($params->fractionModel->fractionId) ? $params->fractionModel->fractionId : $return->lastInsertId;

        // Enroll any Professors who are enrolled without a fraction, to the most recent fraction
        if($return->lastInsertId) {
            $return = $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_course_enrollment',
                'joins' => [
                    'LEFT JOIN admin_users ON admin_course_enrollment.student_id = admin_users.id',
                    'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id'
                ],
                'columns' => 'courseFractionId',
                'values' => $courseFractionId,
                'where' => ['course_id = ? AND roleId = 7 AND courseFractionId = 0', $params->fractionModel->courseId]
            ]);
        }

        return new AjaxResponse([
            "fractionId" => isset($params->fractionModel->id) ? $params->fractionModel->id : $courseFractionId,
            "courseId" => $params->fractionModel->courseId,
            "fractionLabel" => $params->fractionModel->fractionLabel
        ]);
    }

    function deleteCourseFraction($params){
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_course_fractions',
            'where' => ['id = ?', $params->id]
        ]);
        
        // Unenroll any Professors who are enrolled to the specific fraction
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_course_enrollment',
            'where' => ['courseFractionId = ?', $params->id]
        ]);

        return new AjaxResponse();
    }

    function getCourseEnrollmentData($params){
        // Get Course Metadata
        $course = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_courses.id',
                'admin_courses.program_id AS programId',
                'admin_course_fractions.id AS fractionId',
                'admin_course_fractions.label AS fractionLabel',
                '"0" AS fractionActive',
                'CONCAT(admin_course_categories.code_'.$this->user->language.', " ", admin_courses.code) AS code',
                'admin_courses.name_'.$this->user->language.' AS name',
                'admin_courses.credits',
                'admin_courses.ects_credits',
                'admin_courses.active'
            ],
            'table' => 'admin_courses',
            'joins' => [
                'LEFT JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
                'LEFT JOIN admin_course_fractions ON admin_courses.id = admin_course_fractions.courseId'
            ],
            'where' => ['admin_courses.id = ?', $params->id],
            'order' => 'admin_course_fractions.label'
        ]);
        // Ensure there's at least one Fraction
        if (isset($course[0]) && !$course[0]->fractionId) {
            $course[0]->fractionId = '0';
            $course[0]->fractionLabel = $course[0]->code;
            $course[0]->fractionActive = '0';
        }
        // Group Course Fractions
        $course = $this->db->groupResults($course, 'id', ['fractions', ['fractionId', 'fractionLabel', 'fractionActive']])[0];

        // Get Users Enrollment Data
        $columns = [
            'admin_program_enrollment.student_id AS userId',
            'firstName',
            'lastName',
            'admin_roles.name roleName',
            'photoURI',
            'fractionEnrollmentId',
            'fractionId',
            'label_'.($this->user->language).' fractionLabel',
            'fractionActive',
            'fractionDateTime',
            'courseEnrollmentGradeSemester',
            'courseEnrollmentGradeYear'
        ];
        if ($this->auth->authenticateOperation('getCourseEnrollmentDataIncludeAllStudents')) {
            array_push($columns, 'courseEnrollmentGrade');
            $join5thType = 'LEFT ';
        } elseif ($this->auth->authenticateOperation('getCourseEnrollmentDataIncludeAllStudentsCensorGrade')) {
            array_push($columns, 'IF(courseEnrollmentGrade, "-5.0", null) courseEnrollmentGrade');
            $join5thType = '';
        } else {
            array_push($columns, 'courseEnrollmentGrade');
            $join5thType = '';
        }
        $joins = [
            'JOIN admin_users ON admin_program_enrollment.student_id = admin_users.id',
            'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id',
            'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
            'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId',
            $join5thType.'JOIN (SELECT admin_course_enrollment.id AS fractionEnrollmentId, student_id AS courseEnrollmentStudentId, course_id AS courseId, courseFractionId AS fractionId, label_en, label_gr, active AS fractionActive, grade AS courseEnrollmentGrade, gradeSemester AS courseEnrollmentGradeSemester, gradeYear AS courseEnrollmentGradeYear, date_time AS fractionDateTime FROM admin_course_enrollment LEFT JOIN (SELECT id, courseId, label AS label_en, label AS label_gr FROM admin_course_fractions WHERE courseId = ? UNION SELECT 0, admin_courses.id AS courseId, CONCAT(admin_course_categories.code_en, " ", CODE) AS label_en, CONCAT(admin_course_categories.code_gr, " ", CODE) AS label_gr FROM admin_courses LEFT JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id WHERE admin_courses.id = ? ORDER BY courseId, id) AS allFractionsTable ON courseFractionId = allFractionsTable.id WHERE course_id = ?) filtered_course_enrollment ON admin_program_enrollment.student_id = filtered_course_enrollment.courseEnrollmentStudentId'
        ];
        $course->enrollmentData = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => $columns,
            'table' => 'admin_program_enrollment',
            'joins' => $joins,
            'where' => ['admin_program_enrollment.program_id = ? AND admin_program_enrollment.active = TRUE', [
                $params->id,
                $params->id,
                $params->id,
                $course->programId
            ]],
            'order' => 'userId DESC'
        ]);
        $course->enrollmentData = JSFDates($course->enrollmentData, 'fractionDateTime');
        // Group User Roles and Fractions
        $course->enrollmentData = $this->db->groupResults(
            $course->enrollmentData, 'userId', [
                ['roles', 'roleName'],
                ['fractions', [
                        'fractionEnrollmentId',
                        'fractionId',
                        'fractionLabel',
                        'fractionActive',
                        'fractionDateTime'
                ]]
            ]
        );

        // Prepare Enrollment Data
        foreach ($course->enrollmentData as $enrollmentItem) {
            // Compute isProfessor
            $enrollmentItem->isProfessor = false;
            foreach ($enrollmentItem->roles as $role) {
                if ($role === 'professor') {
                    $enrollmentItem->isProfessor = true;
                    break;
                }
            }
            // Compute isEnrolled & isEnrollmentActive
            foreach ($enrollmentItem->fractions as $fraction) {
                $enrollmentItem->isEnrolled = false;
                $enrollmentItem->isEnrollmentActive = false;
                if ($fraction->fractionEnrollmentId) {
                    $enrollmentItem->isEnrolled = $enrollmentItem->isEnrolled | true;
                    $enrollmentItem->isEnrollmentActive = +(isset($fraction->fractionActive) ? $fraction->fractionActive : false);
                }
            }
        }

        // Sort Enrollment Data
        function sortEnrollmentData($a, $b) {
            $aScore = (isset($a->isEnrollmentActive) && $a->isEnrollmentActive) ? 3 : 0;
            $aScore+= (isset($a->isEnrolled) && $a->isEnrolled) ? 2 : 0;
            $aScore+= (isset($a->isProfessor) && $a->isProfessor) ? 0 : 1;
            $bScore = (isset($b->isEnrollmentActive) && $b->isEnrollmentActive) ? 3 : 0;
            $bScore+= (isset($b->isEnrolled) && $b->isEnrolled) ? 2 : 0;
            $bScore+= (isset($b->isProfessor) && $b->isProfessor) ? 0 : 1;
            return $bScore <=> $aScore;
        }
        usort($course->enrollmentData, 'sortEnrollmentData');

        return new AjaxResponse($course);
    }

    function enrollUserToCourse($params){
        if (!isset($params->fractionEnrollmentId)){
            $fractionEnrollmentId = $this->isUserEnrolledToCourse($params);
            if ($fractionEnrollmentId) {
                $params->fractionEnrollmentId = $fractionEnrollmentId->id;
            }
        }
        // Activate known Course Enrollment Record
        if (isset($params->fractionEnrollmentId)) {
            $statement = 'UPDATE';
            $columns = 'active';
            $values = true;
            $where = ['id = ?', $params->fractionEnrollmentId];
        } else {
        // Create new Course Enrollment Record
            $statement = 'INSERT INTO';
            $columns = [
                'id',
                'student_id',
                'course_id',
                'courseFractionId',
                'active'
            ];
            $values = [
                isset($params->fractionEnrollmentId) ? $params->fractionEnrollmentId : null,
                $params->studentId,
                $params->courseId,
                isset($params->fractionId) ? $params->fractionId : 0,
                true
            ];
        }
        // Execute Query
        $return = $this->db->sql([
            'statement' => $statement,
            'table' => 'admin_course_enrollment',
            'columns' => $columns,
            'values' => $values,
            'where' => isset($where) ? $where : null
        ]);

        // Update Moodle
        $user = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'email',
                'admin_roles.name roleName',
                'moodle_id'
            ],
            'table' => 'admin_course_enrollment',
            'joins' => [
                'JOIN admin_users ON student_id = admin_users.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_roles ON admin_roles.id = admin_user_roles.roleId',
                'JOIN admin_courses ON course_id = admin_courses.id'
            ],
            'where' => ['student_id = ? AND course_id = ?', [
                $params->studentId,
                $params->courseId
            ]]
        ]);
        $user = $this->db->groupResults($user, 'email', ['roles', 'roleName']);

        if (isset($user[0])) {
            $user = $user[0];
        }

        try {
            (new Moodle())->moodleEnrollUser(
                $user->email,
                $user->moodle_id,
                // Roles: 3 = Teacher, 5 = Student
                in_array('professor', $user->roles) ? 3 : 5
            );
        } catch(Exception $e){
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        return new AjaxResponse([
            "isEnrolled" => true,
            "isEnrollmentActive" => true,
            "fractions" => [(object) [
                "fractionId" => isset($params->fractionId) ? $params->fractionId : '0',
                "fractionEnrollmentId" => isset($params->fractionEnrollmentId) ? $params->fractionEnrollmentId : $return->lastInsertId,
                "fractionDateTime" => date("Y-m-d H:i:s")
            ]],
            // "userId" => $params->studentId,
            // "courseId" => $params->courseId,
        ]);
    }

    function isUserEnrolledToCourse($params){
        return $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'id',
            'table' => 'admin_course_enrollment',
            'where' => ['student_id = ? AND course_id = ?', [$params->studentId, $params->courseId]]
        ]);
    }

    function unenrollUserFromCourse($params){
        $return = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'student_id, course_id, email, moodle_id',
            'table' => 'admin_course_enrollment',
            'joins' => [
                'JOIN admin_users ON student_id = admin_users.id',
                'JOIN admin_courses ON course_id = admin_courses.id'
            ],
            'where' => ['admin_course_enrollment.id = ?', $params->fractionEnrollmentId]
        ]);

        try {
            (new Moodle())->moodleUnEnrollUser(
                $return->email,
                $return->moodle_id
            );
        } catch(Exception $e){
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        $gradeExists = $this->gradeExists($params->fractionEnrollmentId);
        if($gradeExists) {
            $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_course_enrollment',
                'columns' => 'active',
                'values' => 0,
                'where' => ['id = ?', $params->fractionEnrollmentId]
            ]);
        } else {
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_course_enrollment',
                'where' => ['id = ?', $params->fractionEnrollmentId]
            ]);
        }

        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'active',
            'table' => 'admin_course_enrollment',
            'where' => ['student_id = ? AND course_id = ?',[
                $return->student_id, $return->course_id
            ]]
        ]);

        return new AjaxResponse([
            "isEnrollmentActive" => isset($return[0]->active) ? +$return[0]->active : false,
            "isEnrolled" => count($return)
        ]);
    }

    function gradeExists($id) {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'grade',
            'table' => 'admin_course_enrollment',
            'where' => ['id = ?', $id]
        ]);

        return isset($return[0]->grade);
    }

    function updateStudentGradeForCourse($params){
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_course_enrollment',
            'columns' => 'grade, gradeSemester, gradeYear, date_time',
            'values' => [$params->grade, isset($params->gradeSemester) ? $params->gradeSemester : null, isset($params->gradeYear) ? $params->gradeYear : null, date("Y-m-d H:i:s")],
            'where' => ['id = ?', $params->courseEnrollmentId]
        ]);

        return new AjaxResponse([
            "courseEnrollmentId" => $params->courseEnrollmentId
        ]);
    }

    function sendEvaluationForms($params) {
        // Get Students
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'admin_users.id, email, language',
            'table' => 'admin_course_enrollment',
            'joins' => [
                'LEFT JOIN admin_users ON admin_course_enrollment.student_id = admin_users.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id'
            ],
            'where' => ['course_id = ? AND active = 1 AND roleId = 6', $params->courseId]
        ]);

        // Add Evaluation Form to Students
        $notificationOptions = [];
        foreach($return as $student) {
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_evaluations_pending',
                'columns' => 'forUserId, fromUserId, forCourseId',
                'values' => [
                    $params->userId,
                    $student->id,
                    $params->courseId
                ],
                'update' => true
            ]);

            // Prepare ids and emails per language preference
            if ($return) {
                if (!isset($notificationOptions[$student->language])) {
                    $notificationOptions[$student->language] = [
                        'ids' => [],
                        'emails' => []
                    ];
                }
                array_push($notificationOptions[$student->language]['ids'], $student->id);
                array_push($notificationOptions[$student->language]['emails'], $student->email);
            }
        }

        // Get Professor and Course Information
        $return = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => [
                'firstName',
                'lastName',
                'name_gr courseNameGr',
                'name_en courseNameEn'
            ],
            'table' => 'admin_course_enrollment',
            'joins' => [
                'JOIN admin_users ON admin_users.id = admin_course_enrollment.student_id',
                'JOIN admin_courses ON admin_courses.id = admin_course_enrollment.course_id'    
            ],
            'where' => ['student_id = ? AND course_id = ?', [$params->userId, $params->courseId]]
        ]);

        // Notify Students
        foreach ($notificationOptions as $key => $users) {
            (new NotificationService())->send([
                "toEmails" => $users['emails'],
                "forUserIds" => $users['ids'],
                "templateName" => 'userEvaluationFormPending',
                "language" => $key,
                "vars" => [
                    ['lastName', $return->lastName],
                    ['firstName', $return->firstName],
                    ['courseName'.ucfirst($key), $return->{'courseName'.ucfirst($key)}]
                ]
            ]);
        }

        return new AjaxResponse(true);
    }

    function getMyEvaluations() {
        $return = $this->db->sql([
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

        foreach ($return as $form) {
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

        return new AjaxResponse($return);
    }

    function getEvaluationReportData($params) {
        if (!$this->auth->authenticateOperation('getEvaluationReportDataForEveryone')) {
            $params->userId = $this->user->id;
        }
        $return = new stdClass();

        $whereString = [];
        $whereValues = [];
        if (isset($params->userId)) {
            array_push($whereString, 'forUserId = ?');
            array_push($whereValues, $params->userId);
        }
        if (isset($params->courseId)) {
            array_push($whereString, 'forCourseId = ?');
            array_push($whereValues, $params->courseId);
        }
        if (isset($params->yearFrom)) {
            array_push($whereString, 'YEAR(dateTime) >= ?');
            array_push($whereValues, $params->yearFrom);
        }
        if (isset($params->yearTo)) {
            array_push($whereString, 'YEAR(dateTime) <= ?');
            array_push($whereValues, $params->yearTo);
        }
        $whereString = implode(' AND ', $whereString);

        $return->courses = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                // ORIGINAL LINE
                // 'admin_evaluations.forCourseId id',
                // Had to add DISTINCT vs GROUP BY admin_courses.id below
                'DISTINCT admin_evaluations.forCourseId id',
                'admin_course_categories.code_en',
                'admin_course_categories.code_gr',
                'admin_courses.code',
                'admin_courses.name_en',
                'admin_courses.name_gr'
            ],
            'table' => 'admin_evaluations',
            'joins' => [
                'LEFT JOIN admin_courses ON admin_courses.id = admin_evaluations.forCourseId',
                'LEFT JOIN admin_course_categories ON admin_course_categories.id = admin_courses.category_id'
            ],
            'where' => $whereString ? [$whereString, $whereValues] : null,
            // 'group' => 'admin_courses.id',
            'order' => 'admin_course_categories.code_gr, admin_courses.code'
        ]);

        $return->users = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'forUserId id, firstName, lastName',
            'table' => 'admin_evaluations',
            'joins' => 'LEFT JOIN admin_users ON admin_evaluations.forUserId = admin_users.id',
            'where' => $whereString ? [$whereString, $whereValues] : null,
            'group' => 'admin_evaluations.forUserId',
            'order' => 'lastName, firstName'
        ]);

        $return->years = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'YEAR(dateTime) year',
            'table' => 'admin_evaluations',
            'where' => $whereString ? [$whereString, $whereValues] : null,
            'group' => 'year',
            'order' => 'year'
        ]);

        $reports = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'forUserId',
                'firstName',
                'lastName',
                'forCourseId',
                'admin_course_categories.code_en',
                'admin_course_categories.code_gr',
                'code',
                'admin_courses.name_en',
                'admin_courses.name_gr',
                'admin_courses.credits',
                'admin_courses.ects_credits',
                'evaluation'
            ],
            'table' => 'admin_evaluations',
            'joins' => [
                'LEFT JOIN admin_users ON forUserId = admin_users.id',
                'LEFT JOIN admin_courses ON forCourseId = admin_courses.id',
                'LEFT JOIN admin_course_categories ON admin_course_categories.id = admin_courses.category_id'
            ],
            'where' => $whereString ? [$whereString, $whereValues] : null,
            'order' => 'forUserId, forCourseId'
        ]);

        $firstIndex = null;
        $noResults = count($reports);
        for($i = 0; $i < $noResults; $i++) {
            if (!is_null($firstIndex) && $reports[$firstIndex]->forUserId === $reports[$i]->forUserId && $reports[$firstIndex]->forCourseId === $reports[$i]->forCourseId) {
                array_push($reports[$firstIndex]->evaluations, json_decode($reports[$i]->evaluation));
                unset($reports[$i]);
            } else {
                $firstIndex = $i;
                $reports[$firstIndex]->evaluations = [];
                array_push($reports[$firstIndex]->evaluations, json_decode($reports[$i]->evaluation));
                unset($reports[$firstIndex]->evaluation);
            }
        }
        $reports = array_values($reports);

        foreach($reports as $report) {
            // Produce list of Multiple Choice Available Answers
            $report->th = $report->evaluations[0][0]->children;
            // Create placeholder for Multiple Choice Questions / Answers
            $report->scores = new stdClass();
            // Produce list of both Text & Multiple Choice Questions
            $report->multipleChoiceQuestions = [];
            $report->textQuestions = [];
            foreach($report->evaluations[0] as $key => $question) {
                if ($question->type === "3") {
                    array_push($report->multipleChoiceQuestions, $question);
                } else {
                    array_push($report->textQuestions, $question);
                }
            }

            // Prepare Answers
            foreach($report->evaluations as $key => $questions) {
                foreach($questions as $question) {
                    // Prepare Answers to Multiple Choice Questions
                    if($question->type === "3") {
                        if(!isset($report->scores->{$question->questionId})) {
                            $report->scores->{$question->questionId} = (object) [
                                "noVotes" => new stdClass(),
                                "points" => 0
                            ];
                        }
                        foreach($question->children as $key => $child) {
                            if ($child->questionId === $question->answer) {
                                if (!isset($report->scores->{$question->questionId}->noVotes->{$child->questionId})) {
                                    $report->scores->{$question->questionId}->noVotes->{$child->questionId} = 0;
                                }
                                $report->scores->{$question->questionId}->noVotes->{$child->questionId}++;
                                $report->scores->{$question->questionId}->points += $key + 1;
                                break;
                            }
                        }
                    } else {
                        // Prepare Answers to Text Questions
                        foreach($report->textQuestions as $key => $questionTable) {
                            if (isset($question->answer) && $question->questionId === $questionTable->questionId) {
                                if(!isset($questionTable->answers)) {
                                    $questionTable->answers = [];
                                }
                                array_push($questionTable->answers, $question->answer);
                                break;
                            }
                            unset($questionTable->answer);
                        }
                    }
                }
            }
            // Calculate Point Averages for Multiple Choice Questions
            foreach($report->scores as $key => $question) {
                $report->scores->{$key}->points = $question->points / count($report->evaluations);
            }
            // Remove unnecessary already used data
            unset($report->evaluations);
        }
        $return->reports = $reports;

        $return->pendingReports = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'from_users.id fromUserId',
                'from_users.firstName fromUserFirstName',
                'from_users.lastName fromUserLastName',
                'for_users.id forUserId',
                'for_users.firstName forUserFirstName',
                'for_users.lastName forUserLastName',
                'forCourseId',
                'admin_course_categories.code_gr',
                'admin_course_categories.code_en',
                'code',
                'admin_courses.name_gr',
                'admin_courses.name_en'
              
            ],
            'table' => 'admin_evaluations_pending',
            'joins' => [
                'LEFT JOIN admin_users from_users ON from_users.id = admin_evaluations_pending.fromUserId',
                'LEFT JOIN admin_users for_users ON for_users.id = admin_evaluations_pending.forUserId',
                'LEFT JOIN admin_courses ON admin_courses.id = admin_evaluations_pending.forCourseId',
                'LEFT JOIN admin_course_categories ON admin_course_categories.id = admin_courses.category_id'
            ],
            'order' => 'fromUserId, forUserId'
        ]);
        $return->pendingReports = $this->db->groupResults($return->pendingReports, 'fromUserId', ['forUsers', ['forCourseId', 'code_gr', 'code_en', 'code', 'name_gr', 'name_en', 'forUserId', 'forUserFirstName', 'forUserLastName']]);

        return new AjaxResponse($return);
    }

    function removePendingEvaluation($params) {
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_evaluations_pending',
            'where' => ['forUserId = ? AND fromUserId = ? AND forCourseId = ?', [
                $params->forUserId, $params->fromUserId, $params->forCourseId, 
            ]]
        ]);

        return new AjaxResponse($return);
    }

    function getStudentsData($params){
        $whereString = 'roleId = 6 AND admin_program_enrollment.active = TRUE';
        if (isset($params->programId)) {
            $where = [
                ($whereString.= ' AND admin_programs.id = ?'),
                [$params->programId]
            ];
        } else {
            $where = $whereString;
        }
        $results = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id',
                'firstName',
                'lastName',
                'email',
                'photoURI',
                'admin_users.date_time dateTime',
                'admin_program_enrollment.program_id programId',
                'program_name_gr',
                'program_name_en',
                'COUNT(admin_course_enrollment.course_id) noCourses'
            ],
            'table' => 'admin_program_enrollment',
            'joins' => [
                'LEFT JOIN admin_programs ON admin_programs.id = admin_program_enrollment.program_id',
                'LEFT JOIN admin_users ON admin_program_enrollment.student_id = admin_users.id',
                'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id',
                'LEFT JOIN admin_course_enrollment ON admin_course_enrollment.student_id = admin_users.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id'
            ],
            'where' => $where,
            'group' => 'admin_users.id, admin_program_enrollment.program_id',
            'order' => 'dateTime DESC, lastName, firstName'
        ]);
        $results = JSFDates($results, 'dateTime');
        $results = $this->db->groupResults($results, 'id', ['programs', ['programId', 'program_name_gr', 'program_name_en', 'noCourses']]);

        $urlGenerator = new UrlGenerator();
        foreach ($results as $student) {
            $student->pdfUrlEnglish = $urlGenerator->getURL("getStudentTranscriptPDF", ["id" => $student->id, "lang" => "english"]);
            $student->pdfUrlEnglishECTS = $urlGenerator->getURL("getStudentTranscriptPDF", ["id" => $student->id, "lang" => "englishECTS"]);
            $student->pdfUrlGreek = $urlGenerator->getURL("getStudentTranscriptPDF", ["id" => $student->id, "lang" => "greek"]);
        }

        return new AjaxResponse($results);
    }

    function getCoursesForStudent($params){
        if (!$params->id || !$this->auth->authenticateOperation('getCoursesForStudentOtherThanSelf')) {
            $params->id = $this->user->id;
        }
        $columns = [
            'admin_course_enrollment.id',
            'course_id courseId',
            'courseFractionId fractionId',
            'label fractionLabel',
            'admin_course_categories.code_gr',
            'admin_course_categories.code_en',
            'admin_courses.code',
            'admin_courses.name_gr',
            'admin_courses.name_en',
            '(SELECT COUNT(student_id) FROM admin_course_enrollment LEFT JOIN admin_user_roles ON admin_course_enrollment.student_id = admin_user_roles.userId WHERE course_id = courseId AND roleId = 6 AND active = TRUE GROUP BY course_id) noActiveStudents',
            'moodle_id',
            'date_time',
            'gradeSemester',
            'gradeYear'
        ];
        if ($this->auth->authenticateOperation('getCoursesForStudentIncludeGrade')) {
            array_push($columns, 'grade');
        }

        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => $columns,
            'table' => 'admin_course_enrollment',
            'joins' => [
                'JOIN admin_courses ON admin_course_enrollment.course_id = admin_courses.id',
                'JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
                'LEFT JOIN admin_course_fractions ON admin_course_fractions.id = courseFractionId'
            ],    
            'where' => ['student_id = ?', $params->id],
            'order' => 'course_id'
        ]);
        $return = JSFDates($return, 'date_time');
        $return = $this->db->groupResults($return, 'courseId', [
            ['fractions', ['fractionId', 'fractionLabel']]
        ]);

        // Sort User Courses Data
        function sortUserCoursesData($a, $b) {
            $aScore = strtotime($a->date_time);
            $bScore = strtotime($b->date_time);
            return $bScore <=> $aScore;
        }
        usort($return, 'sortUserCoursesData');

        return new AjaxResponse($return);
    }

    function deleteStudent($params) {
        try {
            // Delete Course Enrollment Information
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_course_enrollment',
                'where' => ['student_id = ?', $params->studentId]
            ]);

            // Delete Program Enrollment Information
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_program_enrollment',
                'where' => ['student_id = ?', $params->studentId]
            ]);

            // Remove Student Role from User
            $this->db->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_user_roles',
                'where' => ['userId = ? AND roleId = 6', $params->studentId]
            ]);
    
            return new AjaxResponse(["id" => $params->studentId]);
        } catch(Exception $e) {
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }
    }

    function deleteTeachingScheduleItem($params){
        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_schedule',
            'where' => ['id = ?', $params->id]
        ]);

        return $this->getTeachingSchedule($params);
    }

    function saveTeachingScheduleItem($params){
        $course = explode('|', $params->scheduleItemInputModel->course);
        $courseId = isset($course[0]) ? $course[0] : null;
        $courseFractionId = isset($course[1]) ? $course[1] : null;

        $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_schedule',
            'columns' => 'id, dateTime, courseId, courseFractionId',
            'values' => [
                isset($params->scheduleItemInputModel->id) ? $params->scheduleItemInputModel->id : null,
                $params->scheduleItemInputModel->dateTimeAsString,
                $courseId,
                $courseFractionId
            ],
            'update' => true
        ]);

        return $this->getTeachingSchedule($params);
    }

    function getTeachingSchedule($params){
        // Get Programs
        $programs = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'id',
                'program_name_gr',
                'program_name_en',
                'number_of_semesters'
            ],
            'table' => 'admin_programs'
        ]);

        // Get Courses
        if (isset($params->filter->programId)) {
            $where = 'active';
        } else {
            $where = ['active AND program_id = ?', (int)$params->filter->programId];
        }
        $courses = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_courses.id',
                'program_id',
                'admin_course_categories.code_gr',
                'admin_course_categories.code_en',
                'admin_courses.code',
                'admin_course_fractions.id AS fractionId',
                'admin_course_fractions.label AS fractionLabel',
                'admin_courses.name_gr',
                'admin_courses.name_en'
            ],
            'table' => 'admin_courses',
            'joins' => [
                'LEFT JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
                'LEFT JOIN admin_course_fractions ON admin_courses.id = admin_course_fractions.courseId'
            ],
            'where' => $where,
            'order' => 'admin_course_categories.code_gr, admin_courses.code, fractionLabel'
        ]);

        // Get Schedule Items
        $whereString = ['TRUE'];
        $whereParams = [];
        if(isset($params->filter->startOn)){
            array_push($whereString, 'dateTime >= ?');
            array_push($whereParams, $params->filter->startOnAsString);
        }
        if($this->auth->authenticateOperation('getTeachingScheduleAllCourses')) {
            if(isset($params->filter->myCoursesOnly) && $params->filter->myCoursesOnly === true){
                array_push($whereString, 'student_id = ?');
                array_push($whereParams, isset($params->filter->id) ? (int)$params->filter->id : $this->user->id);
            }
        } else {
            array_push($whereString, 'admin_course_enrollment.active AND student_id = '.$this->user->id);
        }
        if(isset($params->filter->programId)){
            array_push($whereString, 'program_id = ?');
            array_push($whereParams, $params->filter->programId);
        }
        if(count($params->filter->years)){
            $whereYear = ['(year = 0 OR year IS NULL)'];
            foreach($params->filter->years as $i=>$year){
                if($year){
                    array_push($whereYear, 'year = ?');
                    array_push($whereParams, $i + 1);
                }
            }
            $whereYear = implode(' OR ', $whereYear);
            array_push($whereString, '('.$whereYear.')');
        }
        $where = [implode(' AND ', $whereString), $whereParams];
        $scheduleItems = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                // ORIGINAL LINE
                // 'admin_schedule.id',
                // Had to add DISTINCT vs GROUP BY course_id, dateTime below
                // Potential problem because of the lack of DISTINCT dateTime
                'DISTINCT admin_schedule.id',
                'dateTime',
                'admin_schedule.courseId',
                'admin_course_fractions.id AS fractionId',
                'admin_course_fractions.label AS fractionLabel',
                'admin_course_categories.code_gr',
                'admin_course_categories.code_en',
                'admin_courses.code',
                'admin_courses.name_gr',
                'admin_courses.name_en',
                'moodle_id'
            ],
            'table' => 'admin_schedule',
            'joins' => [
                'JOIN admin_courses ON admin_schedule.courseId = admin_courses.id',
                'LEFT JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
                'LEFT JOIN admin_course_fractions ON admin_schedule.courseFractionId = admin_course_fractions.id',
                'LEFT JOIN admin_course_enrollment ON admin_course_enrollment.course_id = admin_schedule.courseId'
            ],
            'where' => $where,
            // 'group' => 'course_id, dateTime',
            'order' => 'DATETIME, courseId ASC'
        ]);
        $scheduleItems = JSFDates($scheduleItems, 'dateTime');

        return new AjaxResponse([
            "programs" => $programs,
            "courses" => $courses,
            "scheduleItems" => $scheduleItems
        ]);
    }

    function getChapelSchedule($params){
        $return = [
            "categories" => $this->db->sql([
                'statement' => 'SELECT',
                'columns' => 'id, description',
                'table' => 'admin_chapel_categories'
            ]),
            "schedule" => $this->db->sql([
                'statement' => 'SELECT',
                'columns' => [
                    'admin_chapel_schedule.id',
                    'dateTime',
                    'admin_chapel_schedule.categoryId',
                    'admin_chapel_categories.description AS categoryDescription',
                    'admin_chapel_schedule.description',
                    'status'                
                ],
                'table' => 'admin_chapel_schedule',
                'joins' => 'JOIN admin_chapel_categories ON categoryId = admin_chapel_categories.id'
            ])
        ];
        $return["schedule"] = JSFDates($return["schedule"], 'dateTime');

        return new AjaxResponse($return);
    }

    function deleteChapelScheduleItem($params){
        $return = $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_chapel_schedule',
            'where' => ['id = ?', $params->id]
        ]);

        return $this->getChapelSchedule($return);
    }

    function saveChapelScheduleItem($params){
        $return = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_chapel_schedule',
            'columns' => 'id, dateTime, categoryId, description, status',
            'values' => [
                isset($params->scheduleItemInputModel->id) ? $params->scheduleItemInputModel->id : null,
                $params->scheduleItemInputModel->dateTimeAsString,
                $params->scheduleItemInputModel->categoryId,
                $params->scheduleItemInputModel->description,
                $params->scheduleItemInputModel->status
            ],
            'update' => true
        ]);

        return $this->getChapelSchedule($return);
    }

    function getAttendanceByCourseIdData($params){
        // Needs to be rewritten since there's no `roles` column in `admin_users` table
        // $return = $this->db->sql1([
        //     'statement' => 'SELECT',
        //     'columns' => 'id, code_gr, name_gr, code_en, name_en',
        //     'table' => 'admin_courses',
        //     'where' => ['id = ?', $params->courseId]
        // ]);

        // $attendances = $this->db->sql([
        //     'statement' => 'SELECT',
        //     'columns' => [
        //         'admin_course_attendance.id',
        //         'admin_course_enrollment.student_id',
        //         'firstName',
        //         'lastName',
        //         'admin_course_enrollment.course_id',
        //         'attendance_first',
        //         'attendance_second',
        //         'attendance_third',
        //         'attendance_fourth',
        //         'admin_course_attendance.date_time'
        //     ],
        //     'table' => 'admin_course_enrollment',
        //     'joins' => [
        //         'LEFT JOIN admin_course_attendance ON admin_course_enrollment.student_id = admin_course_attendance.student_id',
        //         'JOIN (SELECT id, firstName, lastName FROM admin_users WHERE roles != "professor") AS filtered_admin_users ON admin_course_enrollment.student_id = filtered_admin_users.id'
        //     ],
        //     'where' => ['admin_course_enrollment.course_id = ?', $params->courseId],
        //     'order' => 'date_time DESC'
        // ]);

        // $students = [];
        // for($i = 0; $i < count($attendances); $i++) {
        //     if(!array_key_exists($attendances[$i]->student_id,$students)){
        //         $students[$attendances[$i]->student_id] = clone $attendances[$i];
        //         $students[$attendances[$i]->student_id]->id = null;
        //         $students[$attendances[$i]->student_id]->attendance_first = null;
        //         $students[$attendances[$i]->student_id]->attendance_second = null;
        //         $students[$attendances[$i]->student_id]->attendance_third = null;
        //         $students[$attendances[$i]->student_id]->attendance_fourth = null;
        //         $students[$attendances[$i]->student_id]->date_time = null;
        //     }
        // }

        // function getAttendanceItemsForStudents($remainingStudents){
        //     $attendanceItemsForRemainingStudents = [];
        //     foreach($remainingStudents as $student){
        //         array_push($attendanceItemsForRemainingStudents, $student);
        //     }
        //     return $attendanceItemsForRemainingStudents;
        // }

        // $return->attendances = [];
        // $current_date_students = [];
        // $dates = [];

        // if(count($attendances) && explode(' ', $attendances[0]->date_time)[0] !== date("Y-m-d")){
        //     array_push($return->attendances,getAttendanceItemsForStudents($students));
        // }

        // for($i = 0; $i < count($attendances); $i++) {
        //     $current_date = explode(' ', $attendances[$i]->date_time)[0];
        //     if($current_date === ''){
        //         break;
        //     } elseif (!array_key_exists($current_date, $dates)) {
        //         $dates[$current_date] = true;
        //         foreach($current_date_students as $student){
        //             array_push($return->attendances[count($dates) - 2], $student);
        //         }
        //         $current_date_students = $students;
        //         array_push($return->attendances,[]);
        //     }
        //     array_push($return->attendances[count($return->attendances) - 1], $attendances[$i]);
        //     unset($current_date_students[$attendances[$i]->student_id]);
        // }
        // $return->attendances[count($return->attendances) - 1] = array_merge($return->attendances[count($return->attendances) - 1], getAttendanceItemsForStudents($current_date_students));

        // return new AjaxResponse($return);
    }

    function updateAttendance($params){
        switch($params->period){
            case 1:
                $period = 'attendance_first';
                break;
            case 2:
                $period = 'attendance_second';
                break;
            case 3:
                $period = 'attendance_third';
                break;
            case 4:
                $period = 'attendance_fourth';
                break;
        }
        if(isset($params->attendanceItemId)){
            $statement = 'UPDATE';
            $return = $this->db->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_course_attendance',
                'columns' => $period.', date_time',
                'values' => [$params->attendance, isset($params->dateTime) ? $params->dateTime : date("Y-m-d H:i:s")],
                'where' => ['id = ?', $params->attendanceItemId]
            ]);
        } else {
            $statement = 'INSERT';
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_course_attendance',
                'columns' => 'student_id, course_id, '.$period.', date_time',
                'values' => [
                    $params->studentId,
                    $params->courseId,
                    $params->attendance,
                    isset($params->dateTime) ? $params->dateTime : date("Y-m-d H:i:s")
                ],
                'update' => true
            ]);
        }

        return new AjaxResponse([
            "attendanceItem" => (object) [
                "id" => isset($params->attendanceItemId) ? $params->attendanceItemId : $return->lastInsertId,
                "student_id" => $params->studentId,
                "course_id" => $params->courseId,
                $period => $params->attendance
            ],
            "statement" => $statement
        ]);

    }
}