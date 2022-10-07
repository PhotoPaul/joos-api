<?

class Admissions {
    function __construct(){
        global $session;

        $this->db = $session->db->dbObject;
        $this->db2 = $session->db;
        $this->hasher = $session->db->hasher;
        $this->user = $session->authenticationService->user;
    }

// START *** Administration Functions ***

    function getApplicantsData($params){
        // Get all Candidates x Applications sorted by Candidates who have Applications and then sorted by Applicant
        $results = $this->db2->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id',
                'firstName',
                'lastName',
                'email',
                'roleId',
                'forProgramId',
                'admin_program_enrollment.id programEnrollmentId',
                'admin_users.date_time',
                'applicationId',
                'icon',
                'viewPath',
                'editPath',
                'viewRoles',
                'editRoles',
                'heading_en',
                'heading_gr',
                'applicationStatus',
                'hidden',
                'vote' // Add missing relevant Join
            ],
            'table' => 'admin_user_roles',
            'joins' => [
                'LEFT JOIN admin_users ON admin_user_roles.userId = admin_users.id',
                'LEFT JOIN admin_program_enrollment ON admin_program_enrollment.student_id = admin_users.id',
                'LEFT JOIN admin_user_applications ON admin_users.id = admin_user_applications.userId',
                'LEFT JOIN admin_applications ON applicationId = admin_applications.id',
                'LEFT JOIN (SELECT candidateId, vote FROM admin_admissions WHERE userId = ?) AS admin_admissions_filtered ON admin_users.id = admin_admissions_filtered.candidateId'
            ],
            'where' => [
                ((!isset($params->filter) || $params->filter->programId === '0' || $params->filter->programId === null) ?
                    'forProgramId IS NOT NULL OR (FALSE AND ?)' :
                ($params->filter->programId === '-1' ?
                    'forProgramId IS NULL OR (FALSE AND ?)' :
                    'forProgramId = ?'
                )).' AND (roleId = 5 OR roleId = 6)',
                [$this->user->id, isset($params->filter->programId) ? $params->filter->programId : null, isset($params->id) ? $params->id : null]
            ],
            'order' => 'admin_users.id = ? DESC, admin_users.id DESC, applicationId IS NULL, applicationStatus DESC, applicationId'
        ]);
        $results = JSFDates($results, 'date_time');

        $results = $this->db2->groupResults($results, 'id', [
            ['roles', ['roleId', 'forProgramId']],
            ['applications', [
                'applicationId',
                'icon',
                'viewPath',
                'editPath',
                'viewRoles',
                'editRoles',
                'heading_en',
                'heading_gr',
                'applicationStatus',
                'hidden'
            ]]
        ]);

        function getRoleRecordIfExists($roles, $roleNeedle) {
            if (is_array($roleNeedle)) {
                $roleNeedle = (object) $roleNeedle;
            }
            foreach ($roles as $roleHaystack) {
                if (
                    $roleHaystack->roleId == $roleNeedle->roleId &&
                    (
                        (isset($roleHaystack->forProgramId) && isset($roleNeedle->forProgramId)) ?
                        $roleHaystack->forProgramId === $roleNeedle->forProgramId :
                        true
                    )
                ) {
                    return $roleHaystack;
                }
            }
            return false;
        }

        $urlGenerator = new UrlGenerator();
        foreach ($results as $key => $applicant) {
            $applicant->pdfUrl = $urlGenerator->getURL("getAdmissionsCompleteApplicationPDF", ["id" => $applicant->id]);
            if (isset($params->id) && $applicant->id == $params->id) {
                continue;
            } elseif (getRoleRecordIfExists($applicant->roles, ["roleId" => '5'])) {
                // If filterHiddenOnly is true then only keep Applicants who:
                // 1) Have been classified
                // 2) Haven't had their Financial Liability form revealed yet
                $applicantId = '';
                if (isset($params->filter->hiddenOnly) && $params->filter->hiddenOnly) {
                    foreach ($applicant->applications as $application) {
                        if ($application->applicationId === '20' && $application->applicationStatus === '1') {
                            $applicantId = $applicant->id;
                        } elseif (
                            $application->applicationId === '21' &&
                            $applicantId === $applicant->id &&
                            $application->hidden !== '1'
                        ) {
                            unset($results[$key]);
                        }
                    }
                }
            } else {
                unset($results[$key]);
            }
        }
        $results = array_values($results);

        return new AjaxResponse($results);
    }

    function getCandidatesVotingData($params){
        $programIds = (new AcademicsService)->getProgramIdsFromUserId($this->user->id, ['2', '9']);
        foreach ($programIds as $programId) {
            if ($programId->forProgramId) {
                $whereString[] = 'forProgramId = ?';
                $whereParams[] = $programId->forProgramId;
            }
        }
        $whereString = isset($whereString) ? '('.implode(' OR ', $whereString).')' : 'TRUE';
        
        $return = $this->db2->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_admissions.candidateId',
                'admin_users_candidates.date_time',
                'admin_users_candidates.id',
                'admin_users_candidates.firstName candidateFirstName',
                'admin_users_candidates.lastName candidateLastName',
                'admin_user_roles.roleId',
                'admin_user_roles.forProgramId',
                'admin_users_admissions.id userId',
                'admin_users_admissions.firstName userFirstName',
                'admin_users_admissions.lastName userLastName',
                'vote'
            ],
            'table' => 'admin_admissions',
            'joins' => [
                'LEFT JOIN admin_users AS admin_users_admissions ON admin_admissions.userId = admin_users_admissions.id',
                'LEFT JOIN admin_users AS admin_users_candidates ON admin_admissions.candidateId = admin_users_candidates.id',
                'LEFT JOIN admin_user_roles ON admin_user_roles.userId = admin_users_candidates.id'
            ],
            'where' => [
                $whereString.' AND (admin_user_roles.roleId = 5 OR admin_user_roles.roleId = 6)',
                isset($whereParams) ? $whereParams : null
            ],
            'order' => 'admin_admissions.candidateId DESC, admin_user_roles.roleId, admin_admissions.userId DESC'
        ]);
        $return = JSFDates($return, 'date_time');

        $return = $this->db2->groupResults($return, 'id', [
            ['votes', ['candidateId', 'userId', 'userFirstName', 'userLastName', 'vote']],
            ['roles', ['roleId', 'forProgramId']]
        ]);

        // Remove students who are not candidates
        foreach ($return as $key => $candidate) {
            $isCandidate = false;
            foreach ($candidate->roles as $role) {
                if ($role->roleId === '5') {
                    $isCandidate = true;
                    break;
                }
            }
            if (!$isCandidate) {
                unset($return[$key]);
            }
        }
        $return = array_values($return);

        return new AjaxResponse($return);
    }

    function getAdmissionsCompleteApplication($params){
        // Get User Applications
        $sql = 'SELECT applicationId, applicationStatus, heading_gr, heading_en, dbTable FROM admin_user_applications JOIN admin_applications ON applicationId = admin_applications.id WHERE userId = ?;';
        $statement = $this->db->prepare($sql);
        $statement->execute([isset($params->id) ? $params->id : $this->user->id]);
        $results = $statement->fetchAll(PDO::FETCH_OBJ);

        // Get User Applications Data
        foreach($results as $result) {
            $sql = 'SELECT * FROM admin_applications_'.$result->dbTable.' WHERE userId = ?;';
            $statement = $this->db->prepare($sql);
            $statement->execute([isset($params->id) ? $params->id : $this->user->id]);
            $result->data = $statement->fetchAll(PDO::FETCH_OBJ);
            if(count($result->data) === 1) {
                $result->data = $result->data[0];
            } elseif (!is_object($result->data)) {
                $result->data = new stdClass();
            }
        }

        return new AjaxResponse($results);
    }

    function getAdmissionsCompleteApplicationPDF($params){
        $applicationsData = $this->getAdmissionsCompleteApplication($params)->data;

        // Get PDFService Handle
        $pdfService = new PDFService();

        foreach($applicationsData as $applicationData) {
            if($applicationData->applicationId === "1") {
                $sql = 'SELECT firstName, lastName, email FROM admin_users WHERE id = ?;';
                $statement = $this->db->prepare($sql);
                $statement->execute([$params->id]);
                $result = $statement->fetch(PDO::FETCH_OBJ);
                $applicationData->data->firstName = $result->firstName;
                $applicationData->data->lastName = $result->lastName;
                $applicationData->data->email = $result->email;
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnGreekPersonalTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "2") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnGreekEducationTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "3") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnGreekHealthTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "4") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnGreekChristianLifeTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "5") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnGreekReferencesTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "6") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnGreekFinancialTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "7") {
                $sql = 'SELECT firstName, lastName, email FROM admin_users WHERE id = ?;';
                $statement = $this->db->prepare($sql);
                $statement->execute([$params->id]);
                $result = $statement->fetch(PDO::FETCH_OBJ);
                $applicationData->data->firstName = $result->firstName;
                $applicationData->data->lastName = $result->lastName;
                $applicationData->data->email = $result->email;
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnISPPersonalTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "8") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnISPEducationTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "9") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnISPHealthTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "10") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnISPChristianLifeTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "11") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnISPReferencesTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "12") {
                // No need to print
            }
            elseif($applicationData->applicationId === "21") {
                $pdfService->AddPage();
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->printOnISPFinancialTemplate($applicationData);
            }
            elseif($applicationData->applicationId === "20") {
                // No need to print
            }
            else {
                $pdfService->PrintReportStatus($applicationData->applicationStatus);
                $pdfService->Head('No PDF Template for applicationId: '.$applicationData->applicationId);
            }
        }
        $pdfService->render();
    }

    function getLetterOfRecommendation($params){
        $sql = 'SELECT admin_applications_letters_of_recommendation.id, userId, firstName candidateFirstName, lastName candidateLastName, authorFirstName, authorLastName, authorOccupation, authorAddress, authorCityZipCountry, authorPhone, authorEmail, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, q11, q12, q13, q14, q15, q16, q17, q18, q19, q20, unsuitable, suitable, thorough, rational, undisciplined, disciplined, immature, quiteMature, easyGoing, obedient, cooperative, wasteful, reliable, unreliable, selfCentered, giving, servant, nervous, narrowMinded, inflexible, private, friendly, pleasant, achiever, extraInfo, firstName, lastName FROM admin_applications_letters_of_recommendation JOIN admin_users ON admin_applications_letters_of_recommendation.userId = admin_users.id WHERE admin_applications_letters_of_recommendation.id = ?;';
        $selectStatement = $this->db->prepare($sql);
        $selectStatement->execute([$params->id]);
        $result = $selectStatement->fetch(PDO::FETCH_OBJ);

        $result->unsuitable = booleanize($result->unsuitable);
        $result->suitable = booleanize($result->suitable);
        $result->thorough = booleanize($result->thorough);
        $result->rational = booleanize($result->rational);
        $result->undisciplined = booleanize($result->undisciplined);
        $result->disciplined = booleanize($result->disciplined);
        $result->immature = booleanize($result->immature);
        $result->quiteMature = booleanize($result->quiteMature);
        $result->easyGoing = booleanize($result->easyGoing);
        $result->obedient = booleanize($result->obedient);
        $result->cooperative = booleanize($result->cooperative);
        $result->wasteful = booleanize($result->wasteful);
        $result->reliable = booleanize($result->reliable);
        $result->unreliable = booleanize($result->unreliable);
        $result->selfCentered = booleanize($result->selfCentered);
        $result->giving = booleanize($result->giving);
        $result->servant = booleanize($result->servant);
        $result->nervous = booleanize($result->nervous);
        $result->narrowMinded = booleanize($result->narrowMinded);
        $result->inflexible = booleanize($result->inflexible);
        $result->private = booleanize($result->private);
        $result->friendly = booleanize($result->friendly);
        $result->pleasant = booleanize($result->pleasant);
        $result->achiever = booleanize($result->achiever);

        return new AjaxResponse($result);
    }

    function decideForReference($params){
        if($params->decision === false) {
            $sql = 'UPDATE admin_applications_references SET referenceId = ? WHERE userId = ? AND priority = ?;';
            $updateStatement = $this->db->prepare($sql);
            $referenceId = '0';
            $result = $updateStatement->execute([$referenceId, $params->userId, $params->priority]);
        } else {
            $sql = "UPDATE admin_applications_references SET referenceId = ? WHERE userId = ? AND priority = ?;";
            $updateStatement = $this->db->prepare($sql);
            $referenceId = $params->decision ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->hasher->HashPassword($params->userId)) : '';
            $result = $updateStatement->execute([$referenceId, $params->userId, $params->priority]);
        }
        return new AjaxResponse([
            "referenceId" => $referenceId
        ]);
    }

    function deleteDocument($params) {
        // Delete File from FileSystem
        $result = (new FileSystem())->deleteFile($params);
        // Delete Document from Applications Documents
        $sql = 'DELETE FROM admin_applications_documents WHERE documentId = ?;';
        $deleteStatement = $this->db->prepare($sql);
        $result = $deleteStatement->execute([$params->filename]);
        // Delete Response from Form Responses
        $result = (new Applications())->deleteFormResponse($params->filename);

        return new AjaxResponse($result);
    }

    function voteForCandidate($params){
        $sql = 'INSERT INTO admin_admissions (userId, candidateId, vote) VALUES (?,?,?) ON DUPLICATE KEY UPDATE vote = ?;';
        $insertStatement = $this->db->prepare($sql);
        $result = $insertStatement->execute([$this->user->id, $params->id, $params->approved, $params->approved]);

        return new AjaxResponse($result);
    }

    function removeVoteFromCandidate($params){
        $return = $this->db2->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_admissions',
            'where' => ['userId = ? AND candidateId = ?', [$params->userId, $params->candidateId]]
        ]);

        return new AjaxResponse($return);
    }

    function resetApplicant($params){
        // Get Application Table Names for the Applications of the Applicant
        $applications = $this->db2->sql([
            'statement' => 'SELECT',
            'columns' => 'dbTable',
            'table' => 'admin_user_applications',
            'joins' => 'JOIN admin_applications ON id = applicationId',
            'where' => ['userId = ?', $params->userId]
        ]);

        foreach($applications as $application) {
            // Delete Application Entries from each Application Table
            $result = $this->db2->sql([
                'statement' => 'DELETE FROM',
                'table' => 'admin_applications_'.$application->dbTable,
                'where' => ['userId = ?', $params->userId]
            ]);
        }

        // Remove all Applications from Applicant
        $result = $this->db2->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_user_applications',
            'where' => ['userId = ?', $params->userId]
        ]);

        // Remove Program Id from Applicant Role
        $result = $this->db2->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_user_roles',
            'columns' => 'forProgramId',
            'values' => [null],
            'where' => ['userId = ?', $params->userId]
        ]);

        return new AjaxResponse(["id" => $params->userId]);
    }

    function promoteCandidateToStudent($params){
        // Add student role to user
        $this->db2->sql([
            'statement' => 'INSERT INTO',
            'table' => 'admin_user_roles',
            'columns' => 'userId, roleId, forProgramId',
            'values' => [$params->candidateId, 6, $params->programId],
            'update' => true
        ]);

        // Enroll user to program
        $result = (new AcademicsService())->enrollUserToProgram((object) [
            "studentId" => $params->candidateId,
            "programId" => $params->programId
        ]);

        // If Candidate now Student is an ISP Student, then reveal the Application Fee Application
        $this->db2->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_user_applications',
            'columns' => 'hidden', 'values' => ['0'],
            'where' => ['userId = ? AND applicationId = ?', [$params->candidateId, 22]]
        ]);

        return new AjaxResponse($result->data);
    }

    function copyUserProfileFromApplication($id){
        $sql = 'INSERT INTO admin_user_profiles (id, birthDate, phone, greekCitizen, greekIdNumber, greekSsn, irsOffice, citizenship, euCitizen, passportNumber, residencePermit, address, city, zipCode, country) SELECT id, birthDate, phone, greekCitizen, greekIdNumber, greekSsn, irsOffice, citizenship, euCitizen, passportNumber, residencePermit, address, city, zipCode, country FROM admin_applications_personal t WHERE id = ? ON DUPLICATE KEY UPDATE birthDate = t.birthDate, phone = t.phone, greekCitizen = t.greekCitizen, greekIdNumber = t.greekIdNumber, greekSsn = t.greekSsn, irsOffice = t.irsOffice, citizenship = t.citizenship, euCitizen = t.euCitizen, passportNumber = t.passportNumber, residencePermit = t.residencePermit, address = t.address, city = t.city, zipCode = t.zipCode, country = t.country;';
        $replaceStatement = $this->db->prepare($sql);
        $result = $replaceStatement->execute([$id]);
    }

    function removeCandidateRole($params){
        $return = $this->db2->sql([
            'statement' => 'DELETE FROM',
            'table' => 'admin_user_roles',
            'where' => ['userId = ? AND roleId = 5', $params->id]
        ]);

        return new AjaxResponse($return);
    }
