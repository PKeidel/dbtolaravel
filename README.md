# README #

<img src="http://forthebadge.com/images/badges/makes-people-smile.svg" height="20px" />
<a href="https://travis-ci.org/PKeidel/dbtolaravel"><img src="https://travis-ci.org/PKeidel/dbtolaravel.svg" alt="Build Status"></a>

[![Beerpay](https://beerpay.io/PKeidel/dbtolaravel/badge.svg?style=flat)](https://beerpay.io/PKeidel/dbtolaravel)

[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel?ref=badge_shield)

## Install

```shell
composer require pkeidel/dbtolaravel
```
DB2Laravel is just active if `APP_DEBUG=true` or `DBTOLARAVEL_ENABLED=true`

### < Laravel 5.5
As always, add it to your app/config.php:

```php
'providers' => [
    // ....
    PKeidel\DBtoLaravel\Providers\DBtoLaravelServiceProvider::class,
]
```

### \>= Laravel 5.5
```php
// get you a coffee, you're done
```

## Settings
### .env file
DBtoLaravel is enabled if `APP_DEBUG=true` or `DBTOLARAVEL_ENABLED=true` 

### Filter Tables
Register a filter in your `AppServiceProvider.php`:
```php
DBtoLaravelHelper::$FILTER = function($table) {
    return strpos($table, 'eyewitness_io_') !== 0 && strpos($table, 'oauth_') !== 0;
};
```


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel?ref=badge_large)
