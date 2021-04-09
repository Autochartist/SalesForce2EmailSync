<?php

require_once(dirname(__FILE__).'/SalesforceAPI.php');

// SalesForce details
$baseUrl = 'https://autochartist.my.salesforce.com';
$username = 'ilan@autochartist.com';
$password = 'Svp3rm4n!';
$consumerKey = '3MVG98_Psg5cppyY2W_omRywK7DHkgXNVxaBioZzuXYk562.R0WUQwNKpBjy9IUD6nnRtCKquzh9vD3FAzIOm';
$consumerSecret = '386A9BFFC7F2DDCB56D6F8F9E9BFAE6AC2D2147C3DB9858868323CE46DB588DA';

print("authenticating to salesforce\n");
$salesforceAPI = new SalesforceAPI($username, $password, $consumerKey, $consumerSecret);
if(!$salesforceAPI->authenticate()) {
    print ("Invalid salesforce credentials\n");
    fwrite(STDERR, "Invalid salesforce credentials\n"); 
    print ("ERROR");
    return;
}


#$templates = $salesforceAPI->get("EmailTemplate");
#print_r($templates);

$folders = $salesforceAPI->get("Folder");
print_r($folders);

?>
