<?php

require_once(dirname(__FILE__).'/SendyAPI.php');
require_once(dirname(__FILE__).'/SalesforceAPI.php');

// sendy details
$lists = array(
    "892ihGh1ynxfV0SXPIR5R7Dg" => "Company News", 
    "guw1GdZ38l13mv5qx3hQ892g" => "Monthly value-added", 
    "ficka0htKNqsSn892U10Fh0g" => "Webinars & Education",
    "XoIgXQqKGKyjYFhb2EUWyQ" => "Product Updates"
);

// Sendy details
$APIKEY = 'eYl2oczos9u9vvdW2s5T';
$URL = 'https://sendy.autochartist.com';
$DEFAULTLISTID = '892ihGh1ynxfV0SXPIR5R7Dg';

// SalesForce details
$baseUrl = 'https://autochartist.my.salesforce.com';
$username = 'ilan@autochartist.com';
$password = 'Svp3rm4n!';
$consumerKey = '3MVG98_Psg5cppyY2W_omRywK7DHkgXNVxaBioZzuXYk562.R0WUQwNKpBjy9IUD6nnRtCKquzh9vD3FAzIOm';
$consumerSecret = '386A9BFFC7F2DDCB56D6F8F9E9BFAE6AC2D2147C3DB9858868323CE46DB588DA';

// priority of sales stages
function getStageImportance($sn)
{
    if($sn == 'Delivered') {
        return 1;
    } else if($sn == 'Won') {
        return  2;
    } else if($sn == 'Lost') {
        return  3;
    } else if($sn == 'Cancelled') {
        return  4;
    } else if($sn == 'Negotiation - T&C') {
        return  5;
    } else if($sn == 'Negotiation') {
        return  6;
    } else if($sn == 'Qualify') {
        return  7;
    } else if($sn == 'Qualify - Delay') {
        return  8;
    } else if($sn == 'Pre - Qualify') {
        return  9;
    }
    return  10;
}

// keep opportunities with the highest imprtance rating
function filterOpportunities(&$opportunities)
{

    // get the most important stage per accountid
    $stages = [];
    foreach ($opportunities as &$accountOpp) {
        foreach ($accountOpp as &$opportunity) {
            $accountid = $opportunity['AccountId'];
            $stageName = $opportunity['StageName'];
            $stageImportance = getStageImportance($stageName);
            if(!isset($stages[$accountid])) {
                $stages[$accountid] = $stageName;
            } else {
                $currentStageImportance = getStageImportance($stages[$accountid]);
                if($stageImportance < $currentStageImportance) {
                    $stages[$accountid] = $stageName;
                }                
            }
        }
        unset($opportunity);
    }
    unset($accountOpp);

    // set stage name preference order
    foreach ($opportunities as &$accountOpp) {
        // remove all opportunities with StageOrder > minstage
        $index = 0;
        foreach ($accountOpp as $opportunity) {
            $accountid = $opportunity['AccountId'];
            $stageName = $opportunity['StageName'];
            if($stageName != $stages[$accountid]) {
                unset($accountOpp[$index]);
            }
            $index++;
        }
    }
    unset($accountOpp);

    return $stages;
}