//// END *** Administration Functions ***

// START *** Applicant Functions ***

    function getApplicantHomeData(){
        $userId = $this->user->id;
        // Check if Applicant has any active applications
        $sql = 'SELECT userId, applicationId, applicationStatus, icon, editPath, heading_en, heading_gr, dbTable FROM admin_user_applications JOIN admin_applications ON applicationId = admin_applications.id WHERE userId = ? AND hidden = 0;';
        $selectStatement = $this->db->prepare($sql);
        $selectStatement->execute([$userId]);
        $result = $selectStatement->fetchAll(PDO::FETCH_OBJ);

        if(!$result) {
            // No active program application, return available programs
            $sql = 'SELECT id, program_name_gr, program_name_en, number_of_semesters FROM admin_programs;';
            $selectStatement = $this->db->prepare($sql);
            $selectStatement->execute();
            $result = $selectStatement->fetchAll(PDO::FETCH_OBJ);
            return new AjaxResponse([
                "programs" => $result
            ]);
        }

        return new AjaxResponse([
            "applications" => $result
        ]);
    }

    function setProgramForApplicant($params){
        $userId = $this->user->id;

        // If asAuditor then add Auditor role to user
        if($params->asAuditor) {
            $return = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_user_roles',
                'columns' => [
                    'userId',
                    'roleId'
                ],
                'values' => [
                    $userId,
                    12
                ]
            ]);
        }

        // Set Program for Applicant
        $return = $this->db2->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_user_roles',
            'columns' => 'forProgramId',
            'values' => [$params->programId],
            'where' => ['userId = ? AND roleId = 5 AND forProgramId IS NULL', $userId]
        ]);

        // Get Applications for program
        $return = $this->db2->sql1([
            'statement' => 'SELECT',
            'columns' => $params->asAuditor ? 'auditor_applications AS applications' : 'applications',
            'table' => 'admin_programs',
            'where' => ['id = ?', $params->programId]
        ]);
        $applications = json_decode($return->applications);

        // Add Applications to user
        foreach($applications as $application) {
            $return = $this->db2->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_user_applications',
                'columns' => 'userId, applicationId, hidden',
                'values' => [
                    $userId,
                    $application->applicationId,
                    isset($application->hidden) ?
                        $application->hidden :
                        0
                ],
                'update' => true
            ]);
        }

        // Send New Applicant Notification to the Program's Registrar
        (new NotificationService())->send([
            "toRoles" => [
                ['roleName' => 'registrar', 'forProgramId' => $params->programId],
                ['roleName' => 'admin']
            ],
            "templateName" => 'admissionsΝewApplicant',
            "language" => $this->user->language,
            "vars" => [
                ["applicantId", $userId],
                ["firstName", $this->user->firstName],
                ["lastName", $this->user->lastName]
            ]
        ]);
        
        // Return the updated list of the Applicant's Applications
        return (new Home())->getHomeData();
    }

    function getRegistrationSupportingDocuments($params){
        $candidateId = isset($params->id)? $params->id: $this->user->id;
        $sql = 'SELECT owner_id, filename, filesize, original_filename, original_mime_type, date_time FROM admin_files WHERE owner_id = ?;';
        $selectStatement = $this->db->prepare($sql);
        $selectStatement->execute([$candidateId]);
        $result = $selectStatement->fetchAll(PDO::FETCH_OBJ);

        return new AjaxResponse($result);
    }

