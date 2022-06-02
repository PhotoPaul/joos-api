<?

class Schedule {
    function __construct(){
        global $session;

        $this->db = $session->db;
    }

    function getScheduleRules(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_schedule_settings.id',
                'name',
                'programId',
                'program_name_gr AS programNameGr',
                'program_name_en AS programNameEn',
                'courseId',
                'CONCAT_WS(" ", admin_courses.code, admin_course_categories.code_gr) AS courseCodeGr',
                'CONCAT_WS(" ", admin_courses.code, admin_course_categories.code_en) AS courseCodeEn',
                'admin_courses.name_gr AS courseNameGr',
                'admin_courses.name_en AS courseNameEn',
                'professorId',
                'CONCAT_WS(", ", lastName, firstName) AS fullName',
                'available',
                'startDate',
                'endDate'
            ],
            'table' => 'admin_schedule_settings',
            'joins' => [
                'LEFT JOIN admin_programs ON admin_schedule_settings.programId = admin_programs.id', 
                'LEFT JOIN admin_courses ON admin_schedule_settings.courseId = admin_courses.id',
                'LEFT JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
                'LEFT JOIN admin_users ON admin_schedule_settings.professorId = admin_users.id'
            ]
        ]);        

        return new AjaxResponse($return);
    }

    function getSchedulePrograms(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id, program_name_gr AS nameGr, program_name_en AS nameEn',
            'table' => 'admin_programs'
        ]);        

        return new AjaxResponse($return);
    }

    function getScheduleCourses(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_courses.id',
                'CONCAT_WS(" ", admin_course_categories.code_gr, CODE) AS codeGr',
                'CONCAT_WS(" ", admin_course_categories.code_en, code) AS codeEn',
                'admin_courses.name_gr AS nameGr',
                'admin_courses.name_en AS nameEn'
            ],
            'table' => 'admin_courses',
            'joins' => 'JOIN admin_course_categories ON admin_courses.category_id = admin_course_categories.id',
            'where' => 'active = TRUE'
        ]);        

        return new AjaxResponse($return);
    }
    
    function getScheduleProfessors(){
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id, CONCAT_WS(", ", lastName, firstName) AS fullName',
            'table' => 'admin_users',
            'joins' => 'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users.id',
            'where' => 'admin_user_roles.roleId = 7',
            'order' => 'lastName, firstName'
        ]);        
    
        return new AjaxResponse($return);
    }
}