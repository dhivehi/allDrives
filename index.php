<?php
include('./vendor/autoload.php');
include('./gDrive.php');

$gdrive    = new Dhivehi\gDrive();

//$gdrive->file_put_contents("/path/to/file.db", "FOLDER_ID"); //less than 5mb
//$gdrive->largeUpload("/path/to/file.db", "FOLDER_ID"); //greater than 5 mb
//$gdrive->scandir("FOLDER_ID"); //returns directory listing
//$gdrive->mkdir("/www/my/test"); //recursive directory creation - returns FOLDER_ID on success
