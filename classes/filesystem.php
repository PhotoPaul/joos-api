<?
class FileSystem {
    function __construct(){
        global $session;

        $this->db = $session->db;
        $this->hasher = $session->db->hasher;
        $this->user = $session->authenticationService->user;
    }

    function getUniqueFileName() {
        $isFileNameUnique = false;
        while(!$isFileNameUnique) {
            $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->hasher->HashPassword(true));
            $isFileNameUnique = !file_exists('files/'.$filename);
        }
        return $filename;
    }

    function saveFile($params) {
        try {
            // Determine File owner
            $ownerId = isset($params->userId) ? $params->userId : $this->user->id;
            // Determine File name
            $filename = isset($params->filename) ? $params->filename : $this->getUniqueFileName();
            // If fileContents exists, then write or overwrite file and then fetermine File size
            if(isset($params->fileContents)) {
                $filesize = file_put_contents('files/'.$filename, $params->fileContents);
            } else {
                $filesize = @filesize('files/'.$filename);
            }

            // Replaced by the above due to no overwrite
            // // Determine File size and make sure File is properly saved
            // $filesize = @filesize('files/'.$filename);
            // if(!$filesize) $filesize = file_put_contents('files/'.$filename, $params->fileContents);
            
            // Determine Original File name and mime
            $originalFileName = isset($params->originalFileName) ? $params->originalFileName : 'JoOS_Export';
            $originalMimeType = isset($params->originalMimeType) ? $params->originalMimeType : 'application/octet-stream';
            // Add file to Database
            $return = $this->db->sql([
                'statement' => 'INSERT INTO',
                'table' => 'admin_files',
                'columns' => 'filename, owner_id, filesize, original_filename, original_mime_type',
                'values' => [
                    $filename, $ownerId, $filesize, $originalFileName, $originalMimeType
                ],
                'update' => true
            ]);
        } catch(Exception $e) {
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        return $filename;
    }

    function handleFileUpload($params) {
        // Determine File Owner
        $userId = $params->candidateId ? $params->candidateId : $this->user->id;
        // Save Original File Name
        $originalFileName = $_FILES["file"]["name"];
        // Generate Unique File Name
        $_FILES["file"]["name"] = $this->getUniqueFileName();
        // Save Original Mime Type
        $originalMimeType = $_FILES["file"]["type"];
        $_FILES["file"]["type"] = "application/octet-stream";

        ob_start();
        require_once('./classes/uploadhandler.php');
        $upload_handler = new UploadHandler();

        $filename = $this->saveFile((object) [
            "userId" => $userId,
            "originalFileName" => $originalFileName,
            "originalMimeType" => $originalMimeType,
            "filename" => $upload_handler->response["file"][0]->name
        ]);

        ob_end_clean();

        return new AjaxResponse($filename);
    }

    function getFile($params) {
        try {
            // Get the File's original metadata
            $return = $this->db->sql1([
                'statement' => 'SELECT',
                'columns' => 'filename, original_filename, original_mime_type',
                'table' => 'admin_files',
                'where' => ['filename = ?', $params->filename]
            ]);

            // Retrieve the File from the File System
            if($return) {
                // To force download use the following:
                // header('Content-disposition: attachment;filename="'.$return->original_filename.'"');
                header('Content-disposition: inline;filename="'.$return->original_filename.'"');
                header('Content-type: '.$return->original_mime_type);
                echo readfile('files/'.$return->filename);
            } elseif (file_exists('files/'.$params->filename)) {
                header('Content-disposition: inline;filename="JoOS_Orphan_File'.$params->filename.'"');
                header('Content-type: '.mime_content_type('files/'.$params->filename));
                echo readfile('files/'.$params->filename);
            } else {
                return new AjaxResponse(false, false, 'File not found: '.$params->filename);
            }
        } catch(Exception $e) {
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }
    }

    function getImage($params) {
        // Hide all Errors to avoid braking Image processing
        error_reporting(0);
        ini_set('display_errors', 0);

        try {
            if(!file_exists('files/'.$params->filename)) {
                throw(new Exception());
            }
            // Create a new SimpleImage object
            $image = new SimpleImage;
            // Manipulate it
            $image->fromFile('files/'.$params->filename);
            $image->resize(isset($params->width) ? $params->width : null, isset($params->height) ? $params->height : null);
            $image->toScreen();
        } catch(Exception $e) {
            // Handle errors
            $image = imagecreatefrompng("image-file-not-found.png");
            header('Content-Type: image/png');
            imagepng($image);
            imagedestroy($image);
        }
    }

    function deleteFile($params) {
        // If the File exists on the File System
        if(file_exists('files/'.$params->filename)){
            $result = unlink('files/'.$params->filename);
            if(!$result) {
                return new AjaxError(__METHOD__.': File exists but failed to delete');
            }
        }
        // If the File does not exist on the File System (most probably was just deleted)
        try {
            if(!file_exists('files/'.$params->filename)){
                $return = $this->db->sql([
                    'statement' => 'DELETE FROM',
                    'table' => 'admin_files',
                    'where' => ['filename = ?', $params->filename]
                ]);

                // Reset Profile Picture if deleted file was used as a Profile Picture
                $return = $this->db->sql([
                    'statement' => 'UPDATE',
                    'table' => 'admin_user_profiles',
                    'columns' => 'photoURI',
                    'values' => 'NULL',
                    'where' => ['photoURI = ?', $params->filename]
                ]);
            }
        } catch(Exception $e) {
            return new AjaxError(__METHOD__.': '.$e->getMessage());
        }

        return new AjaxResponse($return);
    }

    function getAllFiles($params) {
        // Get a list of all Files from the DB
        $dbFiles = $this->db->sql([
            'statement' => 'SELECT',
            'columns' => 'owner_id, filename, filesize, original_filename, original_mime_type',
            'table' => 'admin_files',
            'order' => 'filesize DESC'
        ]);
        $dbOnlyFiles = $dbFiles;

        // Check the File List against the File System
        foreach($dbOnlyFiles as $key => $dbFile) {
            if (file_exists('files/'.$dbFile->filename)) {
                unset($dbOnlyFiles[$key]);
            }
        }
        $dbOnlyFiles = array_values($dbOnlyFiles);

        // Get a list of all Files from the File System
        $fsOnlyFiles = array_diff(scandir('files/'), ['.', '..']);

        // Check the File List against the DB
        foreach($fsOnlyFiles as $key => $fsFile) {
            $dbFile = $this->db->sql1([
                'statement' => 'SELECT',
                'columns' => 'true',
                'table' => 'admin_files',
                'where' => ['filename = ?', $fsFile]
            ]);
    
            if ($dbFile) {
                unset($fsOnlyFiles[$key]);
            }
        }
        $fsOnlyFiles = array_values($fsOnlyFiles);

        return new AjaxResponse([
            "dbFiles" => $dbFiles,
            "dbOnlyFiles" => $dbOnlyFiles,
            "fsOnlyFiles" => $fsOnlyFiles
        ]);
    }
}
