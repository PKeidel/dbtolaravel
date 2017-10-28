# README #

<a href="https://travis-ci.org/PKeidel/dbtolaravel"><img src="https://travis-ci.org/PKeidel/dbtolaravel.svg" alt="Build Status"></a>

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
