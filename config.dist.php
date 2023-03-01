<?php

// Enter your netcup customer number here.
define('CUSTOMERNR', '12345');


// Enter your API-Password and -Key here - you can generate them in your CCP at https://ccp.netcup.net
define('APIPASSWORD', 'abcdefghijklmnopqrstuvwxyz');
define('APIKEY', 'abcdefghijklmnopqrstuvwxyz');


// Define domains and subdomains which should be used for dynamic DNS in the following format:
// domain.tld: host1, host2, host3; domain2.tld: host1, host4, *, @
// Start with the domain (without subdomain), add ':' after the domain, then add as many subdomains as you want, seperated by ','.
// To add another domain, finish with ';'.
// Whitespace (spaces and newlines) are ignored. If you have a very complicated configuration, you may want to use multiple lines. Feel free to do so!
// If one of the subdomains does not exist, the script will create them for you.
// Subdomain configuration: Use '@' for the domain without subdomain. Use '*' for wildcard: All subdomains (except ones already defined in DNS).
define('DOMAINLIST', 'myfirstdomain.com: server, dddns; myseconddomain.com: @, *, some-subdomain');


// The old format for configuring domain + host is still supported, but deprecated. I recommend to switch to the above config,
// as it allows you to define multiple domains + subdomains.

// Enter Domain which should be used for dynamic DNS.
// define('DOMAIN', 'mydomain.com');
// Enter subdomain to be used for dynamic DNS, alternatively '@' for domain root or '*' for wildcard. If the record doesn't exist, the script will create it.
// define('HOST', 'server');

// If set to true, this will change TTL to 300 seconds on every run if necessary.
define('CHANGE_TTL', true);


// Use netcup DNS REST-API.
define('APIURL', 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON');

// DynDNS API configuration options
// The username for client authentication
define('DYNDNS_USERNAME', 'username');

// The password for client authentication. Set the sha512sum hash of the password.
// To generate the hash of the default password 'secret' enter in your shell: echo -n 'secret' | sha512sum
define('DYNDNS_PASSWORD_HASH', 'bd2b1aaf7ef4f09be9f52ce2d8d599674d81aa9d6a4421696dc4d93dd0619d682ce56b4d64a9ef097761ced99e0f67265b5f76085e5b0ee7ca4696b2ad6fe2b2');

// Enter the domain expected from the dyndns client. This domain is not related to 'DOMAINLIST' above and will not be used
// to determine which NetCup domain should be updated. This is only used during authentication and can therefore be an arbitrary value.
define('DYNDNS_DOMAIN', 'example.com');
