#!/usr/bin/env php
<?php

// Load necessary functions
require_once __DIR__ . '/functions.php';

// Do not print anything to the client
$quiet = true;
// Some DynDNS clients (e.g. ddclient) do not work if the HTTP/1.1 'Transfer-Encoding: chunked' is used. Set Content-Length to prevent this.
// We specify an invalid length of 100 but this does not matter for most clients.
header("Content-Type: text/plain");
header("Content-Length: 100");

outputStdout("=============================================");
outputStdout(sprintf("Running dynamic DNS client for netcup %s", VERSION));
outputStdout("This script is not affiliated with netcup.");
outputStdout("=============================================\n");

if (! _is_curl_installed()) {
    outputStderr("cURL PHP extension is not installed. Please install the cURL PHP extension, otherwise the script will not work. Exiting.");
    exit(1);
}


// Taken and adjusted from https://www.onderka.com/computer-und-netzwerk/eigener-dyndns-auf-netcup-vserver-mit-api
// Use either the 'myip' GET parameter or the REMOTE_ADDR of the client
if ( filter_input(INPUT_GET, 'myip', FILTER_SANITIZE_SPECIAL_CHARS) ) {
    define("CLIENT_IP", filter_input(INPUT_GET, 'myip', FILTER_SANITIZE_SPECIAL_CHARS));
} else {
    define("CLIENT_IP", $_SERVER['REMOTE_ADDR']);
}

