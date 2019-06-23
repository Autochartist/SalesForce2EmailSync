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

    foreach ($contacts as $accountid => $accountContacts) 
    {
        #print($accountid."\n");

        $accountConfigs = [];
        $accountOpportunities = [];
        if(isset($configs[$accountid])) 
        {
            $accountConfigs = $configs[$accountid];
        }
        if(isset($opportunities[$accountid])) 
        {
            $accountOpportunities = $opportunities[$accountid];
        }
        
        // extract the brokerids from the accountConfigs object into an array
        $brokerids = [];
        if($accountConfigs != null) {
            foreach($accountConfigs as $config) 
            {
                $brokerids[] = $config['BrokerID__c'];
            }
        }
      
        // extract the product names, account names, opportunity names, stage from the accountOpportunities object into an array
        $products = [];
        $stage = null;
        $accountname = null;
        if($accountOpportunities != null) 
        {
            foreach($accountOpportunities as $opportunity) 
            {
                $accountname = $opportunity['Account']['Name'];
                if($stage == null) { 
                    $stage = $opportunity['StageName'];
                } else if(($stage != 'Won') || ($stage != 'Delivered')) {
                    $stage = $opportunity['StageName'];
                }

                if($opportunity['OpportunityLineItems'] != null) 
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
        }
        #print_r($contact);
        $results[] = $contact;
        
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
    echo("downloaded ".count($opportunities)." opportunities\n");
    #print_r_n($opportunities, 5);

    // get configurations
    echo("fetching configurations\n");
    $configs = $salesforceAPI->getConfigurations();
    echo("downloaded ".count($configs)." configurations\n");
    #print_r_n($configs, 5);

    // Get contact details
    echo("fetching contacts\n");
    $contacts = $salesforceAPI->getContacts();
    echo("downloaded ".count($contacts)." contacts\n");
    #print_r_n($contacts, 5);

    // merge results
    $results = mergeResults($contacts, $opportunities, $configs);
    #print_r($results);

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
