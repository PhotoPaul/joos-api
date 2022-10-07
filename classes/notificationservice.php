<?

class NotificationService {
    function __construct(){
        global $session;
        $this->db = $session->db;
        $this->auth = $session->authenticationService;
    }

    function send ($notificationOptions) {
        $notificationOptions = (object) $notificationOptions;

        // Get Notification Template
        if(isset($notificationOptions->templateName)) {
            $notification = $this->db->sql1([
                'statement' => 'SELECT',
                'columns' => 'subject_en, subject_gr, body_en, body_gr, path',
                'table' => 'admin_notification_templates',
                'where' => ['name = ?', $notificationOptions->templateName]
            ]);

            $notificationOptions->subject = $notification->{'subject_'.$notificationOptions->language};
            $notificationOptions->message = $notification->{'body_'.$notificationOptions->language};
        } else {
            $notificationOptions->subject = isset($notificationOptions->subject) ? $notificationOptions->subject : 'No subject defined';
            $notificationOptions->message = isset($notificationOptions->message) ? $notificationOptions->message : 'No message defined';
        }

        if (!isset($notificationOptions->type)){ // Type is not set so email is assumed
            if(!isset($notificationOptions->forUserIds)) {
                $notificationOptions->forUserIds = [];
            } elseif (!is_array($notificationOptions->forUserIds)) {
                $notificationOptions->forUserIds = [$notificationOptions->forUserIds];
            }
            $notificationOptions->email = [];

            // Emails passed as recipients
            if (isset($notificationOptions->toEmails)) {
                if (!is_array($notificationOptions->toEmails)) {
                    $notificationOptions->toEmails = [$notificationOptions->toEmails];
                }
                foreach($notificationOptions->toEmails as $email) {
                    array_push($notificationOptions->email, $email);
                }
            }

            // Roles passed as recipients
            if (isset($notificationOptions->toRoles)) {
                if (!is_array($notificationOptions->toRoles)) {
                    $notificationOptions->toRoles = [$notificationOptions->toRoles];
                }
                foreach($notificationOptions->toRoles as $role) {
                    $return = $this->db->sql([
                        'statement' => 'SELECT',
                        'columns' => 'admin_users.id, email',
                        'table' => 'admin_user_roles',
                        'joins' => [
                            'LEFT JOIN admin_roles ON admin_user_roles.roleId = admin_roles.id',
                            'LEFT JOIN admin_users ON admin_user_roles.userId = admin_users.id'
                        ],
                        'where' => [
                            'admin_roles.name = ? AND (admin_user_roles.forProgramId = ? OR admin_user_roles.forProgramId IS NULL)',
                            [
                                $role['roleName'],
                                isset($role['forProgramId']) ?
                                    $role['forProgramId'] :
                                    0
                            ]
                        ]
                    ]);
                    foreach($return as $user) {
                        array_push($notificationOptions->forUserIds, $user->id);
                        array_push($notificationOptions->email, $user->email);
                    }
                }    
            }

            // Deduplicate email addresses
            $notificationOptions->email = array_unique($notificationOptions->email);
            (new EmailNotification($notificationOptions))->send();
        }

        // System Notification
        if (isset($notificationOptions->forUserIds)) {
            if (!is_array($notificationOptions->forUserIds)) {
                $notificationOptions->forUserIds = [$notificationOptions->forUserIds];
            }
            // Prepare path
            if (isset($notificationOptions->vars)) {
                for($i = 0; $i < count($notificationOptions->vars); $i++){
                    $notification->path = str_replace('{{'.$notificationOptions->vars[$i][0].'}}', $notificationOptions->vars[$i][1], $notification->path);
                }
            }
            // Send Notifications
            foreach ($notificationOptions->forUserIds as $forUserId) {
                $this->db->sql([
                    'statement' => 'INSERT INTO',
                    'table' => 'admin_notifications',
                    'columns' => [
                        'fromUserId',
                        'forUserId',
                        'body_gr',
                        'body_en',
                        'path'
                    ],
                    'values' => [
                        !isset($this->auth->user->id) ? "0" : ($forUserId === $this->auth->user->id ? "0" : $this->auth->user->id),
                        $forUserId,
                        $notification->subject_gr,
                        $notification->subject_en,
                        $notification->path
                    ]
                ]);
            }
        }
    }

    function getNotificationsLength() {
        $return = $this->db->sql1([
            'statement' => 'SELECT',
            'columns' => 'COUNT(id) notificationsLength',
            'table' => 'admin_notifications',
            'where' => ['forUserId = ? AND hidden = FALSE AND `read` = FALSE', $this->auth->user->id]
        ]);

        return new AjaxResponse($return);
    }

    function getNotifications() {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => [
                'admin_notifications.id',
                'fromUserId',
                'firstName',
                'lastName',
                'photoURI',
                'forUserId',
                'body_gr',
                'body_en',
                'path',
                '`read`',
                'dateTime'
            ],
            'table' => 'admin_notifications',
            'joins' => [
                'LEFT JOIN admin_users ON admin_users.id = admin_notifications.fromUserId',
                'LEFT JOIN admin_user_profiles ON admin_user_profiles.id = admin_notifications.fromUserId'
            ],
            'where' => ['forUserId = ? AND hidden = FALSE', $this->auth->user->id],
            'order' => 'dateTime DESC'
        ]);
        $return = JSFDates($return, 'dateTime');

        return new AjaxResponse($return);
    }

    function markNotificationAsRead($params) {
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_notifications',
            'columns' => '`read`',
            'values' => [TRUE],
            'where' => ['id = ?', $params->id]
        ]);

        return new AjaxResponse($return);
    }

    function clearNotification($params) {
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_notifications',
            'columns' => 'hidden',
            'values' => [TRUE],
            'where' => ['id = ?', $params->id]
        ]);

        return new AjaxResponse($return);
    }

    function clearAllNotifications() {
        $return = $this->db->sql([
            'statement' => 'UPDATE',
            'table' => 'admin_notifications',
            'columns' => 'hidden',
            'values' => [TRUE],
            'where' => ['forUserId = ?', $this->auth->user->id]
        ]);

        return new AjaxResponse($return);
    }

    function getNotificationTemplates() {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'name, subject_en, subject_gr',
            'table' => 'admin_notification_templates'
        ]);

        return new AjaxResponse($return);
    }

    function getNotificationTemplate($params) {
        $return = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'name, subject_en, subject_gr, body_en, body_gr',
            'table' => 'admin_notification_templates',
            'where' => ['name = ?', $params->name]
        ]);

        return new AjaxResponse(isset($return[0]) ? $return[0] : false);
    }
}

class EmailNotification {
    function __construct($notificationOptions){
        $this->mailer = new Mailer();
        $this->email = $notificationOptions->email;
        $this->subject = $notificationOptions->subject;
        $this->message = $notificationOptions->message;

        $vars = isset($notificationOptions->vars) ? $notificationOptions->vars : [];
        // Make $appUrl available for deep linking
        global $appUrl; array_push($vars, ['appUrl', $appUrl]);
        for($i = 0; $i < count($vars); $i++){
            $this->message = str_replace('{{'.$vars[$i][0].'}}', $vars[$i][1], $this->message);
        }
    }

    function send() {
        if (MAIL_SERVER_USERNAME !== '***MAIL_SERVER_USERNAME***') {
            $this->mailer->gmail(
                $this->email,
                $this->subject,
                $this->message
            );
        }
    }
}