// merges contacts, opportunities and configs into objects we can send to sendy
function mergeResults($contacts, $opportunities, $accountBrokerIds, $accountStages)
{
    // Merge results in desired format
    $results = [];
    
    foreach ($contacts as $accountid => $accountContacts) 
    {
        $products = [];
        if(array_key_exists($accountid, $opportunities)) 
        {
            $accountOpportunities = $opportunities[$accountid];

            // extract the product names, account names, opportunity names from the accountOpportunities object into an array
            foreach($accountOpportunities as $opportunity) 
            {
                if(isset($opportunity['OpportunityLineItems']))
                {
                    foreach($opportunity['OpportunityLineItems']['records'] as $r) 
                    {
                        $products[$r['PricebookEntry']['Product2']['Name']] = $r['PricebookEntry']['Product2']['Name']; // make sure we dont have duplicates
                    }
                }
            }

        }

        // add all the brokerids and products accountContacts
        foreach($accountContacts as $contact) {
            $contact['stage'] = "none";
            if(array_key_exists($accountid, $accountStages)) {
                $contact['stage'] = $accountStages[$accountid];
            }
            $contact['accountname'] = "none";
            if(array_key_exists('Account', $contact)) {
                $contact['accountname'] = $contact['Account']['Name'];
                unset($contact['Account']);
            }
            $contact['products'] = implode(', ', array_keys($products));
            $contact['brokerids'] = "";
            if(array_key_exists($accountid, $accountBrokerIds)) {
                $contact['brokerids'] = $accountBrokerIds[$accountid];
            }
            $results[] = $contact;
        }
        
    }
    return $results;
}

function print_r_n($array, $n) 
{
    $i = 0;
    foreach($array as $r) 
    {
        print_r($r);
        if($i == $n) {
            break;
        }
        $i++;
    }
}

function mergeBrokerIDs($array)
{
    $brokerIds = [];
    foreach($array as $accountid => $configs) {
        if(!is_array($configs))
            continue;
        foreach($configs as $config) {
            if(!array_key_exists($config['Account__c'], $brokerIds)) {
                $brokerIds[$config['Account__c']] = $config['BrokerID__c'];
            } else {
                $brokerIds[$config['Account__c']] = $brokerIds[$config['Account__c']] . ', ' . $config['BrokerID__c'];
            }
        }
    }
    return $brokerIds;
}

try {

    //Authentificate user
    print("authenticating to salesforce\n");
    $salesforceAPI = new SalesforceAPI($username, $password, $consumerKey, $consumerSecret);
    if(!$salesforceAPI->authenticate()) {
        print ("Invalid salesforce credentials\n");
        fwrite(STDERR, "Invalid salesforce credentials\n"); 
        print ("ERROR");
        return;
    }

    // fetch accounts
    #print("fetching accounts\n");
    #$accounts = $salesforceAPI->getAccounts();
    #print("downloaded ".count($accounts)." accounts\n");

    // get configurations
    print("fetching configurations\n");
    $configs = $salesforceAPI->getConfigurations();
    $accountBrokerIds = mergeBrokerIDs($configs);
    print("downloaded ".count($configs)." configurations\n");
    
    // fetch opportunities
    print("fetching opportunities\n");
    $opportunities = $salesforceAPI->getOpportunities();
    $accountStages = filterOpportunities($opportunities);
    print("downloaded ".count($opportunities)." opportunities\n");

    // Get contact details
    print("fetching contacts\n");
    $contacts = $salesforceAPI->getContacts();
    unset($contacts[""]);
    print("downloaded contacts in ".count($contacts)." accounts\n");

    // merge results
    $results = mergeResults($contacts, $opportunities, $accountBrokerIds, $accountStages);

    // create sendy object
    $sendy = new \SendyPHP\SendyPHP(
        array(
            'api_key' => $APIKEY,
            'installation_url' => $URL,
            'list_id' => $DEFAULTLISTID
            ));

    // send all details to Sendy
    $sendy->updateContacts($results, $lists);

    print("re-authenticating to salesforce\n");
    $salesforceAPI = new SalesforceAPI($username, $password, $consumerKey, $consumerSecret);
    if(!$salesforceAPI->authenticate()) {
        print ("Invalid salesforce credentials\n");
        fwrite(STDERR, "Invalid salesforce credentials\n"); 
        print ("ERROR");
        return;
    }
    $salesforceAPI->updateContactSendyStatus($results);

    print "SUCCESS";

} catch(Exception $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    fwrite(STDERR, "ERROR");
    print $e->getMessage()."\n";
    print "ERROR";
}

?>
