<?php

define('__ROOT__', dirname(__FILE__));
require_once(__ROOT__.'/SendyAPI.php');
require_once(__ROOT__.'/SalesforceAPI.php');

// sendy details
$lists = array( 
    "892ihGh1ynxfV0SXPIR5R7Dg" => "Company News", 
    "guw1GdZ38l13mv5qx3hQ892g" => "Monthly value-added", 
    "ficka0htKNqsSn892U10Fh0g" => "Webinars & Education", 
    "XoIgXQqKGKyjYFhb2EUWyQ" => "Product Updates"
);
$APIKEY = 'eYl2oczos9u9vvdW2s5T';
$URL = 'https://sendy.autochartist.com/sendy/';
$DEFAULTLISTID = '892ihGh1ynxfV0SXPIR5R7Dg';

// SalesForce details
$baseUrl = 'https://autochartist.my.salesforce.com';
$username = 'ilan@autochartist.com';
$password = 'svp3rm4n';
$consumerKey = '3MVG98_Psg5cppyY2W_omRywK7DHkgXNVxaBioZzuXYk562.R0WUQwNKpBjy9IUD6nnRtCKquzh9vD3FAzIOm';
$consumerSecret = '386A9BFFC7F2DDCB56D6F8F9E9BFAE6AC2D2147C3DB9858868323CE46DB588DA';



// merges contacts, opportunities and configs into objects we can send to sendy
function mergeResults($contacts, $opportunities, $configs)
{
    // Merge results in desired format
    $results = [];

    foreach ($contacts as $key => $contact) {
        $result = [];
        $result['firstname'] = $contact['firstname'];
        $result['lastname'] = $contact['lastname'];
        $result['email'] = $contact['email'];
        $result['stage'] = null;
        $result['products'] = [];
        foreach($opportunities as $opportunity) {
            if($contact['accountId'] == $opportunity['accountId']) {
                $result['accountname'] = $opportunity['accountName'];
                $result['opportunityName'] = $opportunity['name'];
                if(($result['stage'] == null) || (!($result['stage'] == 'Won') || ($result['stage'] == 'Delivered'))) {
                    $result['stage'] = $opportunity['stage'];
                }
                $result['products'] = array_merge($result['products'], $opportunity['products']);
            }
        }
        $result['brokerids'] = $configs[$contact['accountId']];

        $results[] = $result;
    }

    return $results;
}

try {

    //Authentificate user
    echo("authenticating to salesforce\n");
    $salesforceAPI = new SalesforceAPI($username, $password, $consumerKey, $consumerSecret);
    if(!$salesforceAPI->authenticate()) {
        echo ("Invalid salesforce credentials\n");
        fwrite(STDERR, "Invalid salesforce credentials\n"); 
        echo ("ERROR");
        return;
    }

    // fetch opportunities
    echo("fetching opportunities\n");
    $opportunities = $salesforceAPI->getOpportunities();
    echo("downlaoded ".count($opportunities)." opportunities\n");

    // create unique set of accountids for which to fetch contact details
    $accounts = array_unique(array_column($opportunities, 'accountId'));

    // Get contact details
    echo("fetching contacts from ".count($accounts)." accounts\n");
    $contacts = $salesforceAPI->getContacts($accounts);
    echo("downloaded ".count($contacts)." contacts\n");

    // get configurations
    echo("fetching configurations from ".count($accounts)." accounts\n");
    $configs = $salesforceAPI->getConfigurations($accounts);
    echo("downlaoded ".count($configs)." configurations\n");

    // merge results
    $results = mergeResults($contacts, $opportunities, $configs);

    // create sendy object
    $sendy = new \SendyPHP\SendyPHP(
        array(
            'api_key' => $APIKEY,
            'installation_url' => $URL,
            'list_id' => $DEFAULTLISTID
            ));

    // send all details to Sendy
    $res = $sendy->updateSalesForceContacts($results, $lists);
    echo ("updated sendy: errors: ".$res['errors'].", skipped: ". $res['skipped'].", updated: ". $res['updated']."\n");

    echo "SUCCESS";

} catch(Exception $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    fwrite(STDERR, "ERROR");
    echo $e->getMessage()."\n";
    echo "ERROR";
}

?>