//// END *** Applicant Functions ***
}

// START *** Form Model Processing ***
    class ApplicationFormModel {
        public $serverModel;
        public $clientModel;

        function __construct($id, $_serverModel = null, $_clientModel = null){
            if(isset($_serverModel)){
                $this->serverModel = $_serverModel;
                $this->updateClientModel($_serverModel);
            } elseif(isset($_clientModel)){
                $this->clientModel = $_clientModel;
                $this->updateServerModel($id, $_clientModel);
            }
        }

        function updateClientModel($serverModel){
            $this->clientModel = (object) [
                "applicationFormModel" => (object) [
                    "idInfoFormModel" => new stdClass,
                    "familyInfoFormModel" => new stdClass,
                    "addressInfoFormModel" => new stdClass,
                    "guardianInfoFormModel" => new stdClass
                ]
            ];

            $this->clientModel->applicationFormModel->idInfoFormModel->id = $serverModel->userId;
            $this->clientModel->applicationFormModel->idInfoFormModel->firstName = $serverModel->firstName;
            $this->clientModel->applicationFormModel->idInfoFormModel->lastName = $serverModel->lastName;
            $this->clientModel->applicationFormModel->idInfoFormModel->birthDate = $serverModel->birthDate;
            $this->clientModel->applicationFormModel->idInfoFormModel->birthPlace = $serverModel->birthPlace;
            $this->clientModel->applicationFormModel->idInfoFormModel->email = $serverModel->email;
            $this->clientModel->applicationFormModel->idInfoFormModel->phone = $serverModel->phone;
            $this->clientModel->applicationFormModel->idInfoFormModel->occupation = $serverModel->occupation;
            $this->clientModel->applicationFormModel->idInfoFormModel->greekCitizen = booleanize($serverModel->greekCitizen);
            $this->clientModel->applicationFormModel->idInfoFormModel->greekIdNumber = $serverModel->greekIdNumber;
            $this->clientModel->applicationFormModel->idInfoFormModel->greekSsn = $serverModel->greekSsn;
            $this->clientModel->applicationFormModel->idInfoFormModel->irsOffice = $serverModel->irsOffice;
            $this->clientModel->applicationFormModel->idInfoFormModel->citizenship = $serverModel->citizenship;
            $this->clientModel->applicationFormModel->idInfoFormModel->euCitizen = booleanize($serverModel->euCitizen);
            $this->clientModel->applicationFormModel->idInfoFormModel->passportNumber = $serverModel->passportNumber;
            $this->clientModel->applicationFormModel->idInfoFormModel->residencePermit = booleanize($serverModel->residencePermit);

            $this->clientModel->applicationFormModel->familyInfoFormModel->familyStatus = $serverModel->familyStatus;
            $this->clientModel->applicationFormModel->familyInfoFormModel->familySpouseFirstName = $serverModel->familySpouseFirstName;
            $this->clientModel->applicationFormModel->familyInfoFormModel->familySpouseLastName = $serverModel->familySpouseLastName;
            $this->clientModel->applicationFormModel->familyInfoFormModel->familyKids = booleanize($serverModel->familyKids);
            $this->clientModel->applicationFormModel->familyInfoFormModel->familyKidsNamesAges = $serverModel->familyKidsNamesAges;

            $this->clientModel->applicationFormModel->addressInfoFormModel->address = $serverModel->address;
            $this->clientModel->applicationFormModel->addressInfoFormModel->city = $serverModel->city;
            $this->clientModel->applicationFormModel->addressInfoFormModel->zipCode = $serverModel->zipCode;
            $this->clientModel->applicationFormModel->addressInfoFormModel->country = $serverModel->country;

            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianFirstName = $serverModel->guardianFirstName;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianLastName = $serverModel->guardianLastName;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianOccupation = $serverModel->guardianOccupation;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianEmail = $serverModel->guardianEmail;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianPhone = $serverModel->guardianPhone;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianAddressSame = booleanize($serverModel->guardianAddressSame);
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianAddress = $serverModel->guardianAddress;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianCity = $serverModel->guardianCity;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianZipCode = $serverModel->guardianZipCode;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianCountry = $serverModel->guardianCountry;
            $this->clientModel->applicationFormModel->guardianInfoFormModel->guardianOpinion = $serverModel->guardianOpinion;
        }

        function updateServerModel($id, $clientModel){
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->birthDate)? $clientModel->applicationFormModel->idInfoFormModel->birthDate: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->birthPlace)? $clientModel->applicationFormModel->idInfoFormModel->birthPlace: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->phone)? $clientModel->applicationFormModel->idInfoFormModel->phone: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->occupation)? $clientModel->applicationFormModel->idInfoFormModel->occupation: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->greekCitizen)? $clientModel->applicationFormModel->idInfoFormModel->greekCitizen: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->greekIdNumber)? $clientModel->applicationFormModel->idInfoFormModel->greekIdNumber: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->greekSsn)? $clientModel->applicationFormModel->idInfoFormModel->greekSsn: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->irsOffice)? $clientModel->applicationFormModel->idInfoFormModel->irsOffice: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->citizenship)? $clientModel->applicationFormModel->idInfoFormModel->citizenship: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->euCitizen)? ($clientModel->applicationFormModel->idInfoFormModel->euCitizen): null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->passportNumber)? $clientModel->applicationFormModel->idInfoFormModel->passportNumber: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->idInfoFormModel->residencePermit)? ($clientModel->applicationFormModel->idInfoFormModel->residencePermit): null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->familyInfoFormModel->familyStatus)? $clientModel->applicationFormModel->familyInfoFormModel->familyStatus: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->familyInfoFormModel->familySpouseFirstName)? $clientModel->applicationFormModel->familyInfoFormModel->familySpouseFirstName: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->familyInfoFormModel->familySpouseLastName)? $clientModel->applicationFormModel->familyInfoFormModel->familySpouseLastName: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->familyInfoFormModel->familyKids)? $clientModel->applicationFormModel->familyInfoFormModel->familyKids: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->familyInfoFormModel->familyKidsNamesAges)? $clientModel->applicationFormModel->familyInfoFormModel->familyKidsNamesAges: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->addressInfoFormModel->address)? $clientModel->applicationFormModel->addressInfoFormModel->address: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->addressInfoFormModel->city)? $clientModel->applicationFormModel->addressInfoFormModel->city: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->addressInfoFormModel->zipCode)? $clientModel->applicationFormModel->addressInfoFormModel->zipCode: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->addressInfoFormModel->country)? $clientModel->applicationFormModel->addressInfoFormModel->country: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianFirstName)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianFirstName: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianLastName)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianLastName: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianOccupation)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianOccupation: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianEmail)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianEmail: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianPhone)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianPhone: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianAddressSame)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianAddressSame: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianAddress)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianAddress: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianCity)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianCity: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianZipCode)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianZipCode: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianCountry)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianCountry: null;
            $this->serverModel[] = isset($clientModel->applicationFormModel->guardianInfoFormModel->guardianOpinion)? $clientModel->applicationFormModel->guardianInfoFormModel->guardianOpinion: null;

            $this->serverModel = array_merge($this->serverModel, $this->serverModel);
            array_unshift($this->serverModel, $id);
        }
    }

    class HealthFormModel {
        public $serverModel;
        public $clientModel;
        
        function __construct($id, $_serverModel = null, $_clientModel = null){
            if(isset($_serverModel)){
                $this->serverModel = $_serverModel;
                $this->updateClientModel($_serverModel);
            } elseif(isset($_clientModel)){
                $this->clientModel = $_clientModel;
                $this->updateServerModel($id, $_clientModel);
            }
        }

        function updateClientModel($serverModel){
            $this->clientModel = (object) [
                "healthFormModel" => (object) [
                    "healthHistoryModel" => new stdClass(),
                    "drugsUseModel" => new stdClass(),
                    "currentHealthModel" => new stdClass(),
                    "emergencyContactsModel" => new stdClass()
                ]
            ];

            $this->clientModel->healthFormModel->healthHistoryModel->tonsillitis = booleanize($serverModel->tonsillitis);
            $this->clientModel->healthFormModel->healthHistoryModel->chickenPox = booleanize($serverModel->chickenPox);
            $this->clientModel->healthFormModel->healthHistoryModel->bronchialAsthma = booleanize($serverModel->bronchialAsthma);
            $this->clientModel->healthFormModel->healthHistoryModel->diphtheria = booleanize($serverModel->diphtheria);
            $this->clientModel->healthFormModel->healthHistoryModel->epilepsy = booleanize($serverModel->epilepsy);
            $this->clientModel->healthFormModel->healthHistoryModel->rubella = booleanize($serverModel->rubella);
            $this->clientModel->healthFormModel->healthHistoryModel->measles = booleanize($serverModel->measles);
            $this->clientModel->healthFormModel->healthHistoryModel->yellowFever = booleanize($serverModel->yellowFever);
            $this->clientModel->healthFormModel->healthHistoryModel->meningitis = booleanize($serverModel->meningitis);
            $this->clientModel->healthFormModel->healthHistoryModel->mumps = booleanize($serverModel->mumps);
            $this->clientModel->healthFormModel->healthHistoryModel->polio = booleanize($serverModel->polio);
            $this->clientModel->healthFormModel->healthHistoryModel->cholera = booleanize($serverModel->cholera);
            $this->clientModel->healthFormModel->healthHistoryModel->heartAbnormality = booleanize($serverModel->heartAbnormality);
            $this->clientModel->healthFormModel->healthHistoryModel->otherDiseases = booleanize($serverModel->otherDiseases);
            $this->clientModel->healthFormModel->healthHistoryModel->otherDiseasesDetails = $serverModel->otherDiseasesDetails;
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineDiphtheria = booleanize($serverModel->vaccineDiphtheria);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccinePertussis = booleanize($serverModel->vaccinePertussis);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineTetanus = booleanize($serverModel->vaccineTetanus);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineSmallpox = booleanize($serverModel->vaccineSmallpox);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineRubella = booleanize($serverModel->vaccineRubella);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineMeasles = booleanize($serverModel->vaccineMeasles);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineMumps = booleanize($serverModel->vaccineMumps);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccinePolio = booleanize($serverModel->vaccinePolio);
            $this->clientModel->healthFormModel->healthHistoryModel->vaccineCholera = booleanize($serverModel->vaccineCholera);
            $this->clientModel->healthFormModel->healthHistoryModel->otherVaccines = booleanize($serverModel->otherVaccines);
            $this->clientModel->healthFormModel->healthHistoryModel->otherVaccinesDetails = $serverModel->otherVaccinesDetails;

            $this->clientModel->healthFormModel->drugsUseModel->drugsUse = booleanize($serverModel->drugsUse);
            $this->clientModel->healthFormModel->drugsUseModel->drugsUseDetails = $serverModel->drugsUseDetails;

            $this->clientModel->healthFormModel->currentHealthModel->currentDiseases = booleanize($serverModel->currentDiseases);
            $this->clientModel->healthFormModel->currentHealthModel->currentDiseasesDetails = $serverModel->currentDiseasesDetails;
            $this->clientModel->healthFormModel->currentHealthModel->currentSymptoms = booleanize($serverModel->currentSymptoms);
            $this->clientModel->healthFormModel->currentHealthModel->currentSymptomsDetails = $serverModel->currentSymptomsDetails;
            $this->clientModel->healthFormModel->currentHealthModel->currentMedicines = booleanize($serverModel->currentMedicines);
            $this->clientModel->healthFormModel->currentHealthModel->currentMedicinesDetails = $serverModel->currentMedicinesDetails;
            $this->clientModel->healthFormModel->currentHealthModel->foodAllergy = booleanize($serverModel->foodAllergy);
            $this->clientModel->healthFormModel->currentHealthModel->foodAllergyDetails = $serverModel->foodAllergyDetails;
            
            $this->clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactFirstName = $serverModel->firstEmergencyContactFirstName;
            $this->clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactLastName = $serverModel->firstEmergencyContactLastName;
            $this->clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactPhone = $serverModel->firstEmergencyContactPhone;
            $this->clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactFirstName = $serverModel->secondEmergencyContactFirstName;
            $this->clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactLastName = $serverModel->secondEmergencyContactLastName;
            $this->clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactPhone = $serverModel->secondEmergencyContactPhone;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctor = booleanize($serverModel->doctor);
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorFirstName = $serverModel->doctorFirstName;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorLastName = $serverModel->doctorLastName;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorPhone = $serverModel->doctorPhone;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorAddress = $serverModel->doctorAddress;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorCity = $serverModel->doctorCity;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorZipCode = $serverModel->doctorZipCode;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorCountry = $serverModel->doctorCountry;
            $this->clientModel->healthFormModel->emergencyContactsModel->doctorContactApproval = booleanize($serverModel->doctorContactApproval);
            $this->clientModel->healthFormModel->emergencyContactsModel->otherDoctorContactApproval = booleanize($serverModel->otherDoctorContactApproval);
        }

        function updateServerModel($id, $clientModel){
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->tonsillitis)? $clientModel->healthFormModel->healthHistoryModel->tonsillitis: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->chickenPox)? $clientModel->healthFormModel->healthHistoryModel->chickenPox: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->bronchialAsthma)? $clientModel->healthFormModel->healthHistoryModel->bronchialAsthma: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->diphtheria)? $clientModel->healthFormModel->healthHistoryModel->diphtheria: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->epilepsy)? $clientModel->healthFormModel->healthHistoryModel->epilepsy: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->rubella)? $clientModel->healthFormModel->healthHistoryModel->rubella: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->measles)? $clientModel->healthFormModel->healthHistoryModel->measles: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->yellowFever)? $clientModel->healthFormModel->healthHistoryModel->yellowFever: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->meningitis)? $clientModel->healthFormModel->healthHistoryModel->meningitis: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->mumps)? $clientModel->healthFormModel->healthHistoryModel->mumps: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->polio)? $clientModel->healthFormModel->healthHistoryModel->polio: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->cholera)? $clientModel->healthFormModel->healthHistoryModel->cholera: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->heartAbnormality)? $clientModel->healthFormModel->healthHistoryModel->heartAbnormality: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->otherDiseases)? $clientModel->healthFormModel->healthHistoryModel->otherDiseases: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->otherDiseasesDetails)? $clientModel->healthFormModel->healthHistoryModel->otherDiseasesDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineDiphtheria)? $clientModel->healthFormModel->healthHistoryModel->vaccineDiphtheria: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccinePertussis)? $clientModel->healthFormModel->healthHistoryModel->vaccinePertussis: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineTetanus)? $clientModel->healthFormModel->healthHistoryModel->vaccineTetanus: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineSmallpox)? $clientModel->healthFormModel->healthHistoryModel->vaccineSmallpox: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineRubella)? $clientModel->healthFormModel->healthHistoryModel->vaccineRubella: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineMeasles)? $clientModel->healthFormModel->healthHistoryModel->vaccineMeasles: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineMumps)? $clientModel->healthFormModel->healthHistoryModel->vaccineMumps: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccinePolio)? $clientModel->healthFormModel->healthHistoryModel->vaccinePolio: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->vaccineCholera)? $clientModel->healthFormModel->healthHistoryModel->vaccineCholera: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->otherVaccines)? $clientModel->healthFormModel->healthHistoryModel->otherVaccines: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->healthHistoryModel->otherVaccinesDetails)? $clientModel->healthFormModel->healthHistoryModel->otherVaccinesDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->drugsUseModel->drugsUse)? $clientModel->healthFormModel->drugsUseModel->drugsUse: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->drugsUseModel->drugsUseDetails)? $clientModel->healthFormModel->drugsUseModel->drugsUseDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->currentDiseases)? $clientModel->healthFormModel->currentHealthModel->currentDiseases: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->currentDiseasesDetails)? $clientModel->healthFormModel->currentHealthModel->currentDiseasesDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->currentSymptoms)? $clientModel->healthFormModel->currentHealthModel->currentSymptoms: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->currentSymptomsDetails)? $clientModel->healthFormModel->currentHealthModel->currentSymptomsDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->currentMedicines)? $clientModel->healthFormModel->currentHealthModel->currentMedicines: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->currentMedicinesDetails)? $clientModel->healthFormModel->currentHealthModel->currentMedicinesDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->foodAllergy)? $clientModel->healthFormModel->currentHealthModel->foodAllergy: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->currentHealthModel->foodAllergyDetails)? $clientModel->healthFormModel->currentHealthModel->foodAllergyDetails: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactFirstName)? $clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactFirstName: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactLastName)? $clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactLastName: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactPhone)? $clientModel->healthFormModel->emergencyContactsModel->firstEmergencyContactPhone: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactFirstName)? $clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactFirstName: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactLastName)? $clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactLastName: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactPhone)? $clientModel->healthFormModel->emergencyContactsModel->secondEmergencyContactPhone: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctor)? $clientModel->healthFormModel->emergencyContactsModel->doctor: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorFirstName)? $clientModel->healthFormModel->emergencyContactsModel->doctorFirstName: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorLastName)? $clientModel->healthFormModel->emergencyContactsModel->doctorLastName: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorPhone)? $clientModel->healthFormModel->emergencyContactsModel->doctorPhone: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorAddress)? $clientModel->healthFormModel->emergencyContactsModel->doctorAddress: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorCity)? $clientModel->healthFormModel->emergencyContactsModel->doctorCity: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorZipCode)? $clientModel->healthFormModel->emergencyContactsModel->doctorZipCode: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorCountry)? $clientModel->healthFormModel->emergencyContactsModel->doctorCountry: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->doctorContactApproval)? $clientModel->healthFormModel->emergencyContactsModel->doctorContactApproval: null;
            $this->serverModel[] = isset($clientModel->healthFormModel->emergencyContactsModel->otherDoctorContactApproval)? $clientModel->healthFormModel->emergencyContactsModel->otherDoctorContactApproval: null;

            $this->serverModel = array_merge($this->serverModel, $this->serverModel);
            array_unshift($this->serverModel, $id);
        }
    }

    class EducationFormModel {
        public $serverModel;
        public $clientModel;
        
        function __construct($id, $_serverModel = null, $_clientModel = null){
            if(isset($_serverModel)){
                $this->serverModel = $_serverModel;
                $this->updateClientModel($_serverModel);
            } elseif(isset($_clientModel)){
                $this->clientModel = $_clientModel;
                $this->updateServerModel($id, $_clientModel);
            }
        }

        function updateClientModel($serverModel){
            $this->clientModel = (object) [
                "educationFormModel" => (object) [
                    "primarySecondaryFormModel" => new stdClass(),
                    "languagesFormModel" => new stdClass(),
                    "higherFormModel" => new stdClass()
                ]
            ];

            $this->clientModel->educationFormModel->primarySecondaryFormModel->elementaryName = $serverModel->elementaryName;
            $this->clientModel->educationFormModel->primarySecondaryFormModel->elementaryGraduationYear = $serverModel->elementaryGraduationYear;
            $this->clientModel->educationFormModel->primarySecondaryFormModel->middleSchoolName = $serverModel->middleSchoolName;
            $this->clientModel->educationFormModel->primarySecondaryFormModel->middleSchoolGraduationYear = $serverModel->middleSchoolGraduationYear;
            $this->clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolName = $serverModel->secondarySchoolName;
            $this->clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolGraduationYear = $serverModel->secondarySchoolGraduationYear;
            $this->clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolDiscipline = $serverModel->secondarySchoolDiscipline;

            $this->clientModel->educationFormModel->languagesFormModel->greek = $serverModel->greek;
            $this->clientModel->educationFormModel->languagesFormModel->english = $serverModel->english;

            $this->clientModel->educationFormModel->higherFormModel->communityCollegeName = $serverModel->communityCollegeName;
            $this->clientModel->educationFormModel->higherFormModel->communityCollegeGraduationYear = $serverModel->communityCollegeGraduationYear;
            $this->clientModel->educationFormModel->higherFormModel->communityCollegeDiscipline = $serverModel->communityCollegeDiscipline;
            $this->clientModel->educationFormModel->higherFormModel->collegeName = $serverModel->collegeName;
            $this->clientModel->educationFormModel->higherFormModel->collegeGraduationYear = $serverModel->collegeGraduationYear;
            $this->clientModel->educationFormModel->higherFormModel->collegeDiscipline = $serverModel->collegeDiscipline;
            $this->clientModel->educationFormModel->higherFormModel->graduateSchoolName = $serverModel->graduateSchoolName;
            $this->clientModel->educationFormModel->higherFormModel->graduateSchoolGraduationYear = $serverModel->graduateSchoolGraduationYear;
            $this->clientModel->educationFormModel->higherFormModel->graduateSchoolDiscipline = $serverModel->graduateSchoolDiscipline;
        }

        function updateServerModel($id, $clientModel){
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->elementaryName)? $clientModel->educationFormModel->primarySecondaryFormModel->elementaryName: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->elementaryGraduationYear)? $clientModel->educationFormModel->primarySecondaryFormModel->elementaryGraduationYear: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->middleSchoolName)? $clientModel->educationFormModel->primarySecondaryFormModel->middleSchoolName: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->middleSchoolGraduationYear)? $clientModel->educationFormModel->primarySecondaryFormModel->middleSchoolGraduationYear: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolName)? $clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolName: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolGraduationYear)? $clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolGraduationYear: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolDiscipline)? $clientModel->educationFormModel->primarySecondaryFormModel->secondarySchoolDiscipline: null;

            $this->serverModel[] = isset($clientModel->educationFormModel->languagesFormModel->greek)? $clientModel->educationFormModel->languagesFormModel->greek: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->languagesFormModel->english)? $clientModel->educationFormModel->languagesFormModel->english: null;

            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->communityCollegeName)? $clientModel->educationFormModel->higherFormModel->communityCollegeName: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->communityCollegeGraduationYear)? $clientModel->educationFormModel->higherFormModel->communityCollegeGraduationYear: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->communityCollegeDiscipline)? $clientModel->educationFormModel->higherFormModel->communityCollegeDiscipline: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->collegeName)? $clientModel->educationFormModel->higherFormModel->collegeName: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->collegeGraduationYear)? $clientModel->educationFormModel->higherFormModel->collegeGraduationYear: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->collegeDiscipline)? $clientModel->educationFormModel->higherFormModel->collegeDiscipline: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->graduateSchoolName)? $clientModel->educationFormModel->higherFormModel->graduateSchoolName: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->graduateSchoolGraduationYear)? $clientModel->educationFormModel->higherFormModel->graduateSchoolGraduationYear: null;
            $this->serverModel[] = isset($clientModel->educationFormModel->higherFormModel->graduateSchoolDiscipline)? $clientModel->educationFormModel->higherFormModel->graduateSchoolDiscipline: null;

            $this->serverModel = array_merge($this->serverModel, $this->serverModel);
            array_unshift($this->serverModel, $id);
        }
    }

    class ChristianLifeFormModel {
        public $serverModel;
        public $clientModel;
        
        function __construct($id, $_serverModel = null, $_clientModel = null){
            if(isset($_serverModel)){
                $this->serverModel = $_serverModel;
                $this->updateClientModel($_serverModel);
            } elseif(isset($_clientModel)){
                $this->clientModel = $_clientModel;
                $this->updateServerModel($id, $_clientModel);
            }
        }

        function updateClientModel($serverModel){
            $this->clientModel = (object) [
                "christianLifeFormModel" => (object) [
                    "churchMinistryFormModel" => new stdClass(),
                    "testimonyFormModel" => new stdClass()
                ]
            ];

            $this->clientModel->christianLifeFormModel->churchMinistryFormModel->churchName = $serverModel->churchName;
            $this->clientModel->christianLifeFormModel->churchMinistryFormModel->churchMember = booleanize($serverModel->churchMember);
            $this->clientModel->christianLifeFormModel->churchMinistryFormModel->churchMemberHowLong = $serverModel->churchMemberHowLong;
            $this->clientModel->christianLifeFormModel->churchMinistryFormModel->ministryTalent = $serverModel->ministryTalent;
            $this->clientModel->christianLifeFormModel->churchMinistryFormModel->ministryExperience = $serverModel->ministryExperience;

            $this->clientModel->christianLifeFormModel->testimonyFormModel->testimony = $serverModel->testimony;
            $this->clientModel->christianLifeFormModel->testimonyFormModel->statementOfFaithApproval = booleanize($serverModel->statementOfFaithApproval);
        }

        function updateServerModel($id, $clientModel){
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->churchMinistryFormModel->churchName)? $clientModel->christianLifeFormModel->churchMinistryFormModel->churchName: null;
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->churchMinistryFormModel->churchMember)? $clientModel->christianLifeFormModel->churchMinistryFormModel->churchMember: null;
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->churchMinistryFormModel->churchMemberHowLong)? $clientModel->christianLifeFormModel->churchMinistryFormModel->churchMemberHowLong: null;
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->churchMinistryFormModel->ministryTalent)? $clientModel->christianLifeFormModel->churchMinistryFormModel->ministryTalent: null;
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->churchMinistryFormModel->ministryExperience)? $clientModel->christianLifeFormModel->churchMinistryFormModel->ministryExperience: null;
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->testimonyFormModel->testimony)? $clientModel->christianLifeFormModel->testimonyFormModel->testimony: null;
            $this->serverModel[] = isset($clientModel->christianLifeFormModel->testimonyFormModel->statementOfFaithApproval)? $clientModel->christianLifeFormModel->testimonyFormModel->statementOfFaithApproval: null;

            $this->serverModel = array_merge($this->serverModel, $this->serverModel);
            array_unshift($this->serverModel, $id);
        }
    }

    class ReferencesFormModel {
        public $serverModel;
        public $clientModel;
        
        function __construct($id, $_serverModel = null, $_clientModel = null){
            if(isset($_serverModel)){
                $this->serverModel = $_serverModel;
                $this->updateClientModel($_serverModel);
            } elseif(isset($_clientModel)){
                $this->clientModel = $_clientModel;
                $this->updateServerModel($id, $_clientModel);
            }
        }

        function updateClientModel($serverModel){
            $this->clientModel = (object) [
                "referencesFormModel" => (object) [
                    "firstReference" => new stdClass(),
                    "secondReference" => new stdClass(),
                    "thirdReference" => new stdClass()
                ]
            ];

            $this->clientModel->referencesFormModel->firstReference->firstReferenceId = ($serverModel->firstReferenceId === null) ? null : $serverModel->firstReferenceId;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceFirstName = $serverModel->firstReferenceFirstName;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceLastName = $serverModel->firstReferenceLastName;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceEmail = $serverModel->firstReferenceEmail;
            $this->clientModel->referencesFormModel->firstReference->firstReferencePhone = $serverModel->firstReferencePhone;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceAddress = $serverModel->firstReferenceAddress;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceCity = $serverModel->firstReferenceCity;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceZipCode = $serverModel->firstReferenceZipCode;
            $this->clientModel->referencesFormModel->firstReference->firstReferenceCountry = $serverModel->firstReferenceCountry;

            $this->clientModel->referencesFormModel->secondReference->secondReferenceId = ($serverModel->secondReferenceId === null) ? null : $serverModel->secondReferenceId;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceFirstName = $serverModel->secondReferenceFirstName;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceLastName = $serverModel->secondReferenceLastName;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceEmail = $serverModel->secondReferenceEmail;
            $this->clientModel->referencesFormModel->secondReference->secondReferencePhone = $serverModel->secondReferencePhone;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceAddress = $serverModel->secondReferenceAddress;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceCity = $serverModel->secondReferenceCity;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceZipCode = $serverModel->secondReferenceZipCode;
            $this->clientModel->referencesFormModel->secondReference->secondReferenceCountry = $serverModel->secondReferenceCountry;

            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceId = ($serverModel->thirdReferenceId === null) ? null : $serverModel->thirdReferenceId;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceFirstName = $serverModel->thirdReferenceFirstName;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceLastName = $serverModel->thirdReferenceLastName;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceEmail = $serverModel->thirdReferenceEmail;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferencePhone = $serverModel->thirdReferencePhone;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceAddress = $serverModel->thirdReferenceAddress;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceCity = $serverModel->thirdReferenceCity;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceZipCode = $serverModel->thirdReferenceZipCode;
            $this->clientModel->referencesFormModel->thirdReference->thirdReferenceCountry = $serverModel->thirdReferenceCountry;
        }

        function updateServerModel($id, $clientModel){
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceFirstName)? $clientModel->referencesFormModel->firstReference->firstReferenceFirstName: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceLastName)? $clientModel->referencesFormModel->firstReference->firstReferenceLastName: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceEmail)? $clientModel->referencesFormModel->firstReference->firstReferenceEmail: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferencePhone)? $clientModel->referencesFormModel->firstReference->firstReferencePhone: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceAddress)? $clientModel->referencesFormModel->firstReference->firstReferenceAddress: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceCity)? $clientModel->referencesFormModel->firstReference->firstReferenceCity: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceZipCode)? $clientModel->referencesFormModel->firstReference->firstReferenceZipCode: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->firstReference->firstReferenceCountry)? $clientModel->referencesFormModel->firstReference->firstReferenceCountry: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceFirstName)? $clientModel->referencesFormModel->secondReference->secondReferenceFirstName: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceLastName)? $clientModel->referencesFormModel->secondReference->secondReferenceLastName: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceEmail)? $clientModel->referencesFormModel->secondReference->secondReferenceEmail: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferencePhone)? $clientModel->referencesFormModel->secondReference->secondReferencePhone: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceAddress)? $clientModel->referencesFormModel->secondReference->secondReferenceAddress: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceCity)? $clientModel->referencesFormModel->secondReference->secondReferenceCity: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceZipCode)? $clientModel->referencesFormModel->secondReference->secondReferenceZipCode: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->secondReference->secondReferenceCountry)? $clientModel->referencesFormModel->secondReference->secondReferenceCountry: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceFirstName)? $clientModel->referencesFormModel->thirdReference->thirdReferenceFirstName: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceLastName)? $clientModel->referencesFormModel->thirdReference->thirdReferenceLastName: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceEmail)? $clientModel->referencesFormModel->thirdReference->thirdReferenceEmail: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferencePhone)? $clientModel->referencesFormModel->thirdReference->thirdReferencePhone: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceAddress)? $clientModel->referencesFormModel->thirdReference->thirdReferenceAddress: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceCity)? $clientModel->referencesFormModel->thirdReference->thirdReferenceCity: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceZipCode)? $clientModel->referencesFormModel->thirdReference->thirdReferenceZipCode: null;
            $this->serverModel[] = isset($clientModel->referencesFormModel->thirdReference->thirdReferenceCountry)? $clientModel->referencesFormModel->thirdReference->thirdReferenceCountry: null;

            $this->serverModel = array_merge($this->serverModel, $this->serverModel);
            array_unshift($this->serverModel, $id);
        }
    }

    class FinancialFormModel {
        public $serverModel;
        public $clientModel;
        
        function __construct($id, $_serverModel = null, $_clientModel = null){
            if(isset($_serverModel)){
                $this->serverModel = $_serverModel;
                $this->updateClientModel($_serverModel);
            } elseif(isset($_clientModel)){
                $this->clientModel = $_clientModel;
                $this->updateServerModel($id, $_clientModel);
            }
        }

        function updateClientModel($serverModel){
            $this->clientModel = (object) [
                "financialFormModel" => (object) [
                    "approvalsFormModel" => new stdClass()
                ]
            ];

            $this->clientModel->financialFormModel->approvalsFormModel->studentPackage = $serverModel->studentPackage;
            $this->clientModel->financialFormModel->approvalsFormModel->financialApproval = booleanize($serverModel->financialApproval);
            $this->clientModel->financialFormModel->approvalsFormModel->selfPaid = booleanize($serverModel->selfPaid);
            $this->clientModel->financialFormModel->approvalsFormModel->sponsors = $serverModel->sponsors;
            $this->clientModel->financialFormModel->approvalsFormModel->sponsorsTotal = $serverModel->sponsorsTotal;
            $this->clientModel->financialFormModel->approvalsFormModel->debtApproval = booleanize($serverModel->debtApproval);
            $this->clientModel->financialFormModel->approvalsFormModel->deposit = $serverModel->deposit;
        }

        function updateServerModel($id, $clientModel){
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->studentPackage)? $clientModel->financialFormModel->approvalsFormModel->studentPackage: null;
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->financialApproval)? $clientModel->financialFormModel->approvalsFormModel->financialApproval: null;
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->selfPaid)? $clientModel->financialFormModel->approvalsFormModel->selfPaid: null;
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->sponsors)? $clientModel->financialFormModel->approvalsFormModel->sponsors: null;
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->sponsorsTotal)? $clientModel->financialFormModel->approvalsFormModel->sponsorsTotal: null;
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->debtApproval)? $clientModel->financialFormModel->approvalsFormModel->debtApproval: null;
            $this->serverModel[] = isset($clientModel->financialFormModel->approvalsFormModel->deposit)? $clientModel->financialFormModel->approvalsFormModel->deposit: null;

            $this->serverModel = array_merge($this->serverModel, $this->serverModel);
            array_unshift($this->serverModel, $id);
        }
    }
//// END *** Form Model Processing ***