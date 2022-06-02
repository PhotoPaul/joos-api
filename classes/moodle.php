<?php

class Moodle {
	function __construct() {
        ini_set('default_socket_timeout', 900);
        $this->username = MODDLE_USERNAME;
        $this->password = MODDLE_PASSWORD;
        $this->service = MODDLE_SERVICE;
        $this->token = MODDLE_TOKEN;
        $this->url = MODDLE_URL;
	}

	function moodleGetToken(){
        $this->token = json_decode(file_get_contents($this->url."/login/token.php?"."username=".$this->username."&password=".$this->password."&service=".$this->service))->token;
        echo $this->token; die();
    }

    function moodle($function, $params, $overrideDev = false) {
        global $prod;
        if ($prod || $overrideDev) {
            $url = $this->url."/webservice/rest/server.php?wstoken=".$this->token."&wsfunction=".$function."&moodlewsrestformat=json".$params;
            return json_decode(file_get_contents($url));
        } else {
            return new AjaxError(__METHOD__.': Don\'t mess with LIVE Moodle Database if running in Dev environment');
        }
    }

    function moodleGetUserId($email) {
        $params = "&criteria[0][key]=email";
        $params.= "&criteria[0][value]=".$email;
        $result = $this->moodle('core_user_get_users', $params);

        if(isset($result->users) && count($result->users)) {
            return $result->users[0]->id;
        } else {
            return false;
        }
    }

    function moodleCreateUser($firstName, $lastName, $email, $password = "Aa12345678") {
        $params = "&users[0][username]=".strtolower($email);
        $params.= "&users[0][password]=".rawurlencode($password);
        $params.= "&users[0][firstname]=".rawurlencode($firstName);
        $params.= "&users[0][lastname]=".rawurlencode($lastName);
        $params.= "&users[0][email]=".strtolower($email);
        $result = $this->moodle('core_user_create_users', $params);

        return new AjaxResponse($result);
    }

    function moodleResetPassword($email, $newPassword = "Aa12345678") {
        $params = "&criteria[0][key]=email";
        $params.= "&criteria[0][value]=".$email;
        $result = $this->moodle('core_user_get_users', $params);

        if(isset($result->users) && count($result->users)) {
            $moodleUserId = $result->users[0]->id;
            $params = "&users[0][id]=".$moodleUserId;
            $params.= "&users[0][password]=".rawurlencode($newPassword);
            $result = $this->moodle('core_user_update_users', $params);
            return new AjaxResponse($result);
        }

        return new AjaxResponse(null, false, __METHOD__.': User <b>'.$email.'</b> not found');
    }

    function moodleDeleteUser($email) {
        $params = "&criteria[0][key]=email";
        $params.= "&criteria[0][value]=".$email;
        $result = $this->moodle('core_user_get_users', $params);

        if(isset($result->users) && count($result->users)) {
            $moodleUserId = $result->users[0]->id;
            $params = "&userids[0]=".$moodleUserId;
            $result = $this->moodle('core_user_delete_users', $params);
            return new AjaxResponse($result);
        }

        return new AjaxResponse(null, false, __METHOD__.': User <b>'.$email.'</b> not found');
    }

    function moodleEnrollUser($email, $moodleCourseId, $role = 5) {
        // Roles: 3 = Teacher, 5 = Student
        $moodleUserId = $this->moodleGetUserId($email);
        $params = "&enrolments[0][roleid]=".$role;
        $params.= "&enrolments[0][userid]=".$moodleUserId;
        $params.= "&enrolments[0][courseid]=".$moodleCourseId;
        $result = $this->moodle('enrol_manual_enrol_users', $params);
    }
    
    function moodleUnEnrollUser($email, $moodleCourseId) {
        $moodleUserId = $this->moodleGetUserId($email);
        $params = "&enrolments[0][userid]=".$moodleUserId;
        $params.= "&enrolments[0][courseid]=".$moodleCourseId;
        $result = $this->moodle('enrol_manual_unenrol_users', $params);
    }

    function moodleGetCourseCategories() {
        $result = $this->moodle('core_course_get_categories', null, true);
        return new AjaxResponse($result);
    }

    function moodleSaveCourse($id, $fullname, $shortname, $categoryid) {
        $params = "&courses[0][fullname]=".rawurlencode($shortname." ".$fullname);
        $params.= "&courses[0][shortname]=".rawurlencode($shortname);
        $params.= "&courses[0][categoryid]=".rawurlencode($categoryid);
        if ($id) {
            $params.= "&courses[0][id]=".rawurlencode($id);
            $result = $this->moodle('core_course_update_courses', $params, true);
        } else {
            $result = $this->moodle('core_course_create_courses', $params, true);
        }
        return new AjaxResponse($result);
    }
}