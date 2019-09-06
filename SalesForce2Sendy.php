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
/*
$lists = array( 
    "ficka0htKNqsSn892U10Fh0g" => "Webinars & Education"
);
*/



// Zapier webhoiok
$ZAPIERURL = 'https://hooks.zapier.com/hooks/catch/2964702/o35se69/'; 

// Sendy details
$APIKEY = 'eYl2oczos9u9vvdW2s5T';
$URL = 'https://sendy.autochartist.com';
$DEFAULTLISTID = '892ihGh1ynxfV0SXPIR5R7Dg';

// SalesForce details
$baseUrl = 'https://autochartist.my.salesforce.com';
$username = 'ilan@autochartist.com';
$password = 'n1c0l34zb3l';
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
    }

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
}


// merges contacts, opportunities and configs into objects we can send to sendy
function mergeResults($contacts, $opportunities, $configs)
{
    // Merge results in desired format
    $results = [];
    
    foreach ($contacts as $accountid => $accountContacts) 
    {

        $brokerids = [];

        if(isset($configs[$accountid])) {

            $accountConfigs = $configs[$accountid];
            // extract the brokerids from the accountConfigs object into an array
            foreach($accountConfigs as $config) 
            {
                $brokerids[] = $config['BrokerID__c'];
            }
        }
        
        $products = [];
        $stage = null;
        $accountname = null;
        if(isset($opportunities[$accountid])) 
        {
            $accountOpportunities = $opportunities[$accountid];

            // extract the product names, account names, opportunity names, stage from the accountOpportunities object into an array
            foreach($accountOpportunities as $opportunity) 
            {
                $accountname = $opportunity['Account']['Name'];
                $stage = $opportunity['StageName'];

                if(isset($opportunity['OpportunityLineItems']))
                {
                    foreach($opportunity['OpportunityLineItems']['records'] as $r) 
                    {
                        $products[] = $r['PricebookEntry']['Product2']['Name'];
                    }
                }
            }

        }

        // add all the brokerids and products accountContacts
        foreach($accountContacts as $contact) {
            $contact['stage'] = $stage;
            $contact['accountname'] = $accountname;
            $contact['products'] = $products;
            $contact['brokerids'] = $brokerids;
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
        if($i == $n)
            break;
        $i++;
    }
}

function sendBouncedToZapier($url, $postdata)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => 0,
      CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 5,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($postdata),
      CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json",
      ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
      return "cURL Error #:" . $err;
    }

    return $response;
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
    filterOpportunities($opportunities);
    echo("downloaded ".count($opportunities)." opportunities\n");

    // get configurations
    echo("fetching configurations\n");
    $configs = $salesforceAPI->getConfigurations();
    echo("downloaded ".count($configs)." configurations\n");

    // Get contact details
    echo("fetching contacts\n");
    $contacts = $salesforceAPI->getContacts();
    echo("downloaded contacts in ".count($contacts)." accounts\n");

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

    // filter for bounced contacts
    $items = [];
    foreach($res['bounced'] as $contact)
    {
        $items[] = $contact['FirstName'].','.$contact['LastName'].','.$contact['Email']. ' (bounced)';
    }
    foreach($res['errors'] as $contact)
    {
        $items[] = $contact['FirstName'].','.$contact['LastName'].','.$contact['Email']. ' (error)';
    }

    // send errors to zapier so we can do something with them
    echo("sending errors and bounces to zapier\n");
    $res = sendBouncedToZapier($ZAPIERURL, array_unique($items));

    echo "SUCCESS";

} catch(Exception $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    fwrite(STDERR, "ERROR");
    echo $e->getMessage()."\n";
    echo "ERROR";
}

?>
