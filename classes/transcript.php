<?

class Transcript {
    function __construct(){
        global $session;

        $this->db = $session->db;
    }

    function getStudentEnglishTranscript($params){
        $transcript = new stdClass();

        // Get Grades Data
        $columns = [
            ($params->lang === 'englishECTS') ? 'ects_credits credits': 'credits',
            'grade',
            'gradeSemester',
            'gradeYear'
        ];
        if($params->lang === 'english' || $params->lang === 'englishECTS'){
            array_push($columns, 'CONCAT(admin_course_categories.code_en, " ", CODE) AS code');
            array_push($columns, 'admin_courses.name_en AS name');
            $order = 'gradeYear, gradeSemester DESC, admin_courses.code_en';
        } elseif($params->lang === 'greek'){
            array_push($columns, 'CONCAT(admin_course_categories.code_gr, " ", CODE) AS code');
            array_push($columns, 'admin_courses.name_gr AS name');
            $order = 'gradeYear, gradeSemester DESC, admin_courses.code_gr';
        }
        $results = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => $columns,
            'table' => 'admin_course_enrollment',
            'joins' => [
                'JOIN admin_courses ON admin_courses.id = admin_course_enrollment.course_id',
                'JOIN admin_course_categories ON admin_course_categories.id = category_id'
            ],
            'where' => ['student_id = ? AND grade IS NOT NULL', $params->id],
            'order' => $order
        ]);

        // If no Grades Data, return "not enough data" error
        if (count($results) === 0) {
            return (object) [
                "success" => false,
                "reason" => "User has no Grades recorded"
            ];
        }

        // Process Grades Data
        $transcript->semesters = new StdClass();
        $currentYear = '';
        $currentSemester = '';
        for($i = 0; $i < count($results); $i++){
            if($currentYear !== $results[$i]->gradeYear || $currentSemester !== $results[$i]->gradeSemester){
                $currentYear = $results[$i]->gradeYear;
                $currentSemester = $results[$i]->gradeSemester;

                if($params->lang === 'english' || $params->lang === 'englishECTS'){
                    $semesterTitle = ($results[$i]->gradeSemester === '1' ? 'Fall ' : 'Spring ').$results[$i]->gradeYear;
                } elseif($params->lang === 'greek'){
                    $semesterTitle = ($results[$i]->gradeSemester === '1' ? 'Φθινόπωρο ' : 'Άνοιξη ').$results[$i]->gradeYear;
                }
                $transcript->semesters->{$semesterTitle} = [];
            }
            array_push($transcript->semesters->{$semesterTitle}, $results[$i]);
        }

        // Get User Date of Birth
        $applications = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'dbTable',
            'table' => 'admin_user_applications',
            'joins' => 'LEFT JOIN admin_applications ON admin_applications.id = admin_user_applications.applicationId',
            'where' => ['userId = ?', $params->id]
        ]);
        if (count($applications) === 0) {
            return (object) [
                "success" => false,
                "reason" => "User Application Missing"
            ];
        }

        foreach ($applications as $application) {
            try {
                $transcript->personal = $this->db->sql1([
                    'statement' => 'SELECT',
                    'columns' => [
                        'CONCAT(SUBSTR(admin_users.date_time, 1, 4), admin_users.id) AS id',
                        'lastName',
                        'firstName',
                        'birthDate'
                    ],
                    'table' => 'admin_applications_'.$application->dbTable,
                    'joins' => 'LEFT JOIN admin_users ON admin_users.id = admin_applications_'.$application->dbTable.'.userId',
                    'where' => ['userId = ?', $params->id]
                ]);
                break;
            } catch(Exception $e) {
                continue;
            }
        }

        return new AjaxResponse($transcript);
    }

    function getStudentTranscriptPDF($params){
        $transcript = $this->getStudentEnglishTranscript($params);
        if($transcript->success === false) {
            dbg($transcript->reason);
        }
        if($params->lang === 'english' || $params->lang === 'englishECTS'){
            $elot743 = new Elot743();
            $transcript->data->personal->firstName = $elot743->convert($transcript->data->personal->firstName);
            $transcript->data->personal->lastName = $elot743->convert($transcript->data->personal->lastName);
        }


        function HR($pdf, $before = false, $noLinesOffset = 1){
            $y = $pdf->GetY();
            if($before){
                $pdf->Line(10,$y - $noLinesOffset * 8 + 1,200,$y - $noLinesOffset * 8 + 1);
            } else {
                $pdf->Line(10,$y + $noLinesOffset * 8 + 1,200,$y + $noLinesOffset * 8 + 1);
            }
        }
    
        function HeaderLine($pdf, $text, $fontSize, $ln){
            $pdf->SetFont('Cardo','',$fontSize);
            $pdf->Cell(0, 5, $text, 0, 0, 'C');
            if($ln) {
                $pdf->Ln($ln);
            }
        }

        function IdLine($pdf, $text, $fontSize, $ln, $bold = false){
            if($bold) {
                $pdf->SetFont('Cardob', '', $fontSize);
            } else {
                $pdf->SetFont('Cardo', '', $fontSize);
            }
            $pdf->Cell(28, 5, $text, 0, 0);
            if($ln) {
                $pdf->Ln($ln);
            }
        }

        function IdLineGreek($pdf, $text, $fontSize, $ln, $bold = false){
            if($bold) {
                $pdf->SetFont('Cardob', '', $fontSize);
            } else {
                $pdf->SetFont('Cardo', '', $fontSize);
            }
            $pdf->Cell(42, 5, $text, 0, 0);
            if($ln) {
                $pdf->Ln($ln);
            }
        }

        function TableTitle($pdf, $text){
            $pdf->SetFont('Cardob', '', 9);
            $pdf->SetFillColor(217, 226, 243);
            $pdf->Cell(0, 5, $text, 1, 0, '', true);
            $pdf->Ln(5);
        }

        function TableHeader($pdf, $text, $width, $centered = '', $border = 1){
            $pdf->SetFont('Cardob', '', 9);
            $pdf->Cell($width, 5, $text, $border, 0, $centered);
        }

        function TableCell($pdf, $text, $width, $centered = ''){
            $pdf->SetFont('Cardo', '', 9);
            $pdf->Cell($width, 5, $text, 1, 0, $centered);
        }

        require_once('libs/tfpdf/tfpdf.php');
        $pdf = new tFPDF();
        $pdf->AddFont('Cardo','','cardo-regular.ttf',true);
        $pdf->AddFont('Cardob','','cardo-bold.ttf',true);
        $pdf->AddFont('Calibri','','calibri.ttf',true);
        $pdf->AddFont('Calibrib','b','calibrib.ttf',true);
        $pdf->cMargin = 1;

        function printPage1($pdf, $transcript, $language){
            $x0 = $pdf->getX();
            $y0 = $pdf->getY();
            $pdf->Image('watermark.png', 22, 40, 160);
            $pdf->setX($x0);
            $pdf->setY($y0);

            $x0 = $pdf->getX();
            $y0 = $pdf->getY();
            if($language === 'english' || $language === 'englishECTS'){
                $pdf->Image('logo-en.png', 172, null, 36);
            } elseif($language === 'greek'){
                $pdf->Image('logo-el.png', 172, null, 36);
            }
            $pdf->setX($x0);
            $pdf->setY($y0);

            $pdf->SetFont('Cardob','',14);
            if($language === 'english' || $language === 'englishECTS'){
                HeaderLine($pdf, "SOCIETY OF BIBLICAL STUDIES", 14, 7);
                HeaderLine($pdf, "Accredited by the ECTΕ, a member of ICETE", 11, 5);
                HeaderLine($pdf, "Chr. Adamopoulou 8, Pikermi – Attiki 19009 (+30 210 603-8946)", 11, 10);
                HeaderLine($pdf, "Official Transcript", 12, 15);
            } elseif($language === 'greek'){
                HeaderLine($pdf, "ΕΤΑΙΡΕΙΑ ΒΙΒΛΙΚΩΝ ΣΠΟΥΔΩΝ", 14, 7);
                HeaderLine($pdf, "Αναγνωρισμένη από το ECTΕ, μέλος του ICETE", 11, 5);
                HeaderLine($pdf, "Χρ. Αδαμοπούλου 8, Πικέρμι – Αττικής Τ.Κ. 19009 (+30 210 6038 946)", 11, 10);
                HeaderLine($pdf, "Αναλυτική Βαθμολογία", 12, 15);
            }
            
            if($language === 'english' || $language === 'englishECTS'){
                IdLine($pdf, "Student Name: ", 11, 0, true);
                IdLine($pdf, $transcript->personal->lastName." ".$transcript->personal->firstName, 11, 0);
                $pdf->setX(-68);
                IdLine($pdf, "Date Issued: ", 11, 0, true);
                IdLine($pdf, date("j F Y"), 11, 4);
                IdLine($pdf, "Student ID: ", 11, 0, true);
                IdLine($pdf, $transcript->personal->id, 11, 4);
                IdLine($pdf, "Date of Birth: ", 11, 0, true);
                IdLine($pdf, date("j F Y", strtotime($transcript->personal->birthDate)), 11, 4);
                IdLine($pdf, "Major: ", 11, 0, true);
                IdLine($pdf, "Religion/Biblical Studies", 11, 12);
            } elseif($language === 'greek'){
                IdLineGreek($pdf, "Ονοματεπώνυμο: ", 11, 0, true);
                IdLineGreek($pdf, $transcript->personal->lastName." ".$transcript->personal->firstName, 11, 0);
                $pdf->setX(-68);
                IdLineGreek($pdf, "Εκδόθηκε: ", 11, 0, true);
                $pdf->setX(-40);
                IdLineGreek($pdf, fDate(), 11, 4);
                IdLineGreek($pdf, "Αριθμός Μητρώου: ", 11, 0, true);
                IdLineGreek($pdf, $transcript->personal->id, 11, 4);
                IdLineGreek($pdf, "Ημερομηνία γέννησης: ", 11, 0, true);
                IdLineGreek($pdf, fDate($transcript->personal->birthDate), 11, 4);
                IdLineGreek($pdf, "Πρόγραμμα Σπουδών: ", 11, 0, true);
                IdLineGreek($pdf, "Θεολογία/Βιβλικές Σπουδές", 11, 12);
            }

            $totalCreditsEarned = 0;
            $totalCoursesCompleted = 0;
            $greatPointAverage = 0;
            foreach($transcript->semesters as $semesterTitle => $grades){
                TableTitle($pdf, $semesterTitle);
                if($language === 'english' || $language === 'englishECTS'){
                    TableHeader($pdf, "Course ID", 30);
                    TableHeader($pdf, "Course Name", 110, 'C');
                    if($language === 'englishECTS'){
                        TableHeader($pdf, "ECTS Credit hours", 30, 'C');
                    } else {
                        TableHeader($pdf, "Credit hours", 30, 'C');
                    }
                    TableHeader($pdf, "Grade", 20, 'C');
                } elseif($language === 'greek'){
                    TableHeader($pdf, "Kωδ. Μαθήματος", 30);
                    TableHeader($pdf, "Όνομα Μαθήματος", 110, 'C');
                    TableHeader($pdf, "Διδακτικές Ώρες", 30, 'C');
                    TableHeader($pdf, "Βαθμός", 20, 'C');
                }

                $pdf->Ln(5);

                $semesterCreditsEarned = 0;
                $semesterCoursesCompleted = 0;
                $semesterPointAverage = 0;
                foreach($grades as $grade){
                    TableCell($pdf, $grade->code, 30);
                    TableCell($pdf, $grade->name, 110);
                    TableCell($pdf, $grade->credits, 30, 'C');
                    if($grade->grade === '-1.0') {
                        if($language === 'english' || $language === 'englishECTS'){
                            TableCell($pdf, 'Incomplete', 20, 'C');
                        } elseif($language === 'greek'){
                            TableCell($pdf, 'Ατελής', 20, 'C');
                        }
                    } elseif($grade->grade === '-2.0') {
                        if($language === 'english' || $language === 'englishECTS'){
                            TableCell($pdf, 'Pass', 20, 'C');
                        } elseif($language === 'greek'){
                            TableCell($pdf, 'Επιτυχία', 20, 'C');
                        }
                        $semesterCreditsEarned+= +$grade->credits;
                    } elseif($grade->grade === '-3.0') {
                        if($language === 'english' || $language === 'englishECTS'){
                            TableCell($pdf, 'No Pass', 20, 'C');
                        } elseif($language === 'greek'){
                            TableCell($pdf, 'Μη Επιτυχία', 20, 'C');
                        }
                    } else {
                        if($language === 'english' || $language === 'englishECTS'){
                            TableCell($pdf, fGrade($grade->grade, 'c'), 20, 'C');
                        } elseif($language === 'greek'){
                            TableCell($pdf, fGrade($grade->grade, 'b'), 20, 'C');
                        }
                        $semesterCreditsEarned+= +$grade->grade >= 6 ? +$grade->credits : 0;
                        $semesterCoursesCompleted++;
                        $semesterPointAverage+= +$grade->grade;
                        $totalCoursesCompleted++;
                        $greatPointAverage+= +$grade->grade;
                    }
                    $pdf->Ln(5);
                }
                // Update Total Semester Hours using Semester Hours (does not include F and Incomplete)
                $totalCreditsEarned+= $semesterCreditsEarned;
                // Calculate Semester Point Average if only Incomplete, Pass, and No Pass grades, prevent division by 0
                if ($semesterCoursesCompleted === 0) {
                    unset($semesterPointAverage);
                } else {
                    $semesterPointAverage = $semesterPointAverage / ($semesterCoursesCompleted !== 0 ? $semesterCoursesCompleted : 1);
                }

                TableHeader($pdf, "{{nodash}}", 30, '', 0);
                if($language === 'english' || $language === 'englishECTS'){
                    TableHeader($pdf, "Semester hours", 110, 'R', 0);
                } elseif($language === 'greek'){
                    TableHeader($pdf, "Εξαμηνιαίες Ώρες", 110, 'R', 0);
                }
                TableHeader($pdf, "".$semesterCreditsEarned, 30, 'C');
                if(isset($semesterPointAverage)) {
                    if($language === 'english' || $language === 'englishECTS'){
                        TableHeader($pdf, "".fGrade($semesterPointAverage, 'c'), 20, 'C');
                    } elseif($language === 'greek'){
                        TableHeader($pdf, "".fGrade($semesterPointAverage, 'n'), 20, 'C');
                    }
                } else {
                    TableHeader($pdf, "—", 20, 'C');
                }
                $pdf->Ln(5);
            }
            $greatPointAverage = $totalCoursesCompleted !== 0 ? $greatPointAverage / $totalCoursesCompleted : 0;

            $pdf->Ln(5);
            TableHeader($pdf, "{{nodash}}", 30, '', 0);
            if($language === 'english' || $language === 'englishECTS'){
                TableHeader($pdf, "Total Semester hours", 110, 'R', 0);
            } elseif($language === 'greek'){
                TableHeader($pdf, "Σύνολο Εξαμηνιαίων Ωρών", 110, 'R', 0);
            }
            TableHeader($pdf, "".$totalCreditsEarned, 30, 'C');
            TableHeader($pdf, "{{nodash}}", 20, 'C', 0);
            $pdf->Ln(5);
            TableHeader($pdf, "{{nodash}}", 30, '', 0);
            if($language === 'english' || $language === 'englishECTS'){
                TableHeader($pdf, "Grade Point Average", 110, 'R', 0);
            } elseif($language === 'greek'){
                TableHeader($pdf, "Μέσος Όρος Βαθμών", 110, 'R', 0);
            }
            TableHeader($pdf, "{{nodash}}", 30, 'C', 0);
            if($language === 'english' || $language === 'englishECTS'){
                TableHeader($pdf, "".fGrade($greatPointAverage, 'c'), 20, 'C');
            } elseif($language === 'greek'){
                TableHeader($pdf, "".fGrade($greatPointAverage, 'n'), 20, 'C');
            }
            $pdf->Ln(5);

            $pdf->Ln(5);
            $pdf->Cell(30, 10, "The student has not completed the program", 0);
            if($language === 'english' || $language === 'englishECTS'){
                $pdf->Ln(5);
                $pdf->Cell(30, 10, "GPA is based on ", 0);
                $pdf->Cell(30, 10, "A = 4", 0);
                $pdf->Ln(5);
                $pdf->Cell(30, 10, "{{nodash}}", 0);
                $pdf->Cell(30, 10, "B = 3", 0);
                $pdf->Ln(5);
                $pdf->Cell(30, 10, "{{nodash}}", 0);
                $pdf->Cell(30, 10, "C = 2", 0);
                $pdf->Ln(5);
                $pdf->Cell(30, 10, "{{nodash}}", 0);
                $pdf->Cell(30, 10, "D = 1", 0);
            }

            $pdf->Ln(10);
            $pdf->SetFont('Cardo', '', 10);
            $pdf->MultiCell(0, 4, "This transcript was released by the Registrar’s office of the Greek Bible College in a sealed envelope.\n\rIt is certified only if received unopened with the school seal on the lip of the envelope.\n\r\n\rThe Greek Bible College is accredited by the European Council for Theological Education (ECTE), a member of the International Council for Evangelical Theological Education (ICETE)", 0);

            $pdf->Ln(15);
            $pdf->SetFont('Cardob', '', 10);
            $pdf->Cell(60, 10, "{{nodash}}", 0, 0, 'C');
            $pdf->Cell(80, 10, "Academic Dean", 0, 0, 'C');
            HR($pdf);
            $pdf->Ln(10);
            $pdf->SetFont('Cardo', '', 12);
            $pdf->Cell(60, 10, "Signature", 0, 0, 'C');
            $pdf->Cell(80, 10, "Title", 0, 0, 'C');
            $pdf->Cell(60, 10, "Date", 0, 0, 'C');

            $pdf->Ln(25);
            $pdf->SetFont('Cardob', '', 10);
            $pdf->Cell(60, 10, "{{nodash}}", 0, 0, 'C');
            $pdf->Cell(80, 10, "Program Director", 0, 0, 'C');
            HR($pdf);
            $pdf->Ln(10);
            $pdf->SetFont('Cardo', '', 12);
            $pdf->Cell(60, 10, "Signature", 0, 0, 'C');
            $pdf->Cell(80, 10, "Title", 0, 0, 'C');
            $pdf->Cell(60, 10, "Date", 0, 0, 'C');

            if($pdf->PageNo() !== 1) {
                $pdf->setX(0);
                $pdf->setY(0);
                $pdf->Image('watermark.png', 22, 40, 160);
            }
        }

        $pdf->AddPage();
        printPage1($pdf, $transcript->data, $params->lang);

        $pdf->Output();
    }
}

function fGrade($grade, $f){
    if($f === 'c'){ // character
        return cGrade($grade);
    } elseif($f === 'n') { // number
        return nGrade($grade);
    } elseif($f === 'b') { // both
        return nGrade($grade).(cGrade($grade) === 'F' ? ' (F)' : '');
    }
}

function cGrade($grade) {
    $grade = round($grade, 1);
    if ($grade >= 9) {
        return 'A';
    } else if ($grade >= 8) {
        return 'B';
    } else if ($grade >= 7 ) {
        return 'C';
    } else if ($grade >= 6 ) {
        return 'D';
    // } else if ($grade >= 5) {
    //     return 'E';
    } else {
        return 'F';
    }
}

function nGrade($grade) {
    $grade = round($grade, 2);
    return "".$grade;
}