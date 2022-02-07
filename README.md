# README #

<img src="http://forthebadge.com/images/badges/makes-people-smile.svg" height="20px" />
<a href="https://travis-ci.org/PKeidel/dbtolaravel"><img src="https://travis-ci.org/PKeidel/dbtolaravel.svg" alt="Build Status"></a>

With this package it is possible to auto-generate a lot of needed files if you already have an existing database schema.
Files that can be generated:
* migration
* model
* views to view a single model, edit a single model and list all models
* controller
* route
* seeder with existing data 

## Install

```shell
composer require pkeidel/dbtolaravel
```
DB2Laravel is only active if `APP_DEBUG=true` or `DBTOLARAVEL_ENABLED=true`

### usage
* visit yoururl/dbtolaravel, for example http://127.0.0.1/dbtolaravel
* you can select a configured database connection
* in the table you can create all files of view a diff to compare the file to an existing one

### Filter Tables
Register a filter in your `AppServiceProvider.php`:
```php
DBtoLaravelHelper::$FILTER = function($table) {
    return strpos($table, 'eyewitness_io_') !== 0 && strpos($table, 'oauth_') !== 0;
};
```

### Override type mapping
```php
DBtoLaravelHelper::$MAPPINGS = ['enum' => 'string', 'bytea' => 'binary', 'macaddr' => 'string'];
```