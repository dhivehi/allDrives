# gDrive
A very simple google drive client in PHP

## Usage
```php
$gdrive    = new Dhivehi\gDrive();
//source path, dest path - returns FILE_ID on success
$gdrive->copy("/local/path/to/file.db", "/new/path/in/gdrive/to/file.db"); 
//returns directory listing
$gdrive->scandir("FOLDER_ID"); 
//recursive directory creation - returns FOLDER_ID on success
$gdrive->mkdir("/www/my/test"); 
```


## Requirements

### Composer

The preferred method is via [composer](https://getcomposer.org). Follow the
[installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have
composer installed.

Once composer is installed, execute the following command in your project root to install this library:

```sh
composer require google/apiclient:^2.0
```
