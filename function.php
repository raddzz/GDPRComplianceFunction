<?php
public function getPersonalData()
    {
        $folderName = $_SESSION['user_name'] . '_PersonalData';
        $this->db_connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // change character set to utf8 and check it
        if (!$this
            ->db_connection
            ->set_charset("utf8"))
        {
            $this->errors[] = $this
                ->db_connection->error;
        }

        // if no connection errors (= working database connection)
        if (!$this
            ->db_connection
            ->connect_errno)
        {
            $request_time = time();
            $sql = "INSERT INTO gdpr_requests (user_id, request_time)
                VALUES('" . $_SESSION['user_id'] . "','" . $request_time . "');";
            $query_request_insert = $this
                ->db_connection
                ->query($sql);

            // if request has been added successfully
            if ($query_request_insert)
            {
                if (!file_exists($folderName))
                {
                    mkdir($folderName, 0777, true);

                    // Use the below format for all tables that have personal data in.
                    if ($file_approvals = $this->returnRowsGDPR('file_approval_log'))
                    {
                        $this->createCSV('file_approval_log', $folderName, $file_approvals);
                    }
                    if ($file_comments = $this->returnRowsGDPR('file_comments', 'comment_author'))
                    {
                        $this->createCSV('file_comments', $folderName, $file_comments);
                    }
                    if ($file_deletion_log = $this->returnRowsGDPR('file_deletion_log'))
                    {
                        $this->createCSV('file_deletion_log', $folderName, $file_deletion_log);
                    }
                    if ($file_downloads_log = $this->returnRowsGDPR('file_downloads_log'))
                    {
                        $this->createCSV('file_downloads_log', $folderName, $file_downloads_log);
                    }
                    if ($file_posts = $this->returnRowsGDPR('file_posts', 'file_author'))
                    {
                        $this->createCSV('file_posts', $folderName, $file_posts);
                    }
                    if ($user_link_discord = $this->returnRowsGDPR('user_link_discord'))
                    {
                        $this->createCSV('user_link_discord', $folderName, $user_link_discord);
                    }
                    if ($users = $this->returnRowsGDPR('users'))
                    {
                        $this->createCSV('users', $folderName, $users, "user_password_hash,user_level,user_status");
                    }
                    $this->createZIP($folderName, $folderName . '.zip');
                    $this->deleteFiles($folderName);

                }
            }
        }
    }

    private function createZIP($dir, $zip_file)
    {

        // Get real path for our folder
        $rootPath = realpath($dir);

        // Initialize archive object
        $zip = new ZipArchive();
        $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath) , RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($zip_file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($zip_file);

    }
    private function deleteFiles($target)
    {
        if (is_dir($target))
        {
            $files = glob($target . '*', GLOB_MARK); //GLOB_MARK adds a slash to directories returned
            foreach ($files as $file)
            {
                $this->deleteFiles($file);
            }

            rmdir($target);
        }
        elseif (is_file($target))
        {
            unlink($target);
        }
    }
    private function createCSV($name, $folderName, $rows, $removed_cols = False)
    {
        $filename = $name . '.csv';
        $fp = fopen($folderName . '/' . $filename, 'a');

        foreach ($rows as $row)
        {
            if ($removed_cols)
            {
                $removed_cols = explode(",", $removed_cols);
                foreach ($removed_cols as $col)
                {
                    unset($row->{$col});
                }
            }

            fputcsv($fp, get_object_vars($row));
        }
        rewind($fp);
        fputcsv($fp, array_keys(get_object_vars($rows[0])));
        fclose($fp);
        return $filename;
    }
    private function returnRowsGDPR($table, $user_col = 'user_id')
    {
        $rows = array();
        $this->db_connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // change character set to utf8 and check it
        if (!$this
            ->db_connection
            ->set_charset("utf8"))
        {
            $this->errors[] = $this
                ->db_connection->error;
        }

        // if no connection errors (= working database connection)
        if (!$this
            ->db_connection
            ->connect_errno)
        {

            $sql = "SELECT *
                FROM " . $table . " WHERE " . $user_col . " = '" . $_SESSION['user_id'] . "';";
            $result_of_lookup = $this
                ->db_connection
                ->query($sql);

            if ($result_of_lookup->num_rows > 0)
            {

                while ($obj = mysqli_fetch_object($result_of_lookup))
                {
                    $rows[] = $obj;
                }
                return $rows;
            }
            else
            {
                $this->errors[] = "Error";
                return False;
            }
        }
    }