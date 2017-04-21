# allDrives
A very simple drive client in PHP

## Usage
```php
$gdrive    = new Dhivehi\gDrive();
//oneDrive
//$odrive    = new Dhivehi\oDrive(); 
//source path, dest path - returns FILE_ID on success
$gdrive->copy("/local/path/to/file.db", "/new/path/in/gdrive/to/file.db"); 
//returns directory listing
$gdrive->scandir("/path/to/folder/"); 
//recursive directory creation - returns FOLDER_ID on success
$gdrive->mkdir("/www/my/test"); 
```
