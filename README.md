Simple GeoBlocking-Bundle
=========================

Symfony2 Bundle that allows you to configure geoblocking access to certain pages of your application.

It adds an kernel event listener that listens for "kernel.request" events and uses the php geoip module to identify the country of origin of the current request and depending on the configuration displays an error-page.


## Requirements
There are no explicit requirements. BUT the default setup makes two assumptions:

##### 1. the php geoip-module is enabled on your server
   
"GeoIpLookupAdapter" uses the [php function geoip_country_code_by_name($address)](http://www.php.net/manual/en/function.geoip-country-code3-by-name.php) 
to find the country of the given address.

To use the default implementation, this function (provided by the php geoip module => http://www.php.net/manual/en/book.geoip.php) must be available.

Alternatively you can implement and use your own GeoLookupAdapter that uses an other way to find the country for the given ip (see below).

##### 2. you use fosuserbundle for authentication/usermanagment

Most often you would like that registered users can access your site from wherever they are. So there should be a option to login and for logged 
in users no pages should be blocked. As a lot of people (including me) use the fosuserbundle for user managment, the default configuration is set 
to work nicely with the default configuration of the fosuserbundle.

You can change this of course in the config.yml.


## Installation
To install AzineGeoBlockingBundle with Composer just add the following to your composer.json file:

```
// composer.json
{
    // ...
    require: {
        // ...
        "azine/geoblocking-bundle": "dev-master"
    }
}
```

Then, you can install the new dependencies by running Composer’s update command from the directory where your composer.json file is located:

```
php composer.phar update
```

Now, Composer will automatically download all required files, and install them for you. All that is left to do is to update your AppKernel.php file, and register the new bundle:

```
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
   	new Azine\GeoBlockingBundle\AzineGeoBlockingBundle(),
    // ...
);
```


## Configuration options
For the bundle to work with the default-settings, no config-options are required. 
The default blocks all anonymouse users unless they are in the same 
private subnet (=> both server & client are inside the same home/company network) or on localhost (=> web-server and client are the same computer, e.g. when debugging locally).

This is the complete list of configuration options with their defaults.
```
// app/config/config.yml
azine_geo_blocking:
    enabled:              			true 								# true|false : turn the whole bundle on/off
    access_denied_view:  AzineGeoBlockingBundle::accessDenied.html.twig # the view to be rendered as "blocked" page
    block_anonymouse_users_only:	true		 						# block all users or only users that are not logged in yet
    login_route:          			fos_user_security_login 			# route name to the login-form (only relevant if block_anonymouse_users_only is set to true)
    lookup_adapter:       			azine_geo_blocking.lookup.adapter	# id of the lookup-adapter you would like to use
    allow_private_ips:    			true								# true | false : also applie the rules to private IPs e.g. 127.0.0.1 or 192.168.xxx.yyy etc.

	# routes to applie the blocking rules to
    # only either whitelist or blacklist can contain values, if you configure both, the blacklist will be ignored.
    routes:
        whitelist:
        	- route_to_allways_allow
            # Defaults: these defaults work nice with the fosuserbundle defaults
            - fos_user_security_login
            - fos_user_security_login_check
            - fos_user_security_logout
        blacklist:            
        	- route_to_allways_block
        	- other_route_to_allways_block

	# countries to applie the blocking rules for
    # only either whitelist or blacklist can contain values, if you configure both, the blacklist will be ignored.
    countries:
        whitelist:  # e.g. "CH","FR","DE" etc. => access is allowed to visitors from these countries
        	- CH
        	- FR
        	- DE
        blacklist:  # e.g. "US","CN" etc. => access is denied to visitors from these countries
        	- US
        	- CN
    
```


## Alternative GeoIpLookupAdapter
You can create your own implementation of [Adapter\GeoIpLookupAdapterInterface.php](Adapter/GeoIpLookupAdapterInterface.php), define it as service in your service.yml or service.xml and set the service-id as lookup_adapter in the config.yml:
```
// app/config/config.yml
azine_geo_blocking:
    enabled:              true 										# true|false : turn the whole bundle on/off
    lookup_adapter:       your.own.implementation.of.lookup.adapter	# id of the lookup-adapter you would like to use
``` 


## Open Issues
It's not really an issue for my usecase, but if you block access for example for US users, this also means, that search-engine-robots cannot crawl your site
to index it.