# Webtools Geolocation provider

This is a provider for the [Geocoder](https://github.com/geocoder-php/Geocoder)
PHP library that integrates the Webtools Geolocation service from the European
Commission.

This repository is open source but the service itself is private and exclusive
for websites from the European Commission.

For more information, see the [Webtools Geocoding introduction](https://webgate.ec.europa.eu/fpfis/wikis/pages/viewpage.action?spaceKey=webtools&title=Geocoding).
Note that in order to read this documentation you need to have access to the
internal FPFIS wiki of the European Commission. Please ask you project manager
for more information if you do not have access.

## Installation

```bash
$ composer require openeuropa/webtools-geocoding-provider
```

## Step debugging

To enable step debugging from the command line, pass the `XDEBUG_SESSION` environment variable with any value to
the container:

```bash
docker-compose exec -e XDEBUG_SESSION=1 web <your command>
```

Please note that, starting from XDebug 3, a connection error message will be outputted in the console if the variable is
set but your client is not listening for debugging connections. The error message will cause false negatives for PHPUnit
tests.

To initiate step debugging from the browser, set the correct cookie using a browser extension or a bookmarklet
like the ones generated at https://www.jetbrains.com/phpstorm/marklets/.
