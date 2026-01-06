<?php
// Script to fix TestHarnessController.php

$file_path = "./protected/controllers/TestHarnessController.php";
$content = file_get_contents($file_path);

// Replace the die() statement with graceful error handling
$old_code = <<<'OLDCODE'
    private function getFileList($dir)
    {
        // array to hold return value
        $retval = array();

        // add trailing slash if missing
        if (substr($dir, -1) != '/') {
            $dir .= '/';
        }

        // open pointer to directory and read list of files
        $d = @dir($dir) or die("getFileList: Failed opening directory $dir for reading");
        while (false !== ($entry = $d->read())) {
            $info = pathinfo($entry);
            $ext = $info['extension'];
            // skip hidden files
            if ($entry[0] == '.') {
                continue;
            }
            if (is_dir("$dir$entry")) {
                $retval[] = array(
                    'name' => "$entry",
                    'fullpath' => "$dir/$entry",
                    'type' => filetype("$dir$entry"),
                    'size' => 0,
                    'lastmod' => filemtime("$dir$entry"),
                    'ext' => $ext,
                );
            } elseif (is_readable("$dir$entry")) {
                $retval[] = array(
                    'name' => "$entry",
                    'fullpath' => "$dir/$entry",
                    'type' => mime_content_type("$dir$entry"),
                    'size' => filesize("$dir$entry"),
                    'lastmod' => filemtime("$dir$entry"),
                    'ext' => $ext,
                );
            }
        }
        $d->close();

        return $this->aasort($retval, 'name');
    }
OLDCODE;

$new_code = <<<'NEWCODE'
    private function getFileList($dir)
    {
        // array to hold return value
        $retval = array();

        // add trailing slash if missing
        if (substr($dir, -1) != '/') {
            $dir .= '/';
        }

        // check if directory exists and is readable
        if (!is_dir($dir) || !is_readable($dir)) {
            return $retval; // return empty array if directory doesn't exist or is not readable
        }

        // open pointer to directory and read list of files
        $d = @dir($dir);
        if ($d === false) {
            return $retval; // return empty array if unable to open directory
        }
        
        while (false !== ($entry = $d->read())) {
            $info = pathinfo($entry);
            $ext = $info['extension'];
            // skip hidden files
            if ($entry[0] == '.') {
                continue;
            }
            if (is_dir("$dir$entry")) {
                $retval[] = array(
                    'name' => "$entry",
                    'fullpath' => "$dir/$entry",
                    'type' => filetype("$dir$entry"),
                    'size' => 0,
                    'lastmod' => filemtime("$dir$entry"),
                    'ext' => $ext,
                );
            } elseif (is_readable("$dir$entry")) {
                $retval[] = array(
                    'name' => "$entry",
                    'fullpath' => "$dir/$entry",
                    'type' => mime_content_type("$dir$entry"),
                    'size' => filesize("$dir$entry"),
                    'lastmod' => filemtime("$dir$entry"),
                    'ext' => $ext,
                );
            }
        }
        $d->close();

        return $this->aasort($retval, 'name');
    }
NEWCODE;

$new_content = str_replace($old_code, $new_code, $content);

if ($new_content === $content) {
    echo "ERROR: No changes made\n";
    exit(1);
} else {
    if (file_put_contents($file_path, $new_content) === false) {
        echo "ERROR: Failed to write file\n";
        exit(1);
    } else {
        echo "SUCCESS: File updated\n";
        exit(0);
    }
}
