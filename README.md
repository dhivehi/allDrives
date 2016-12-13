# gDrive
PHP google drive client

## Usage
```php
$gdrive    = new Dhivehi\gDrive();
$gdrive->copy("/path/to/file.db", "/new/path/to/file.db"); //source path, dest path - returns FILE_ID on success
$gdrive->scandir("FOLDER_ID"); //returns directory listing
$gdrive->mkdir("/www/my/test"); //recursive directory creation - returns FOLDER_ID on success
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
