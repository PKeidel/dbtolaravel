# README #

<img src="http://forthebadge.com/images/badges/makes-people-smile.svg" height="20px" />
<a href="https://travis-ci.org/PKeidel/dbtolaravel"><img src="https://travis-ci.org/PKeidel/dbtolaravel.svg" alt="Build Status"></a>

## Install
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel?ref=badge_shield)


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


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2FPKeidel%2Fdbtolaravel?ref=badge_large)