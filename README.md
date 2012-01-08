PHP-Geo
===
The PHP-Geo library contains one static class which acts as a wrapper for the
[PHP geoip](http://php.net/manual/en/book.geoip.php) extension/module. It&#039;s
goal was to provide a more straightforward, naturally understood API for
accessing geo-location data about a remote/IP address.

Worth noting is that if you go into the source itself, the scope of methods may
throw you off. This is due to how the geoip plugin works.

Specifically, if it can&#039;t find an IP address, it throws a notice. This can
be tough to deal with in a development environment where errors are set to high,
and it simply pollutes your log files. To get around this, I used the magic
**__callStatic** method to act as a wrapper for **all** methods that ought to be
publicly accessible.

This method captures notices and politely discards them, to prevent any wonky
flow from entering your application.

### City/Country Lookup Example

``` php

    // dependency
    require_once APP . '/vendors/PHP-Geo/Geo.class.php';
    
    // display city and country
    echo Geo::getCity() . ', ' . Geo::getCountry();
    exit(0);

```

While the remote/IP address used for the lookup is determined automagically by
the wrapper (including determining if the request is being passed through a load
balancer, in which case the *HTTP_X_FORWARDED_FOR* property is used instead), it
can be set for manual lookups.
