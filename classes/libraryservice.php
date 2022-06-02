<?

class LibraryService {
    function __construct(){
        global $session;
        $this->db = $session->db;
        $this->user = $session->authenticationService->user;
    }

    function enableLibraryCard($params) {
        $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'library_users',
            'columns' => 'enabled',
            'values' => true,
            'where' => ['barcode = ?', $params->barcode]
        ]);

        return new AjaxResponse();
    }

    function disableLibraryCard($params) {
        $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'library_users',
            'columns' => 'enabled',
            'values' => 0,
            'where' => ['barcode = ?', $params->barcode]
        ]);

        return new AjaxResponse();
    }

    function getLibraryCards($params) {
        $libraryCards = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'userId',
                'photoURI',
                'barcode',
                'first',
                'last',
                'library_users.email',
                'library_user_categories.name categoryName',
                'library_user_categories.credits categoryCredits',
                'library_user_categories.days categoryDays',
                'library_users.credits',
                'type',
                'enabled'
            ],
            'table' => 'library_users',
            'joins' => [
                'LEFT JOIN library_user_categories ON library_users.type = library_user_categories.categoryId',
                'LEFT JOIN admin_user_profiles ON library_users.userId = admin_user_profiles.id'
            ],
            'order' => 'enabled DESC, categoryCredits - library_users.credits DESC, last, first'
        ]);

        return new AjaxResponse($libraryCards);
    }

    function getUsersForLibrary($params) {
        $users = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_users.id',
                'firstName',
                'lastName',
                'admin_users.email',
                'photoURI'
            ],
            'table' => 'admin_users',
            'joins' => [
                'LEFT JOIN library_users ON admin_users.id = library_users.userId',
                'LEFT JOIN admin_user_profiles ON admin_users.id = admin_user_profiles.id'
            ],
            'where' => 'barcode IS NULL',
            'order' => 'lastName, firstName'
        ]);

        return new AjaxResponse($users);
    }

    function getUserCategoriesForLibrary($params) {
        $userGroups = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => ['categoryId', 'name', 'credits', 'days'],
            'table' => 'library_user_categories'
        ]);

        return new AjaxResponse($userGroups);
    }

    function saveLibraryCard($params) {
        // Check if this library card has used credits
        $result = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'COUNT(user_barcode) creditsUsed',
            'table' => 'library_checkouts',
            'where' => ['user_barcode = ?', $params->libraryCardModel->barcode]
        ]);

        if (isset($result[0]->creditsUsed) && $result[0]->creditsUsed !== null) {
            $creditsLeft = $params->libraryCardModel->categoryCredits - $result[0]->creditsUsed;
        } else {
            $creditsLeft = $params->libraryCardModel->categoryCredits;
        }
        
        $libraryCard = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'library_users',
            'columns' => ['barcode', 'userId', 'first', 'last', 'email', 'credits', 'type'],
            'values' => [$params->libraryCardModel->barcode, $params->libraryCardModel->userId, $params->libraryCardModel->first, $params->libraryCardModel->last, $params->libraryCardModel->email, $creditsLeft, $params->libraryCardModel->type],
            'update' => true
        ]);

        return new AjaxResponse($creditsLeft);
    }

    function getRecordsForLibraryCard($params) {
        if (!isset($params->barcode)) {
            $libraryUser = $this->getLibraryUserFromUserId($this->user->id);
            if (isset($libraryUser->barcode)) {
                $params->barcode = $libraryUser->barcode;
            } else {
                return new AjaxResponse([
                    "libraryUser" => [],
                    "activeRecords" => [],
                    "historicalRecords" => []
                ]);        
            }
        } else {
            $libraryUser = $this->getLibraryUserFromBarcode($params->barcode);
        }
        $activeRecords = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'book_barcode',
                'book_id reservedBookId',
                'title',
                'subtitle',
                'date_out',
                'due_date',
                'DATEDIFF(due_date, CURDATE()) daysLeft'
            ],
            'table' => 'library_checkouts',
            'joins' => [
                'LEFT JOIN library_books ON library_checkouts.book_barcode = library_books.barcode',
                'LEFT JOIN library_books_reserved ON library_books_reserved.book_id = library_books.id'
            ],
            'where' => ['user_barcode = ?', $params->barcode],
            'order' => 'due_date'
        ]);

        $historicalRecords = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'book_barcode',
                'title',
                'subtitle',
                'date_out',
                'due_date',
                'date_in'
            ],
            'table' => 'library_checkouts_history',
            'joins' => 'LEFT JOIN library_books ON library_checkouts_history.book_barcode = library_books.barcode',
            'where' => ['user_barcode = ?', $params->barcode],
            'order' => 'due_date DESC'
        ]);

        return new AjaxResponse([
            "libraryUser" => $libraryUser,
            "activeRecords" => $activeRecords,
            "historicalRecords" => $historicalRecords
        ]);
    }

    function getReservedBooks($params) {
        $reservedBooks = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'book_id reservedBookId',
                'library_books.barcode',
                'title',
                'subtitle'
            ],
            'table' => 'library_books_reserved',
            'joins' => 'LEFT JOIN library_books ON library_books_reserved.book_id = library_books.id',
            'order' => 'library_books_reserved.barcode'
        ]);

        return new AjaxResponse($reservedBooks);
    }

    function reserveBook($params) {
        $book = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id book_id',
            'table' => 'library_books',
            'where' => ['barcode = ?', $params->barcode]
        ]);
        $reservedBookId = $book[0]->book_id;

        $result = $this->db->sql([
            'statement' => 'INSERT INTO',
            'table' => 'library_books_reserved',
            'columns' => ['book_id', 'barcode'],
            'values' => [$reservedBookId, $params->barcode],
            'update' => true
        ]);

        return new AjaxResponse([
            'reservedBookId' => $reservedBookId
        ]);
    }

    function unreserveBook($params) {
        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'library_books_reserved',
            'where' => ['barcode = ?', $params->barcode]
        ]);

        return new AjaxResponse();
    }

    function getBookMeta($params) {
        $book = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'book_id reservedBookId',
                'library_books.barcode',
                'title',
                'subtitle'
            ],
            'table' => 'library_books',
            'joins' => 'LEFT JOIN library_books_reserved ON library_books_reserved.book_id = library_books.id',
            'where' => ['library_books.barcode = ?', $params->barcode]
       ]);

       return new AjaxResponse($book);
    }

    function removeBook($params) {
        $book = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'id',
            'table' => 'library_books',
            'where' => ['barcode = ?', $params->barcode]
        ]);

        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'library_search',
            'where' => ['book_id = ?', $book[0]->id]
        ]);

        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'library_books_reserved',
            'where' => ['book_id = ?', $book[0]->id]
        ]);

        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'library_books_authors',
            'where' => ['book_id = ?', $book[0]->id]
        ]);

        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'library_books_subjects',
            'where' => ['book_id = ?', $book[0]->id]
        ]);

        $this->db->sql([
            'statement' => 'DELETE FROM',
            'table' => 'library_books',
            'where' => ['id = ?', $book[0]->id]
        ]);

        return new AjaxResponse($book);
    }

    function renewBook($params) {
        $this->db->sql([
            'statement' => 'UPDATE library_checkouts SET due_date = DATE_ADD(due_date, INTERVAL 10 DAY)',
            'where' => ['book_barcode = ?', $params->barcode]
        ]);

        $record = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'title',
                'subtitle',
                'user_barcode',
                'due_date',
                'DATEDIFF(due_date, CURDATE()) daysLeft'
            ],
            'table' => 'library_checkouts',
            'joins' => 'LEFT JOIN library_books ON library_checkouts.book_barcode = library_books.barcode',
            'where' => ['book_barcode = ?', $params->barcode]
        ]);

        // Send Book Renewal Notification to Librarian
        $libraryUser = $this->getLibraryUserFromBarcode($record[0]->user_barcode);
        (new NotificationService())->send([
            "toRoles" => [['roleName' => 'librarian']],
            "templateName" => 'libraryCheckedOutBookRenewal',
            "language" => $this->user->language,
            "vars" => [
                ["userBarcode", $libraryUser->barcode],
                ["firstName", $libraryUser->first],
                ["lastName", $libraryUser->last],
                ["bookBarcode", $params->barcode],
                ["titleSubtitle", $record[0]->title.(
                    isset($record[0]->subtitle) ? ' : '.$record[0]->subtitle : ''
                )]
            ]
        ]);
        
        return new AjaxResponse($record[0]);
    }

    function getCheckedOutBooks($params) {
        $booksByLibraryCard = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'user_barcode',
                'first',
                'last',
                'book_barcode',
                'title',
                'subtitle',
                'due_date'
            ],
            'table' => 'library_checkouts',
            'joins' => [
                'LEFT JOIN library_users ON user_barcode = barcode',
                'LEFT JOIN library_books ON book_barcode = library_books.barcode'
            ],
            'order' => 'user_barcode, due_date'
        ]);

        // Group Books By Library Card
        $booksByLibraryCard = $this->db->groupResults($booksByLibraryCard, 'user_barcode', ['checkedOutBooks', ['book_barcode', 'title', 'subtitle', 'due_date']]);

        function sortByDueDate($a, $b) {
            $aDate = $a->checkedOutBooks[0]->due_date;
            $bDate = $b->checkedOutBooks[0]->due_date;
            return $aDate > $bDate;
        }
        usort($booksByLibraryCard, 'sortByDueDate');

        return new AjaxResponse($booksByLibraryCard);
    }

    function getLibraryUserFromUserId($userId) {
        $user = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'barcode',
                'last',
                'first',
                'credits'
            ],
            'table' => 'library_users',
            'where' => ['userId = ?', $userId]
        ]);

        return isset($user[0]) ? $user[0] : false;
    }

    function getLibraryUserFromBarcode($barcode) {
        $user = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'barcode',
                'last',
                'first',
                'credits'
            ],
            'table' => 'library_users',
            'where' => ['barcode = ?', $barcode]
        ]);

        return isset($user[0]) ? $user[0] : false;
    }
}