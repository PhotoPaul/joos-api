<?

class Applications {
    function __construct() {
        global $session;

        $this->db = $session->db->dbObject;
        $this->db2 = $session->db;
        $this->auth = $session->authenticationService;
        $this->user = $session->authenticationService->user;
    }

    function getUserApplicationFormModel($params) {
        $return = ["accessControl" => $this->db2->sql1([
            'statement' => 'SELECT',
            'columns' => 'viewPath, editPath, viewRoles, editRoles',
            'table' => 'admin_applications',
            'where' => ['id = ?', $params->applicationId]
        ])];

        if($params->applicationId === 1) { // Greek Personal
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'firstName',
                'lastName',
                'birthDate',
                'birthPlace',
                'email',
                'phone',
                'occupation',
                'greekCitizen',
                'greekIdNumber',
                'greekSsn',
                'irsOffice',
                'citizenship',
                'euCitizen',
                'passportNumber',
                'residencePermit',
                'familyStatus',
                'familySpouseFirstName',
                'familySpouseLastName',
                'familyKids',
                'familyKidsNamesAges',
                'address',
                'city',
                'zipCode',
                'country',
                'guardianFirstName',
                'guardianLastName',
                'guardianOccupation',
                'guardianEmail',
                'guardianPhone',
                'guardianAddressSame',
                'guardianAddress',
                'guardianCity',
                'guardianZipCode',
                'guardianCountry',
                'guardianOpinion'
            ];
            $joins = [
                'JOIN admin_users ON userId = admin_users.id',
                'LEFT JOIN admin_applications_personal ON admin_applications_personal.userId = admin_user_applications.userId'
            ];
        } elseif($params->applicationId === 2) { // Greek Education
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'elementaryName',
                'elementaryGraduationYear',
                'middleSchoolName',
                'middleSchoolGraduationYear',
                'secondarySchoolName',
                'secondarySchoolGraduationYear',
                'secondarySchoolDiscipline',
                'greek',
                'english',
                'communityCollegeName',
                'communityCollegeGraduationYear',
                'communityCollegeDiscipline',
                'collegeName',
                'collegeGraduationYear',
                'collegeDiscipline',
                'graduateSchoolName',
                'graduateSchoolGraduationYear',
                'graduateSchoolDiscipline'
            ];
            $joins = 'LEFT JOIN admin_applications_education ON admin_applications_education.userId = admin_user_applications.userId';
        } elseif($params->applicationId === 3) { // Greek Health
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'tonsillitis',
                'chickenPox',
                'bronchialAsthma',
                'diphtheria',
                'epilepsy',
                'rubella',
                'measles',
                'yellowFever',
                'meningitis',
                'mumps',
                'polio',
                'cholera',
                'heartAbnormality',
                'otherDiseases',
                'otherDiseasesDetails',
                'vaccineDiphtheria',
                'vaccinePertussis',
                'vaccineTetanus',
                'vaccineSmallpox',
                'vaccineRubella',
                'vaccineMeasles',
                'vaccineMumps',
                'vaccinePolio',
                'vaccineCholera',
                'otherVaccines',
                'otherVaccinesDetails',
                'drugsUse',
                'drugsUseDetails',
                'currentDiseases',
                'currentDiseasesDetails',
                'currentSymptoms',
                'currentSymptomsDetails',
                'currentMedicines',
                'currentMedicinesDetails',
                'foodAllergy',
                'foodAllergyDetails',
                'firstEmergencyContactFirstName',
                'firstEmergencyContactLastName',
                'firstEmergencyContactPhone',
                'secondEmergencyContactFirstName',
                'secondEmergencyContactLastName',
                'secondEmergencyContactPhone',
                'doctor',
                'doctorFirstName',
                'doctorLastName',
                'doctorPhone',
                'doctorAddress',
                'doctorCity',
                'doctorZipCode',
                'doctorCountry',
                'doctorContactApproval',
                'otherDoctorContactApproval'
            ];
            $joins = 'LEFT JOIN admin_applications_health ON admin_applications_health.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->tonsillitis = booleanize($return["application"]->tonsillitis);
            $return["application"]->chickenPox = booleanize($return["application"]->chickenPox);
            $return["application"]->bronchialAsthma = booleanize($return["application"]->bronchialAsthma);
            $return["application"]->diphtheria = booleanize($return["application"]->diphtheria);
            $return["application"]->epilepsy = booleanize($return["application"]->epilepsy);
            $return["application"]->rubella = booleanize($return["application"]->rubella);
            $return["application"]->measles = booleanize($return["application"]->measles);
            $return["application"]->yellowFever = booleanize($return["application"]->yellowFever);
            $return["application"]->meningitis = booleanize($return["application"]->meningitis);
            $return["application"]->mumps = booleanize($return["application"]->mumps);
            $return["application"]->polio = booleanize($return["application"]->polio);
            $return["application"]->cholera = booleanize($return["application"]->cholera);
            $return["application"]->heartAbnormality = booleanize($return["application"]->heartAbnormality);
            $return["application"]->vaccineDiphtheria = booleanize($return["application"]->vaccineDiphtheria);
            $return["application"]->vaccinePertussis = booleanize($return["application"]->vaccinePertussis);
            $return["application"]->vaccineTetanus = booleanize($return["application"]->vaccineTetanus);
            $return["application"]->vaccineSmallpox = booleanize($return["application"]->vaccineSmallpox);
            $return["application"]->vaccineRubella = booleanize($return["application"]->vaccineRubella);
            $return["application"]->vaccineMeasles = booleanize($return["application"]->vaccineMeasles);
            $return["application"]->vaccineMumps = booleanize($return["application"]->vaccineMumps);
            $return["application"]->vaccinePolio = booleanize($return["application"]->vaccinePolio);
            $return["application"]->vaccineCholera = booleanize($return["application"]->vaccineCholera);
            $return["application"]->doctorContactApproval = booleanize($return["application"]->doctorContactApproval);
            $return["application"]->otherDoctorContactApproval = booleanize($return["application"]->otherDoctorContactApproval);
            return new AjaxResponse($return);
        } elseif($params->applicationId === 4) { // Greek Christian Life
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'churchName',
                'churchMember',
                'churchMemberHowLong',
                'ministryTalent',
                'ministryExperience',
                'testimony',
                'statementOfFaithApproval'
            ];
            $joins = 'LEFT JOIN admin_applications_christian_life ON admin_applications_christian_life.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->statementOfFaithApproval = booleanize($return["application"]->statementOfFaithApproval);
            return new AjaxResponse($return);
        } elseif($params->applicationId === 5) { // Greek References
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'referenceId',
                'firstName',
                'lastName',
                'email',
                'phone',
                'address',
                'city',
                'zipCode',
                'country',
                'priority',
                'documentId'
            ];
            $joins = 'LEFT JOIN admin_applications_documents ON referenceId = documentId';
            $results = $this->db2->sql([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_applications_references',
                'joins' => $joins,
                'where' => [
                    'admin_applications_references.userId = ? AND (documentTypeId = 6 OR documentTypeId is NULL)',
                    $params->userId
                ],
                'order' => 'priority'
            ]);
            $return["application"] = [
                (object) ["priority" => "0"],
                (object) ["priority" => "1"],
                (object) ["priority" => "2"]
            ];
            $urlGenerator = new UrlGenerator();
            foreach($results as $result){
                if($result->documentId) {
                    $result->documentUrl = $urlGenerator->getURL("getFile", ["filename" => $result->documentId]);
                }
                $return["application"][$result->priority] = $result;
            }
            return new AjaxResponse($return);
        } elseif($params->applicationId === 6) { // Greek Financial
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'studentPackage',
                'financialApproval',
                'selfPaid',
                'sponsors',
                'sponsorsTotal',
                'debtApproval',
                'deposit'
            ];
            $joins = 'LEFT JOIN admin_applications_financial ON admin_applications_financial.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->financialApproval = booleanize($return["application"]->financialApproval);
            $return["application"]->debtApproval = booleanize($return["application"]->debtApproval);
            return new AjaxResponse($return);
        } elseif($params->applicationId === 7) { // ISP Personal
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'firstName',
                'lastName',
                'birthDate',
                'birthPlace',
                'email',
                'phone',
                'occupation',
                'citizenship',
                'ssn',
                'euCitizen',
                'passportNumber',
                'residencePermit',
                'familyStatus',
                'familySpouseFirstName',
                'familySpouseLastName',
                'familyKids',
                'familyKidsNamesAges',
                'address',
                'city',
                'zipCode',
                'country',
                'permanentAddressDifferent',
                'permanentAddress',
                'permanentCity',
                'permanentZipCode',
                'permanentCountry',
                'guardianFirstName',
                'guardianLastName',
                'guardianOccupation',
                'guardianEmail',
                'guardianPhone',
                'guardianAddressSame',
                'guardianAddress',
                'guardianCity',
                'guardianZipCode',
                'guardianCountry',
                'guardianOpinion'
            ];
            $joins = [
                'JOIN admin_users ON userId = admin_users.id',
                'LEFT JOIN admin_applications_isp_personal ON admin_applications_isp_personal.userId = admin_user_applications.userId'
            ];
        } elseif($params->applicationId === 8) { // ISP Education
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'secondarySchoolName',
                'secondarySchoolGraduationYear',
                'secondarySchoolAddress',
                'secondarySchoolCity',
                'secondarySchoolZipCode',
                'secondarySchoolCountry',
                'englishHighSchool',
                'englishCertificate',
                'communityCollegeName',
                'communityCollegeDatesAttended',
                'communityCollegeDiscipline',
                'communityCollegeAddress',
                'communityCollegeCity',
                'communityCollegeZipCode',
                'communityCollegeCountry',
                'collegeName',
                'collegeDatesAttended',
                'collegeDiscipline',
                'collegeAddress',
                'collegeCity',
                'collegeZipCode',
                'collegeCountry',
                'graduateSchoolName',
                'graduateSchoolDatesAttended',
                'graduateSchoolDiscipline',
                'graduateSchoolAddress',
                'graduateSchoolCity',
                'graduateSchoolZipCode',
                'graduateSchoolCountry',
                'dismissed',
                'dismissedDetails',
                'honors',
                'honorsDetails'
            ];
            $joins = 'LEFT JOIN admin_applications_isp_education ON admin_applications_isp_education.userId = admin_user_applications.userId';
        } elseif($params->applicationId === 9) { // ISP Health
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'drugsUse',
                'drugsUseDetails',
                'currentDiseases',
                'currentDiseasesDetails',
                'currentSymptoms',
                'currentSymptomsDetails',
                'currentMedicines',
                'currentMedicinesDetails',
                'foodAllergy',
                'foodAllergyDetails',
                'firstEmergencyContactFirstName',
                'firstEmergencyContactLastName',
                'firstEmergencyContactPhone',
                'secondEmergencyContactFirstName',
                'secondEmergencyContactLastName',
                'secondEmergencyContactPhone',
                'otherDoctorContactApproval'
            ];
            $joins = 'LEFT JOIN admin_applications_isp_health ON admin_applications_isp_health.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->otherDoctorContactApproval = booleanize($return["application"]->otherDoctorContactApproval);
            return new AjaxResponse($return);
        } elseif($params->applicationId === 10) { // ISP Christian Life
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'statementOfFaithApproval',
                'conviction',
                'convictionDetails',
                'ministryTalent',
                'ministryExperience',
                'familyBackground',
                'testimony',
                'spiritualGrowth',
                'reasonsToEnroll'
            ];
            $joins = 'LEFT JOIN admin_applications_isp_christian_life ON admin_applications_isp_christian_life.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->statementOfFaithApproval = booleanize($return["application"]->statementOfFaithApproval);
            return new AjaxResponse($return);
        } elseif($params->applicationId === 11) { // ISP References
            if(!$this->auth->authenticateOperation('getUserApplicationFormModelForOthers')) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'referenceId',
                'firstName',
                'lastName',
                'email',
                'phone',
                'address',
                'city',
                'zipCode',
                'country',
                'priority',
                'documentId'
            ];
            $joins = 'LEFT JOIN admin_applications_documents ON referenceId = documentId';
            $results = $this->db2->sql([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_applications_references',
                'joins' => $joins,
                'where' => [
                    'admin_applications_references.userId = ? AND (documentTypeId = 6 OR documentTypeId is NULL)',
                    $params->userId
                ],
                'order' => 'priority'
            ]);
            $return["application"] = [
                (object) ["priority" => "0"],
                (object) ["priority" => "1"]
            ];
            $urlGenerator = new UrlGenerator();
            foreach($results as $result){
                if($result->documentId) {
                    $result->documentUrl = $urlGenerator->getURL("getFile", ["filename" => $result->documentId]);
                }
                $return["application"][$result->priority] = $result;
            }
            return new AjaxResponse($return);
        } elseif($params->applicationId === 12) { // Supporting Documentation
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $return["application"] = $this->db2->sql([
                'statement' => 'SELECT',
                'columns' => [
                    'owner_id',
                    'filename',
                    'filesize',
                    'original_filename',
                    'original_mime_type',
                    'admin_applications_documents.documentTypeId',
                    'title_en',
                    'title_gr',
                    'date_time'
                ],
                'table' => 'admin_files',
                'joins' => [
                    'LEFT JOIN admin_applications_documents ON documentId = filename',
                    'LEFT JOIN admin_applications_document_types ON admin_applications_documents.documentTypeId = admin_applications_document_types.documentTypeId'
                ],
                'where' => ['owner_id = ? AND (viewRoles IS NULL OR viewRoles LIKE "%candidate%")', $params->userId]
            ]);

            // get Application Document Types
            $where = ['viewRoles IS NULL'];
            foreach ($this->user->roles as $role) {
                array_push($where, 'viewRoles LIKE "%'.$role->roleName.'%"');
            }
            $where = implode(' OR ', $where);

            $return["collections"] = $this->db2->sql([
                'statement' => 'SELECT',
                'columns' => 'documentTypeId, title_en, title_gr',
                'table' => 'admin_applications_document_types',
                'where' => $where
            ]);

            return new AjaxResponse($return);
        } elseif($params->applicationId === 20) { // ISP Applicant Classification
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'classificationId',
                'startingDate',
                'accommodationRequired',
                'visaRequired'
            ];
            $joins = 'LEFT JOIN admin_applications_isp_applicant_classification ON admin_applications_isp_applicant_classification.userId = admin_user_applications.userId';
        } elseif($params->applicationId === 21) { // ISP Financial
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'hidden',
                'tuition',
                'roomBoard',
                'paymentSchedule',
                'financialApproval',
                'selfPaid',
                'sponsors',
                'sponsorsTotal',
                'debtApproval',
                'applicationFee',
                'feeUrl'
            ];
            $joins = 'LEFT JOIN admin_applications_isp_financial ON admin_applications_isp_financial.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->hidden = booleanize($return["application"]->hidden);
            $return["application"]->financialApproval = booleanize($return["application"]->financialApproval);
            $return["application"]->debtApproval = booleanize($return["application"]->debtApproval);
            return new AjaxResponse($return);
        } elseif($params->applicationId === 22) { // ISP Application Fee
            if(!(
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthers') ||
                $this->auth->authenticateOperation('getUserApplicationFormModelForOthersFinancial')
            )) {
                $params->userId = $this->user->id;
            }
            $columns = [
                'applicationStatus',
                'hidden',
                'tuition',
                'roomBoard',
                'paymentSchedule',
                'financialApproval',
                'selfPaid',
                'sponsors',
                'sponsorsTotal',
                'debtApproval',
                'applicationFee',
                'feeUrl'
            ];
            $joins = 'LEFT JOIN admin_applications_isp_financial ON admin_applications_isp_financial.userId = admin_user_applications.userId';
            $return["application"] = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => $columns,
                'table' => 'admin_user_applications',
                'joins' => $joins,
                'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);
            $return["application"]->hidden = isset($return["application"]->hidden) ? booleanize($return["application"]->hidden) : null;
            $return["application"]->financialApproval = isset($return["application"]->financialApproval) ? booleanize($return["application"]->financialApproval) : null;
            $return["application"]->debtApproval = isset($return["application"]->debtApproval) ? booleanize($return["application"]->debtApproval) : null;
            return new AjaxResponse($return);
        }

        $return["application"] = $this->db2->sql1([
            'statement' => 'SELECT',
            'columns' => $columns,
            'table' => 'admin_user_applications',
            'joins' => $joins,
            'where' => ['admin_user_applications.userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
        ]);
    
        return new AjaxResponse($return);
    }

    function saveUserApplicationFormModel($params) {
        if(!isset($params->userId)){
            $params->userId = $this->user->id;
        }
        if($params->applicationId === 1) { // Greek Personal
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_personal',
                'columns' => [
                    'userId',
                    'birthDate',
                    'birthPlace',
                    'phone',
                    'occupation',
                    'greekCitizen',
                    'greekIdNumber',
                    'greekSsn',
                    'irsOffice',
                    'citizenship',
                    'euCitizen',
                    'passportNumber',
                    'residencePermit',
                    'familyStatus',
                    'familySpouseFirstName',
                    'familySpouseLastName',
                    'familyKids',
                    'familyKidsNamesAges',
                    'address',
                    'city',
                    'zipCode',
                    'country',
                    'guardianFirstName',
                    'guardianLastName',
                    'guardianOccupation',
                    'guardianEmail',
                    'guardianPhone',
                    'guardianAddressSame',
                    'guardianAddress',
                    'guardianCity',
                    'guardianZipCode',
                    'guardianCountry',
                    'guardianOpinion'
                ],
                'values' => [$params->userId, $params->application->birthDate, $params->application->birthPlace, $params->application->phone, $params->application->occupation, $params->application->greekCitizen, $params->application->greekIdNumber, $params->application->greekSsn, $params->application->irsOffice, $params->application->citizenship, $params->application->euCitizen, $params->application->passportNumber, $params->application->residencePermit, $params->application->familyStatus, $params->application->familySpouseFirstName, $params->application->familySpouseLastName, $params->application->familyKids, $params->application->familyKidsNamesAges, $params->application->address, $params->application->city, $params->application->zipCode, $params->application->country, $params->application->guardianFirstName, $params->application->guardianLastName, $params->application->guardianOccupation, $params->application->guardianEmail, $params->application->guardianPhone, $params->application->guardianAddressSame, $params->application->guardianAddress, $params->application->guardianCity, $params->application->guardianZipCode, $params->application->guardianCountry, $params->application->guardianOpinion],
                'update' => true
            ]);
        } elseif($params->applicationId === 2) { // Greek Education
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_education',
                'columns' => [
                    'userId',
                    'elementaryName',
                    'elementaryGraduationYear',
                    'middleSchoolName',
                    'middleSchoolGraduationYear',
                    'secondarySchoolName',
                    'secondarySchoolGraduationYear',
                    'secondarySchoolDiscipline',
                    'greek',
                    'english',
                    'communityCollegeName',
                    'communityCollegeGraduationYear',
                    'communityCollegeDiscipline',
                    'collegeName',
                    'collegeGraduationYear',
                    'collegeDiscipline',
                    'graduateSchoolName',
                    'graduateSchoolGraduationYear',
                    'graduateSchoolDiscipline'
                ],
                'values' => [$params->userId, $params->application->elementaryName, $params->application->elementaryGraduationYear, $params->application->middleSchoolName, $params->application->middleSchoolGraduationYear, $params->application->secondarySchoolName, $params->application->secondarySchoolGraduationYear, $params->application->secondarySchoolDiscipline, $params->application->greek, $params->application->english, $params->application->communityCollegeName, $params->application->communityCollegeGraduationYear, $params->application->communityCollegeDiscipline, $params->application->collegeName, $params->application->collegeGraduationYear, $params->application->collegeDiscipline, $params->application->graduateSchoolName, $params->application->graduateSchoolGraduationYear, $params->application->graduateSchoolDiscipline],
                'update' => true
            ]);
        } elseif($params->applicationId === 3) { // Greek Health
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_health',
                'columns' => [
                    'userId',
                    'tonsillitis',
                    'chickenPox',
                    'bronchialAsthma',
                    'diphtheria',
                    'epilepsy',
                    'rubella',
                    'measles',
                    'yellowFever',
                    'meningitis',
                    'mumps',
                    'polio',
                    'cholera',
                    'heartAbnormality',
                    'otherDiseases',
                    'otherDiseasesDetails',
                    'vaccineDiphtheria',
                    'vaccinePertussis',
                    'vaccineTetanus',
                    'vaccineSmallpox',
                    'vaccineRubella',
                    'vaccineMeasles',
                    'vaccineMumps',
                    'vaccinePolio',
                    'vaccineCholera',
                    'otherVaccines',
                    'otherVaccinesDetails',
                    'drugsUse',
                    'drugsUseDetails',
                    'currentDiseases',
                    'currentDiseasesDetails',
                    'currentSymptoms',
                    'currentSymptomsDetails',
                    'currentMedicines',
                    'currentMedicinesDetails',
                    'foodAllergy',
                    'foodAllergyDetails',
                    'firstEmergencyContactFirstName',
                    'firstEmergencyContactLastName',
                    'firstEmergencyContactPhone',
                    'secondEmergencyContactFirstName',
                    'secondEmergencyContactLastName',
                    'secondEmergencyContactPhone',
                    'doctor',
                    'doctorFirstName',
                    'doctorLastName',
                    'doctorPhone',
                    'doctorAddress',
                    'doctorCity',
                    'doctorZipCode',
                    'doctorCountry',
                    'doctorContactApproval',
                    'otherDoctorContactApproval'
                ],
                'values' => [$params->userId, $params->application->tonsillitis, $params->application->chickenPox, $params->application->bronchialAsthma, $params->application->diphtheria, $params->application->epilepsy, $params->application->rubella, $params->application->measles, $params->application->yellowFever, $params->application->meningitis, $params->application->mumps, $params->application->polio, $params->application->cholera, $params->application->heartAbnormality, $params->application->otherDiseases, $params->application->otherDiseasesDetails, $params->application->vaccineDiphtheria, $params->application->vaccinePertussis, $params->application->vaccineTetanus, $params->application->vaccineSmallpox, $params->application->vaccineRubella, $params->application->vaccineMeasles, $params->application->vaccineMumps, $params->application->vaccinePolio, $params->application->vaccineCholera, $params->application->otherVaccines, $params->application->otherVaccinesDetails, $params->application->drugsUse, $params->application->drugsUseDetails, $params->application->currentDiseases, $params->application->currentDiseasesDetails, $params->application->currentSymptoms, $params->application->currentSymptomsDetails, $params->application->currentMedicines, $params->application->currentMedicinesDetails, $params->application->foodAllergy, $params->application->foodAllergyDetails, $params->application->firstEmergencyContactFirstName, $params->application->firstEmergencyContactLastName, $params->application->firstEmergencyContactPhone, $params->application->secondEmergencyContactFirstName, $params->application->secondEmergencyContactLastName, $params->application->secondEmergencyContactPhone, $params->application->doctor, $params->application->doctorFirstName, $params->application->doctorLastName, $params->application->doctorPhone, $params->application->doctorAddress, $params->application->doctorCity, $params->application->doctorZipCode, $params->application->doctorCountry, $params->application->doctorContactApproval, $params->application->otherDoctorContactApproval],
                'update' => true
            ]);
        } elseif($params->applicationId === 4) { // Greek Christian Life
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_christian_life',
                'columns' => [
                    'userId',
                    'churchName',
                    'churchMember',
                    'churchMemberHowLong',
                    'ministryTalent',
                    'ministryExperience',
                    'testimony',
                    'statementOfFaithApproval'
                ],
                'values' => [$params->userId, $params->application->churchName, $params->application->churchMember, $params->application->churchMemberHowLong, $params->application->ministryTalent, $params->application->ministryExperience, $params->application->testimony, $params->application->statementOfFaithApproval],
                'update' => true
            ]);
        } elseif($params->applicationId === 5) { // Greek References
            $allValues[] = [
                $params->userId,
                1,
                isset($params->application[0]->referenceId) ? ($params->application[0]->referenceId === '0' ? null : $params->application[0]->referenceId) : null,
                isset($params->application[0]->firstName) ? $params->application[0]->firstName : '',
                isset($params->application[0]->lastName) ? $params->application[0]->lastName : '',
                isset($params->application[0]->email) ? $params->application[0]->email : '',
                isset($params->application[0]->phone) ? $params->application[0]->phone : '',
                isset($params->application[0]->address) ? $params->application[0]->address : '',
                isset($params->application[0]->city) ? $params->application[0]->city : '',
                isset($params->application[0]->zipCode) ? $params->application[0]->zipCode : '',
                isset($params->application[0]->country) ? $params->application[0]->country : '',
                isset($params->application[0]->priority) ? $params->application[0]->priority : ''
            ];
            $allValues[] = [
                $params->userId,
                1,
                isset($params->application[1]->referenceId) ? ($params->application[1]->referenceId === '0' ? null : $params->application[1]->referenceId) : null,
                isset($params->application[1]->firstName) ? $params->application[1]->firstName : '',
                isset($params->application[1]->lastName) ? $params->application[1]->lastName : '',
                isset($params->application[1]->email) ? $params->application[1]->email : '',
                isset($params->application[1]->phone) ? $params->application[1]->phone : '',
                isset($params->application[1]->address) ? $params->application[1]->address : '',
                isset($params->application[1]->city) ? $params->application[1]->city : '',
                isset($params->application[1]->zipCode) ? $params->application[1]->zipCode : '',
                isset($params->application[1]->country) ? $params->application[1]->country : '',
                isset($params->application[1]->priority) ? $params->application[1]->priority : ''
            ];
            $allValues[] = [
                $params->userId,
                1,
                isset($params->application[2]->referenceId) ? ($params->application[2]->referenceId === '0' ? null : $params->application[2]->referenceId) : null,
                isset($params->application[2]->firstName) ? $params->application[2]->firstName : '',
                isset($params->application[2]->lastName) ? $params->application[2]->lastName : '',
                isset($params->application[2]->email) ? $params->application[2]->email : '',
                isset($params->application[2]->phone) ? $params->application[2]->phone : '',
                isset($params->application[2]->address) ? $params->application[2]->address : '',
                isset($params->application[2]->city) ? $params->application[2]->city : '',
                isset($params->application[2]->zipCode) ? $params->application[2]->zipCode : '',
                isset($params->application[2]->country) ? $params->application[2]->country : '',
                isset($params->application[2]->priority) ? $params->application[2]->priority : ''
            ];
            foreach ($allValues as $values) {
                $result = $this->db2->sql([
                    'statement' => 'INSERT INTO',
                    'table' => 'admin_applications_references',
                    'columns' => [
                        'userId',
                        'formTemplateId',
                        'referenceId',
                        'firstName',
                        'lastName',
                        'email',
                        'phone',
                        'address',
                        'city',
                        'zipCode',
                        'country',
                        'priority'
                    ],
                    'values' => $values,
                    'update' => true
                ]);
            }
        } elseif($params->applicationId === 6) { // Greek Financial
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_financial',
                'columns' => [
                    'userId',
                    'studentPackage',
                    'financialApproval',
                    'selfPaid',
                    'sponsors',
                    'sponsorsTotal',
                    'debtApproval',
                    'deposit'
                ],
                'values' => [$params->userId, $params->application->studentPackage, $params->application->financialApproval, $params->application->selfPaid, $params->application->sponsors, $params->application->sponsorsTotal, $params->application->debtApproval, $params->application->deposit],
                'update' => true
            ]);
        } elseif($params->applicationId === 7) { // ISP Personal
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_personal',
                'columns' => [
                    'userId',
                    'birthDate',
                    'birthPlace',
                    'phone',
                    'occupation',
                    'citizenship',
                    'ssn',
                    'euCitizen',
                    'passportNumber',
                    'residencePermit',
                    'familyStatus',
                    'familySpouseFirstName',
                    'familySpouseLastName',
                    'familyKids',
                    'familyKidsNamesAges',
                    'address',
                    'city',
                    'zipCode',
                    'country',
                    'permanentAddressDifferent',
                    'permanentAddress',
                    'permanentCity',
                    'permanentZipCode',
                    'permanentCountry',
                    'guardianFirstName',
                    'guardianLastName',
                    'guardianOccupation',
                    'guardianEmail',
                    'guardianPhone',
                    'guardianAddressSame',
                    'guardianAddress',
                    'guardianCity',
                    'guardianZipCode',
                    'guardianCountry',
                    'guardianOpinion'
                ],
                'values' => [$params->userId, $params->application->birthDate, $params->application->birthPlace, $params->application->phone, $params->application->occupation, $params->application->citizenship, $params->application->ssn, $params->application->euCitizen, $params->application->passportNumber, $params->application->residencePermit, $params->application->familyStatus, $params->application->familySpouseFirstName, $params->application->familySpouseLastName, $params->application->familyKids, $params->application->familyKidsNamesAges, $params->application->address, $params->application->city, $params->application->zipCode, $params->application->country, $params->application->permanentAddressDifferent, $params->application->permanentAddress, $params->application->permanentCity, $params->application->permanentZipCode, $params->application->permanentCountry, $params->application->guardianFirstName, $params->application->guardianLastName, $params->application->guardianOccupation, $params->application->guardianEmail, $params->application->guardianPhone, $params->application->guardianAddressSame, $params->application->guardianAddress, $params->application->guardianCity, $params->application->guardianZipCode, $params->application->guardianCountry, $params->application->guardianOpinion],
                'update' => true
            ]);
        } elseif($params->applicationId === 8) { // ISP Education
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_education',
                'columns' => [
                    'userId',
                    'secondarySchoolName',
                    'secondarySchoolGraduationYear',
                    'secondarySchoolAddress',
                    'secondarySchoolCity',
                    'secondarySchoolZipCode',
                    'secondarySchoolCountry',
                    'englishHighSchool',
                    'englishCertificate',
                    'communityCollegeName',
                    'communityCollegeDatesAttended',
                    'communityCollegeDiscipline',
                    'communityCollegeAddress',
                    'communityCollegeCity',
                    'communityCollegeZipCode',
                    'communityCollegeCountry',
                    'collegeName',
                    'collegeDatesAttended',
                    'collegeDiscipline',
                    'collegeAddress',
                    'collegeCity',
                    'collegeZipCode',
                    'collegeCountry',
                    'graduateSchoolName',
                    'graduateSchoolDatesAttended',
                    'graduateSchoolDiscipline',
                    'graduateSchoolAddress',
                    'graduateSchoolCity',
                    'graduateSchoolZipCode',
                    'graduateSchoolCountry',
                    'dismissed',
                    'dismissedDetails',
                    'honors',
                    'honorsDetails'
                ],
                'values' => [$params->userId, $params->application->secondarySchoolName, $params->application->secondarySchoolGraduationYear, $params->application->secondarySchoolAddress, $params->application->secondarySchoolCity, $params->application->secondarySchoolZipCode, $params->application->secondarySchoolCountry, $params->application->englishHighSchool, $params->application->englishCertificate, $params->application->communityCollegeName, $params->application->communityCollegeDatesAttended, $params->application->communityCollegeDiscipline, $params->application->communityCollegeAddress, $params->application->communityCollegeCity, $params->application->communityCollegeZipCode, $params->application->communityCollegeCountry, $params->application->collegeName, $params->application->collegeDatesAttended, $params->application->collegeDiscipline, $params->application->collegeAddress, $params->application->collegeCity, $params->application->collegeZipCode, $params->application->collegeCountry, $params->application->graduateSchoolName, $params->application->graduateSchoolDatesAttended, $params->application->graduateSchoolDiscipline, $params->application->graduateSchoolAddress, $params->application->graduateSchoolCity, $params->application->graduateSchoolZipCode, $params->application->graduateSchoolCountry, $params->application->dismissed, $params->application->dismissedDetails, $params->application->honors, $params->application->honorsDetails],
                'update' => true
            ]);
        } elseif($params->applicationId === 9) { // ISP Health
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_health',
                'columns' => [
                    'userId',
                    'drugsUse',
                    'drugsUseDetails',
                    'currentDiseases',
                    'currentDiseasesDetails',
                    'currentSymptoms',
                    'currentSymptomsDetails',
                    'currentMedicines',
                    'currentMedicinesDetails',
                    'foodAllergy',
                    'foodAllergyDetails',
                    'firstEmergencyContactFirstName',
                    'firstEmergencyContactLastName',
                    'firstEmergencyContactPhone',
                    'secondEmergencyContactFirstName',
                    'secondEmergencyContactLastName',
                    'secondEmergencyContactPhone',
                    'otherDoctorContactApproval'
                ],
                'values' => [$params->userId, $params->application->drugsUse, $params->application->drugsUseDetails, $params->application->currentDiseases, $params->application->currentDiseasesDetails, $params->application->currentSymptoms, $params->application->currentSymptomsDetails, $params->application->currentMedicines, $params->application->currentMedicinesDetails, $params->application->foodAllergy, $params->application->foodAllergyDetails, $params->application->firstEmergencyContactFirstName, $params->application->firstEmergencyContactLastName, $params->application->firstEmergencyContactPhone, $params->application->secondEmergencyContactFirstName, $params->application->secondEmergencyContactLastName, $params->application->secondEmergencyContactPhone, $params->application->otherDoctorContactApproval],
                'update' => true
            ]);
        } elseif($params->applicationId === 10) { // ISP Christian Life
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_christian_life',
                'columns' => [
                    'userId',
                    'statementOfFaithApproval',
                    'conviction',
                    'convictionDetails',
                    'ministryTalent',
                    'ministryExperience',
                    'familyBackground',
                    'testimony',
                    'spiritualGrowth',
                    'reasonsToEnroll'
                ],
                'values' => [$params->userId, $params->application->statementOfFaithApproval, $params->application->conviction, $params->application->convictionDetails, $params->application->ministryTalent, $params->application->ministryExperience, $params->application->familyBackground, $params->application->testimony, $params->application->spiritualGrowth, $params->application->reasonsToEnroll],
                'update' => true
            ]);
        } elseif($params->applicationId === 11) { // ISP References
            $allValues[] = [
                $params->userId,
                2,
                isset($params->application[0]->referenceId) ? ($params->application[0]->referenceId === '0' ? null : $params->application[0]->referenceId) : null,
                isset($params->application[0]->firstName) ? $params->application[0]->firstName : '',
                isset($params->application[0]->lastName) ? $params->application[0]->lastName : '',
                isset($params->application[0]->email) ? $params->application[0]->email : '',
                isset($params->application[0]->phone) ? $params->application[0]->phone : '',
                isset($params->application[0]->address) ? $params->application[0]->address : '',
                isset($params->application[0]->city) ? $params->application[0]->city : '',
                isset($params->application[0]->zipCode) ? $params->application[0]->zipCode : '',
                isset($params->application[0]->country) ? $params->application[0]->country : '',
                isset($params->application[0]->priority) ? $params->application[0]->priority : ''
            ];
            $allValues[] = [
                $params->userId,
                3,
                isset($params->application[1]->referenceId) ? ($params->application[1]->referenceId === '0' ? null : $params->application[1]->referenceId) : null,
                isset($params->application[1]->firstName) ? $params->application[1]->firstName : '',
                isset($params->application[1]->lastName) ? $params->application[1]->lastName : '',
                isset($params->application[1]->email) ? $params->application[1]->email : '',
                isset($params->application[1]->phone) ? $params->application[1]->phone : '',
                isset($params->application[1]->address) ? $params->application[1]->address : '',
                isset($params->application[1]->city) ? $params->application[1]->city : '',
                isset($params->application[1]->zipCode) ? $params->application[1]->zipCode : '',
                isset($params->application[1]->country) ? $params->application[1]->country : '',
                isset($params->application[1]->priority) ? $params->application[1]->priority : ''
            ];
            foreach ($allValues as $values) {
                $result = $this->db2->sql([
                    'statement' => 'INSERT INTO',
                    'table' => 'admin_applications_references',
                    'columns' => [
                        'userId',
                        'formTemplateId',
                        'referenceId',
                        'firstName',
                        'lastName',
                        'email',
                        'phone',
                        'address',
                        'city',
                        'zipCode',
                        'country',
                        'priority'
                    ],
                    'values' => $values,
                    'update' => true
                ]);
            }
        } elseif($params->applicationId === 20) { // ISP Applicant Classification
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_applicant_classification',
                'columns' => [
                    'userId',
                    'classificationId',
                    'startingDate',
                    'accommodationRequired',
                    'visaRequired'
                ],
                'values' => [$params->userId, $params->application->classificationId, $params->application->startingDate, $params->application->accommodationRequired, $params->application->visaRequired],
                'update' => true
            ]);
        } elseif($params->applicationId === 21) { // ISP Financial
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_financial',
                'columns' => [
                    'userId',
                    'tuition',
                    'roomBoard',
                    'paymentSchedule',
                    'financialApproval',
                    'selfPaid',
                    'sponsors',
                    'sponsorsTotal',
                    'debtApproval'
                ],
                'values' => [$params->userId, $params->application->tuition, $params->application->roomBoard, $params->application->paymentSchedule, $params->application->financialApproval, $params->application->selfPaid, $params->application->sponsors, $params->application->sponsorsTotal, $params->application->debtApproval],
                'update' => true
            ]);
        } elseif($params->applicationId === 22) { // ISP Application Fee
            $result = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_applications_isp_financial',
                'columns' => [
                    'userId',
                    'applicationFee',
                    'feeUrl'
                ],
                'values' => [$params->userId, $params->application->applicationFee, $params->application->feeUrl],
                'update' => true
            ]);
        } else {
            dbg('Application ID: '.$params->applicationId);
        }

        return new AjaxResponse($result);
    }

    function setUserApplicationDocumentType($params) {
        try {
            $userId = isset($params->userId) ? $params->userId : $this->user->id;
            $sql = 'INSERT INTO admin_applications_documents (userId, documentId, documentTypeId) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE userId = ?, documentTypeId = ?;';
            $insertStatement = $this->db->prepare($sql);
            $result = $insertStatement->execute([$userId, $params->documentId, $params->documentTypeId, $userId, $params->documentTypeId]);

            return new AjaxResponse($result);
        } catch(Exception $e) {
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }
    }

    function submitUserApplication($params) {
        if(!isset($params->userId)){ // User is an Applicant
            $isApplicant = true;
            $params->userId = $this->user->id;
        } else {
            $isApplicant = false;
        }
        $programIds = (new AcademicsService)->getProgramIdsFromUserId($params->userId, '5');
        foreach ($programIds as $programId) {
            $toRoles[] = ['roleName' => 'registrar', 'forProgramId' => $programId->forProgramId];
        }
        $toRoles[] = ['roleName' => 'admin'];

        // Prepare Notification for the Registrar of the specific program
        $notificationOptions = [
            "toRoles" => $toRoles,
            "templateName" => 'applicantDetailsSubmission',
            "language" => $this->user->language,
            "vars" => [
                ['id', $params->userId]
            ]
        ];

        // Populate Notification
        if($params->applicationId === 1) { // Greek Personal
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Personal Information']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Προσωπικά Στοιχεία']);
        } elseif($params->applicationId === 2) { // Greek Education
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Education / Training']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Σπουδές / Εκπαίδευση']);
        } elseif($params->applicationId === 3) { // Greek Health
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Health Status']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Κατάσταση Υγείας']);
        } elseif($params->applicationId === 4) { // Greek Christian Life
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Christian Life']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Πνευματική Ζωή']);
        } elseif($params->applicationId === 5) { // Greek References
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Recommendation Letters']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Συστατικές Επιστολές']);
        } elseif($params->applicationId === 6) { // Greek Financial
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Financial Liability']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Οικονομική Ευθύνη']);
        } elseif($params->applicationId === 7) { // ISP Personal
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Personal Information']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Προσωπικά Στοιχεία']);
        } elseif($params->applicationId === 8) { // ISP Education
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Education']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Σπουδές']);
        } elseif($params->applicationId === 9) { // ISP Health
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Health Status']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Κατάσταση Υγείας']);
        } elseif($params->applicationId === 10) { // ISP Christian Life
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Christian Life']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Πνευματική Ζωή']);
        } elseif($params->applicationId === 11) { // ISP References
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'References']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Συστατικές Επιστολές']);
        } elseif($params->applicationId === 21) { // ISP Financial
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Financial Liability']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Οικονομική Ευθύνη']);
        } elseif($params->applicationId === 12) { // Supporting Documentation
            array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Supporting Documents']);
            array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Δικαιολογητικά']);
        }

        if($isApplicant){ // User is an Applicant
            // Update Application as Submitted
            $result = $this->db2->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_user_applications',
                'columns' => 'applicationStatus',
                'values' => 2,
                'where' => ['userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);

            // Set Applicant's Name on the Email Template based on who is submitting the form
            array_push($notificationOptions["vars"], ['firstName', $this->user->firstName]);
            array_push($notificationOptions["vars"], ['lastName', $this->user->lastName]);

            // Send Notification to the Registrar of the specific program
            (new NotificationService())->send($notificationOptions);

            // If it's a Greek Financial Liability Application send Notification to the Cashier as well
            if ($params->applicationId === 6 || $params->applicationId === 21) {
                $notificationOptions["toRoles"] = [['roleName' => 'cashier']];
                (new NotificationService())->send($notificationOptions);
            }

        } else { // User is involved with Admissions
            // Update Application as Approved
            $result = $this->db2->sql([
                'statement' => 'UPDATE',
                'table' => 'admin_user_applications',
                'columns' => 'applicationStatus',
                'values' => 1,
                'where' => ['userId = ? AND applicationId = ?', [$params->userId, $params->applicationId]]
            ]);

            // Get Applicant's Name
            $user = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => 'firstName, lastName',
                'table' => 'admin_users',
                'where' => ['id= ?', $params->userId]
            ]);

            // Set Applicant's Name on the Email Template
            array_push($notificationOptions["vars"], ['firstName', $user->firstName]);
            array_push($notificationOptions["vars"], ['lastName', $user->lastName]);

            if($params->applicationId === 20) { // ISP Applicant Classification
                // Add a hidden Financial Liability Application to Applicant's Applications
                $params->applicationId = 21; // 21 is the applicationId for Financial Liability Application
                $sql = 'INSERT INTO admin_user_applications (userId, applicationId, hidden) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE applicationId = applicationId;';
                $insertStatement = $this->db->prepare($sql);
                $result = $insertStatement->execute([$params->userId, $params->applicationId]);

                array_push($notificationOptions["vars"], ['applicationFormTitleEn', 'Applicant Classification']);
                array_push($notificationOptions["vars"], ['applicationFormTitleGr', 'Κατηγοριοποίηση Υποψηφίου']);
                // Notify the Admissions Committee
                (new NotificationService())->send($notificationOptions);
                // Prepare Notification to be sent to Cashier as well
                $notificationOptions["toRoles"] = [['roleName' => 'cashier']];
            }

            // Notify the Admissions Committee (or the Cashier if applicationId === 20 and it's set right above)
            (new NotificationService())->send($notificationOptions);
        }
        return new AjaxResponse($result);
    }

    function decideForUserApplication($params) {
        $sql = 'UPDATE admin_user_applications SET applicationStatus = ? WHERE userId = ? AND applicationId = ?;';
        $updateStatement = $this->db->prepare($sql);
        $updateStatement->execute([$params->applicationStatus, $params->userId, $params->applicationId]);

        // If application is Application Fee and if it's accepted then Notify Registrar
        if ($params->applicationId === 22 && $params->applicationStatus === 1) {
            $return = $this->db2->sql1([
                'statement' => 'SELECT',
                'columns' => 'id, firstName, lastName, language',
                'table' => 'admin_users',
                'where' => ['id = ?', $params->userId]
            ]);

            (new NotificationService())->send([
                "toRoles" => [
                    ['roleName' => 'registrar', 'forProgramId' => 2],
                    ['roleName' => 'admin']
                ],
                "templateName" => 'applicationFeeConfirmed',
                "language" => $return->language,
                "vars" => [
                    ['firstName', $return->firstName],
                    ['lastName', $return->lastName],
                    ['id', $return->id]
                ]
            ]);
        }

        return new AjaxResponse($updateStatement->rowCount());
    }

    function hideUserApplication($params) {
        $sql = 'UPDATE admin_user_applications SET hidden = 1 WHERE userId = ? AND applicationId = ?;';
        $updateStatement = $this->db->prepare($sql);
        $updateStatement->execute([$params->userId, $params->applicationId]);

        return new AjaxResponse($updateStatement->rowCount());
    }

    function unhideUserApplication($params) {
        $sql = 'UPDATE admin_user_applications SET hidden = 0 WHERE userId = ? AND applicationId = ?;';
        $updateStatement = $this->db->prepare($sql);
        $updateStatement->execute([$params->userId, $params->applicationId]);
        $updateResult = $updateStatement->rowCount();

        if($updateResult === 1) {
            $sql = 'SELECT admin_users.id, email, language, heading_en, heading_gr FROM admin_user_applications JOIN admin_users ON admin_user_applications.userId = admin_users.id JOIN admin_applications ON admin_user_applications.applicationId = admin_applications.id WHERE userId = ? AND applicationId = ?;';
            $statement = $this->db->prepare($sql);
            $statement->execute([$params->userId, $params->applicationId]);
            $result = $statement->fetch(PDO::FETCH_OBJ);
            
            // Notify Applicant & Cashier
            (new NotificationService())->send([
                "toRoles" => [['roleName' => 'cashier']],
                "toEmails" => $result->email,
                "forUserIds" => $result->id,
                "templateName" => 'userUpdatedApplication',
                "language" => $result->language,
                "vars" => [
                    ['applicationHeadingEn', $result->heading_en],
                    ['applicationHeadingGr', $result->heading_gr]
                ]
            ]);
        }
        
        return new AjaxResponse($updateResult);
    }

    function getLetterOfRecommendationMetaData($params) {
        $sql = 'SELECT userId, formTemplateId, title_en, title_gr, admin_users.firstName applicantFirstName, admin_users.lastName applicantLastName, referenceId, admin_applications_references.firstName referenceFirstName, admin_applications_references.lastName referenceLastName, admin_applications_references.email, phone, address, city, zipCode, country FROM admin_applications_references JOIN admin_users ON userId = id JOIN admin_form_templates ON formTemplateId = templateId WHERE referenceId = ?;';
        $selectStatement = $this->db->prepare($sql);
        $selectStatement->execute([$params->token]);
        $result["meta"] = $selectStatement->fetch(PDO::FETCH_OBJ);
        if($result["meta"] === false) {
            return new AjaxError('Wrong Token for Letter of Recommendation: <b>'.$params->token.'</b>');
        }
        
        $sql = 'SELECT questionIds FROM admin_form_templates WHERE templateId = ?;';
        $selectStatement = $this->db->prepare($sql);
        $selectStatement->execute([$result["meta"]->formTemplateId]);
        $questionIds = $selectStatement->fetch(PDO::FETCH_OBJ);
        $questionIds = explode(',', $questionIds->questionIds);
        $where = 'questionId = '.implode(' OR questionId = ', $questionIds);
        $orderBy = 'ORDER BY FIELD(questionId, '.implode(', ', $questionIds).')';

        $sql = 'SELECT questionId, title_en, title_gr, type, childrenIds FROM admin_form_questions WHERE '.$where.' '.$orderBy.';';
        $selectStatement = $this->db->prepare($sql);
        $selectStatement->execute();
        $result["questions"] = $selectStatement->fetchAll(PDO::FETCH_OBJ);

        foreach($result["questions"] as $key => $question) {
            if($question->childrenIds) {
                $question->children = [];
                $question->childrenIds = explode(',', $question->childrenIds);
                $where = 'questionId = '.implode(' OR questionId = ', $question->childrenIds);
                $orderBy = 'ORDER BY FIELD(questionId, '.implode(', ', $question->childrenIds).')';
                        $sql = 'SELECT questionId, title_en, title_gr, type FROM admin_form_questions WHERE '.$where.';';
                $selectStatement = $this->db->prepare($sql);
                $selectStatement->execute();
                $question->children = $selectStatement->fetchAll(PDO::FETCH_OBJ);
            }
        }

        return new AjaxResponse($result);
    }

    function saveLetterOfRecommendationData($params) {
        // First Step: Save the Form Object
        $this->saveFormResponse($params);

        // Second Step: Generate PDF
        // Get PDFService Handle
        $pdfService = new PDFService();

        // Generate Letter of Recommendation Header
        $pdfService->Head($params->formData->meta->{'title_'.$params->language});
        $pdfService->Title("Στοιχεία Ταυτότητας");
        $pdfService->pdf->SetFont('Calibrib','b',14);
            $pdfService->pdf->Cell(90,10,"Ονοματεπώνυμο Συντάκτη");
            $pdfService->VR();
            $pdfService->pdf->Cell(0,10,"Ονοματεπώνυμο Υποψηφίου");
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibri','',14);
            $pdfService->pdf->Cell(90,10,$params->formData->meta->referenceLastName.", ".$params->formData->meta->referenceFirstName);
            $pdfService->pdf->Cell(0,10,$params->formData->meta->applicantLastName.", ".$params->formData->meta->applicantFirstName);
            $pdfService->HR();
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibrib','b',14);
            $pdfService->pdf->Cell(0,10,"Επάγγελμα / Ιδιότητα");
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibri','',14);
            $pdfService->pdf->Cell(0,10,$params->formData->meta->occupation);
            $pdfService->HR();
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibrib','b',14);
            $pdfService->pdf->Cell(0,10,"Οδός, αριθμός, Πόλη Τ.Κ., Χώρα");
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibri','',14);
            $pdfService->pdf->Cell(0,10,$params->formData->meta->address.", ".$params->formData->meta->cityZipCountry);
            $pdfService->HR();
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibrib','b',14);
            $pdfService->pdf->Cell(90,10,"Τηλέφωνο");
            $pdfService->VR();
            $pdfService->pdf->Cell(0,10,"Email");
        $pdfService->pdf->Ln(8);
        $pdfService->pdf->SetFont('Calibri','',14);
            $pdfService->pdf->Cell(90,10,$params->formData->meta->phone);
            $pdfService->pdf->Cell(0,10,$params->formData->meta->email);
            $pdfService->HR();
        $pdfService->pdf->Ln(16);

        // Generate Letter of Recommendation Questions / Answers
        $pdfService->Title("Σχετικά με τον/την Υποψήφιο/α");
        $pdfService->generateQuestionsAnswers($params->formData->questions, $params->language);

        $filename = $pdfService->saveFile((object) [
            "userId" => $params->formData->meta->userId,
            "filename" => $params->token,
            "originalFileName" => $params->formData->meta->{'title_'.$params->language}.'_'.$params->formData->meta->referenceLastName.' '.$params->formData->meta->referenceFirstName.'_'.$params->formData->meta->applicantLastName.' '.$params->formData->meta->applicantFirstName
        ]);

        $sql = 'INSERT INTO admin_applications_documents (userId, documentId, documentTypeId) VALUES (?,?,?) ON DUPLICATE KEY UPDATE documentTypeId = ?;';
        $insertStatement = $this->db->prepare($sql);
        // 6 stands for Confidential Letter of Recommendation, check admin_applications_document_types
        $insertStatement->execute([$params->formData->meta->userId, $filename, 6, 6]);

        $programIds = (new AcademicsService)->getProgramIdsFromUserId($params->formData->meta->userId, '5');
        foreach ($programIds as $programId) {
            $toRoles[] = ['roleName' => 'registrar', 'forProgramId' => $programId->forProgramId];
        }
        $toRoles[] = ['roleName' => 'admin'];
        (new NotificationService())->send([
            "toRoles" => $toRoles,
            "templateName" => 'letterOfRecommendationSubmission',
            "language" => $params->language,
            "vars" => [
                ['authorLastName', $params->formData->meta->referenceLastName],
                ['authorFirstName', $params->formData->meta->referenceFirstName],
                ['lastName', $params->formData->meta->applicantLastName],
                ['firstName', $params->formData->meta->applicantFirstName],
                ['id', $params->formData->meta->userId]
            ]
        ]);

        return new AjaxResponse($filename);
    }

    function saveFormResponse($params) {
        $sql = 'INSERT INTO admin_form_responses (formId, language, response) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE language = ?, response = ?;';
        $insertStatement = $this->db->prepare($sql);
        $formData = json_encode($params->formData);
        $result = $insertStatement->execute([$params->token, $params->language, $formData, $params->language, $formData]);

        return new AjaxResponse($result);
    }

    function deleteFormResponse($formId) {
        $sql = 'DELETE FROM admin_form_responses WHERE formId = ?;';
        $deleteStatement = $this->db->prepare($sql);
        $result = $deleteStatement->execute([$formId]);

        return new AjaxResponse($result);
    }
}