// If basic authentication is enabled, use the login details for authentication
if ( isset($_SERVER['PHP_AUTH_USER']) ) {
    define("AUTH_USER",   $_SERVER['PHP_AUTH_USER']);
    $try_client = filter_input(INPUT_GET, 'hostname', FILTER_SANITIZE_SPECIAL_CHARS);
    if ( $try_client != "") {
        define("CLIENT_NAME", $try_client);
    } else {
        $try_client = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        if ( $try_client != "" ) {
            define("CLIENT_NAME", $try_client);
        } else {
            define("CLIENT_NAME", AUTH_USER);
        }
    }
} else {
    if ( isset($_GET['name']) ) {
        define("AUTH_USER",   filter_input(INPUT_GET, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
        define("CLIENT_NAME", filter_input(INPUT_GET, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
    } else {
        define("AUTH_USER", "");
        define("CLIENT_NAME", "");
    }
}

// If basic authentication is enabled, use the login details for authentication
if ( isset($_SERVER['PHP_AUTH_PW']) ) {
    define("AUTH_PASS", $_SERVER['PHP_AUTH_PW']);
} else {
    if (filter_input(INPUT_GET, 'pass', FILTER_SANITIZE_SPECIAL_CHARS)) {
        define("AUTH_PASS", filter_input(INPUT_GET, 'pass', FILTER_SANITIZE_SPECIAL_CHARS));
    } else {
        define("AUTH_PASS", "");
    }
}

// Response for nothing changed
define("RESP_NOCHG",   'nochg');
// Response for IP address changed
define("RESP_OK",      'good');
// Internal error
define("RESP_OUCH",    '911');
// Auth failed or no permission to edit this hostname
define("RESP_NOAUTH",  'badauth');

if (isIPv4Valid(CLIENT_IP)) {
    $providedIPv4 = CLIENT_IP;
    define('USE_IPV4', true);
} else {
    define('USE_IPV4', false);
}
if (!USE_IPV4 && isIPv6Valid(CLIENT_IP)) {
    $providedIPv6 = CLIENT_IP;
    define('USE_IPV6', true);
} else {
    define('USE_IPV6', false);
}

if ((AUTH_PASS === "") && (AUTH_USER === "")) {
    echo CLIENT_IP."\n";
    exit;
}

if (AUTH_USER === DYNDNS_USERNAME
      && hash('sha512', AUTH_PASS) === DYNDNS_PASSWORD_HASH
      && CLIENT_NAME === DYNDNS_DOMAIN) {
    // OK
} else {
    outputStderr("User authentication failed");
    echo RESP_NOAUTH."\n";
    exit(1);
}

if (!defined('USE_IPV4')) {
    outputWarning("USE_IPV4 not defined in config.php. Assuming that IPv4 should be used to support deprecated legacy configs. Please add USE_IPV4 to your config.php, as in config.dist.php");
    define('USE_IPV4', true);
}

if (USE_IPV4 === false && USE_IPV6 === false) {
    outputStderr("IPv4 as well as IPv6 is disabled in config.php. Please activate either IPv4 or IPv6 in config.php. I do not know what I am supposed to do. Exiting.");
    exit(1);
}

if (USE_IPV4 === true) {
    // Get current IPv4 address
    if (!$publicIPv4 = getCurrentPublicIPv4()) {
        outputStderr("Main API and fallback API didn't return a valid IPv4 address (Try 3 / 3). Exiting.");
        exit(1);
    }
}

if (USE_IPV6 === true) {
    //Get current IPv6 address
    if (!$publicIPv6 = getCurrentPublicIPv6()) {
        outputStderr("Main API and fallback API didn't return a valid IPv6 address (Try 3 / 3). Do you have IPv6 connectivity? If not, please disable USE_IPV6 in config.php. Exiting.");
        exit(1);
    }
}

// Login
if ($apisessionid = login(CUSTOMERNR, APIKEY, APIPASSWORD)) {
    outputStdout("Logged in successfully!");
} else {
    exit(1);
}

// Get list of domains
$domains = getDomains();

// Suppress not set warning, because DynDNS always updates either IPv4 or IPv6.
$ipv4change = false;
$ipv6change = false;

foreach ($domains as $domain => $subdomains) {
    outputStdout(sprintf('Beginning work on domain "%s"', $domain));

    // Let's get infos about the DNS zone
    if ($infoDnsZone = infoDnsZone($domain, CUSTOMERNR, APIKEY, $apisessionid)) {
        outputStdout("Successfully received Domain info.");
    } else {
        exit(1);
    }
    //TTL Warning
    if (CHANGE_TTL !== true && $infoDnsZone['responsedata']['ttl'] > 300) {
        outputStdout("TTL is higher than 300 seconds - this is not optimal for dynamic DNS, since DNS updates will take a long time. Ideally, change TTL to lower value. You may set CHANGE_TTL to True in config.php, in which case TTL will be set to 300 seconds automatically.");
    }

    //If user wants it, then we lower TTL, in case it doesn't have correct value
    if (CHANGE_TTL === true && $infoDnsZone['responsedata']['ttl'] !== "300") {
        $infoDnsZone['responsedata']['ttl'] = 300;

        if (updateDnsZone($domain, CUSTOMERNR, APIKEY, $apisessionid, $infoDnsZone['responsedata'])) {
            outputStdout("Lowered TTL to 300 seconds successfully.");
        } else {
            outputStderr("Failed to set TTL... Continuing.");
        }
    }

    //Let's get the DNS record data.
    if ($infoDnsRecords = infoDnsRecords($domain, CUSTOMERNR, APIKEY, $apisessionid)) {
        outputStdout("Successfully received DNS record data.");
    } else {
        exit(1);
    }

    foreach ($subdomains as $subdomain) {
        outputStdout(sprintf('Updating DNS records for subdomain "%s" of domain "%s".', $subdomain, $domain));

        if (USE_IPV4 === true) {
            //Find the host defined in config.php
            $foundHostsV4 = array();

            foreach ($infoDnsRecords['responsedata']['dnsrecords'] as $record) {
                if ($record['hostname'] === $subdomain && $record['type'] === "A") {
                    $foundHostsV4[] = array(
                        'id' => $record['id'],
                        'hostname' => $record['hostname'],
                        'type' => $record['type'],
                        'priority' => $record['priority'],
                        'destination' => $record['destination'],
                        'deleterecord' => $record['deleterecord'],
                        'state' => $record['state'],
                    );
                }
            }

            //If we can't find the host, create it.
            if (count($foundHostsV4) === 0) {
                outputStdout(sprintf("A record for host %s doesn't exist, creating necessary DNS record.", $subdomain));
                $foundHostsV4[] = array(
                    'hostname' => $subdomain,
                    'type' => 'A',
                    'destination' => 'newly created Record',
                );
            }

            //If the host with A record exists more than one time...
            if (count($foundHostsV4) > 1) {
                outputStderr(sprintf("Found multiple A records for the host %s – Please specify a host for which only a single A record exists in config.php. Exiting.", $subdomain));
                exit(1);
            }

            $ipv4change = false;

            //Has the IP changed?
            foreach ($foundHostsV4 as $record) {
                if ($record['destination'] !== $publicIPv4) {
                    //Yes, it has changed.
                    $ipv4change = true;

                    outputStdout(sprintf("IPv4 address has changed. Before: %s; Now: %s", $record['destination'], $publicIPv4));
                } else {
                    //No, it hasn't changed.
                    outputStdout("IPv4 address hasn't changed. Current IPv4 address: ".$publicIPv4);
                }
            }

            //Yes, it has changed.
            if ($ipv4change === true) {
                $foundHostsV4[0]['destination'] = $publicIPv4;
                //Update the record
                if (updateDnsRecords($domain, CUSTOMERNR, APIKEY, $apisessionid, $foundHostsV4)) {
                    outputStdout("IPv4 address updated successfully!");
                } else {
                    exit(1);
                }
            }
        }

        if (USE_IPV6 === true) {
            //Find the host defined in config.php
            $foundHostsV6 = array();

            foreach ($infoDnsRecords['responsedata']['dnsrecords'] as $record) {
                if ($record['hostname'] === $subdomain && $record['type'] === "AAAA") {
                    $foundHostsV6[] = array(
                        'id' => $record['id'],
                        'hostname' => $record['hostname'],
                        'type' => $record['type'],
                        'priority' => $record['priority'],
                        'destination' => $record['destination'],
                        'deleterecord' => $record['deleterecord'],
                        'state' => $record['state'],
                    );
                }
            }

            //If we can't find the host, create it.
            if (count($foundHostsV6) === 0) {
                outputStdout(sprintf("AAAA record for host %s doesn't exist, creating necessary DNS record.", $subdomain));
                $foundHostsV6[] = array(
                    'hostname' => $subdomain,
                    'type' => 'AAAA',
                    'destination' => 'newly created Record',
                );
            }

            //If the host with AAAA record exists more than one time...
            if (count($foundHostsV6) > 1) {
                outputStderr(sprintf("Found multiple AAAA records for the host %s – Please specify a host for which only a single AAAA record exists in config.php. Exiting.", $subdomain));
                exit(1);
            }

            $ipv6change = false;

            //Has the IP changed?
            foreach ($foundHostsV6 as $record) {
                if ($record['destination'] !== $publicIPv6) {
                    //Yes, it has changed.
                    $ipv6change = true;
                    outputStdout(sprintf("IPv6 address has changed. Before: %s; Now: %s", $record['destination'], $publicIPv6));
                } else {
                    //No, it hasn't changed.
                    outputStdout("IPv6 address hasn't changed. Current IPv6 address: ".$publicIPv6);
                }
            }

            //Yes, it has changed.
            if ($ipv6change === true) {
                $foundHostsV6[0]['destination'] = $publicIPv6;
                //Update the record
                if (updateDnsRecords($domain, CUSTOMERNR, APIKEY, $apisessionid, $foundHostsV6)) {
                    outputStdout("IPv6 address updated successfully!");
                } else {
                    exit(1);
                }
            }
        }
    }
}

//Logout
if (logout(CUSTOMERNR, APIKEY, $apisessionid)) {
    outputStdout("Logged out successfully!");
} else {
    exit(1);
}

if ($ipv4change === true || $ipv6change === true) {
    echo RESP_OK." ".CLIENT_IP."\n";
} else {
    echo RESP_NOCHG." ".CLIENT_IP."\n";
}
