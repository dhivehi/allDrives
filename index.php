<?php
include('./vendor/autoload.php');
include('./src/gDrive.php');

$gdrive    = new Dhivehi\gDrive();

//$gdrive->copy("/path/to/file.db", "/new/path/to/file.db"); //source path, dest path
//$gdrive->scandir("FOLDER_ID"); //returns directory listing
//$gdrive->mkdir("/www/my/test"); //recursive directory creation - returns FOLDER_ID on success
