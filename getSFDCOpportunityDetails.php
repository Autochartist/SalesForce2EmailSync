<?php
define('__ROOT__', dirname(__FILE__));
require_once(__ROOT__.'/SalesforceAPI.php');

function arrayUnique($array,$key)
{
    $temp_array = [];
    foreach ($array as &$v) {
        if (!isset($temp_array[$v[$key]]))
            $temp_array[$v[$key]] =& $v;
    }
    $array = array_values($temp_array);
    return $array;

}

// Steps to setup Connected App
// 1. Create new SalesForce Connected App: https://developer.salesforce.com/docs/atlas.en-us.220.0.chatterapi.meta/chatterapi/CR_quickstart_oauth.htm
// 2. For that app, set IP Relaxation	Relax IP restrictions

// Modify details below
$baseUrl = 'https://autochartist.my.salesforce.com';
$username = 'ilan@autochartist.com';
$password = 'svp3rm4n';
$consumerKey = '3MVG98_Psg5cppyY2W_omRywK7DHkgXNVxaBioZzuXYk562.R0WUQwNKpBjy9IUD6nnRtCKquzh9vD3FAzIOm';
$consumerSecret = '386A9BFFC7F2DDCB56D6F8F9E9BFAE6AC2D2147C3DB9858868323CE46DB588DA';

//Authentificate user
echo("authenticating\n");
$salesforceAPI = new SalesforceAPI("https://login.salesforce.com", $username, $password, $consumerKey, $consumerSecret);
if(!$salesforceAPI->authenticate()) {
    echo ("invalid credentials\n");
    return;
}
echo("authenticated\n");

// Get all opportunities with sales stage "Closed Won"
echo("fetching opportunities\n");
$query = "SELECT Id, Name, AccountId, Account.Name, stageName, (Select Id, PricebookEntry.Product2.Name From OpportunityLineItems) FROM Opportunity";
$url = "$baseUrl/services/data/v20.0/query?q=" . urlencode($query);
$response = $salesforceAPI->call($url, $salesforceAPI->getAccessToken(), 'GET', [], true);

// Extract opportunity details from Salesforce response
$opportunities = [];
if (is_array($response) && isset($response['records']) && count($response['records']) > 0 ) {
    foreach ($response['records'] as $record) {
        $opportunity = [];
        $products = [];
        $opportunity['id'] = $record['id'];
        $opportunity["accountId"] = $record['AccountId'];
        $opportunity["name"] = $record['Name'];
        $opportunity["stage"] = $record['StageName'];

        if (isset($record['Account']) && is_array($record['Account']) && isset($record['Account']['Name'])) {
            $opportunity["accountName"] = $record['Account']['Name'];
        }

        if (isset($record['OpportunityLineItems']) && is_array($record['OpportunityLineItems']) && count($record['OpportunityLineItems']) > 0 &&
            isset($record['OpportunityLineItems']['records']) && is_array($record['OpportunityLineItems']['records']) && count($record['OpportunityLineItems']['records']) > 0
        ) {
            foreach ($record['OpportunityLineItems']['records'] as $lineItem) {
                if (isset($lineItem['PricebookEntry']) && isset($lineItem['PricebookEntry']['Product2']) && isset($lineItem['PricebookEntry']['Product2']['Name']) ) {
                    $products[] = $lineItem['PricebookEntry']['Product2']['Name'];
                }
            }
        }
        $opportunity['products'] = $products;
        $opportunities[] = $opportunity;
    }
}
echo("downlaoded ".count($opportunities)." opportunities\n");

// create unique set of accountids for which to fetch contact details
$uniqueAccounts = arrayUnique($opportunities,'accountId');


// Get configuration details
echo("fetching configurations from ".count($uniqueAccounts)." accounts\n");
$progress = 0;
$configs = [];
foreach($uniqueAccounts as $key => $opportunity) {
    $accountId = $opportunity['accountId'];
    $progress++;
    $query = "SELECT BrokerID__c FROM Configuration__c where Account__c = '$accountId'";
    $url = "$baseUrl/services/data/v20.0/query?q=" . urlencode($query);
    $response = $salesforceAPI->call($url, $salesforceAPI->getAccessToken(), 'GET', [], true);

    $bids = [];
    if (is_array($response) && isset($response['records']) && count($response['records']) > 0 ) {
        foreach ($response['records'] as $record) {
            $bids[] = $record['BrokerID__c'];
        }
    }
    $configs[$accountId] = $bids;

    if($progress % 10 == 0) {
        echo "\tdownloaded $progress of ".count($uniqueAccounts)."\n";
        #break;
    } 
}
#var_dump($configs);
#return;
echo("downlaoded ".count($configs)." configurations\n");


// Get contact details
echo("fetching contacts from ".count($uniqueAccounts)." accounts\n");
$progress = 0;
$contacts = [];
foreach($uniqueAccounts as $key => $opportunity) {
    $accountId = $opportunity['accountId'];
    $progress++;
    $query = "SELECT firstName, lastName, Email FROM Contact WHERE accountId='{$accountId}'";
    $url = "$baseUrl/services/data/v20.0/query?q=" . urlencode($query);
    $response = $salesforceAPI->call($url, $salesforceAPI->getAccessToken(), 'GET', [], true);

    if (is_array($response) && isset($response['records']) && count($response['records']) > 0 ) {
        foreach ($response['records'] as $record) {
            $contact = [];
            $contact['firstname'] = $record['FirstName'];
            $contact['lastname'] = $record['LastName'];
            $contact['email'] = $record['Email'];
            $contact['accountId'] = $accountId;
            $contacts[] = $contact;
        }
    }

    if($progress % 10 == 0) {
        echo "\tdownloaded $progress of ".count($uniqueAccounts)."\n";
        #break;
    } 
}
#echo("\n");
echo("downloaded ".count($contacts)." contacts\n");



// Remove duplicate email addresses
$contacts = arrayUnique($contacts,'email');


// Merge results in desired format
$results = [];

echo("merging contacts, opportunities and brokerids\n");
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

$jsonstring = json_encode($results);
print($jsonstring);



