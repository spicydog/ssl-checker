<?php

$parameter = array_values($_GET);
$parameter = implode(',', $parameter);

// Default domains if not parameters
$domains = ['apple.com','microsoft.com','google.com'];

if($parameter) {
	// Load domain list from GET if exist
	$parameter = str_replace(' ', '', $parameter);
	$domains = explode(",", $parameter);
}

// Clean the input
$domains = array_filter($domains, function($domain) {
	return $domain !== '';
});

$domains = array_filter($domains, function($domain) {
	return strpos($domain, '.') > 0;
});

$domains = array_unique($domains);

// Get maximum of 5 domain per request
$domains = array_slice($domains, 0, 5);

// Get certificate info
// This code is adopted from http://stackoverflow.com/a/29779341/967802
function getCertificate($domain) {
	$url = "https://$domain";
	$orignal_parse = parse_url($url, PHP_URL_HOST);
	$get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE)));
	$read = stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
	$cert = stream_context_get_params($read);
	$certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
	return $certinfo;
}

// Process ceriticate info of each domain
$certs = [];
foreach ($domains as $domain) {
	$rawCert = getCertificate($domain);
	$cert = [];
	$cert['domain'] = $domain;
	$cert['serialNumber'] = $rawCert['serialNumber'];
	$cert['validFrom'] = gmdate("Y-m-d\TH:i:s\Z", $rawCert['validFrom_time_t']);
	$cert['validTo'] = gmdate("Y-m-d\TH:i:s\Z", $rawCert['validTo_time_t']);
	$cert['validToUnix'] = $rawCert['validTo_time_t'];
	$cert['issuer'] = $rawCert['issuer']['CN'];
	$cert['days'] = (intval($cert['validToUnix']) - time())/60/60/24;
	$certs[] = $cert;
}

// Sort by expiring time
$validTo = array();
foreach ($certs as $key => $row) {
    $validTo[$key] = $row['validToUnix'];
}
array_multisort($validTo, SORT_ASC, $certs);

// Generate output
$bar = str_repeat('=', 80);
$format = "$bar\n %s (%d days)\n$bar\n from: %s\n until: %s\n serial: %s\n issuer: %s\n$bar\n\n";

$output = '';
foreach ($certs as $cert) {
	$output .= sprintf($format, $cert['domain'], $cert['days'], $cert['validFrom'], $cert['validTo'], $cert['serialNumber'], $cert['issuer']);
}
printf("<pre>\n%s\n</pre>", $output);
