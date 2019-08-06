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
$password = 'n1c0l34zb3l';
$consumerKey = '3MVG98_Psg5cppyY2W_omRywK7DHkgXNVxaBioZzuXYk562.R0WUQwNKpBjy9IUD6nnRtCKquzh9vD3FAzIOm';
$consumerSecret = '386A9BFFC7F2DDCB56D6F8F9E9BFAE6AC2D2147C3DB9858868323CE46DB588DA';

// keep opportunities with the highest imprtance rating
function filterOpportunities(&$opportunities)
{
    # set stage name preference order
    foreach ($opportunities as &$account) {
        $minStage = 999;
        foreach ($account as &$opportunity) {
            $sn = $opportunity['StageName'];
            if($sn == 'Delivered') {
                $opportunity['StageOrder'] = 1;
            } else if($sn == 'Won') {
                $opportunity['StageOrder'] = 2;
            } else if($sn == 'Lost') {
                $opportunity['StageOrder'] = 3;
            } else if($sn == 'Cancelled') {
                $opportunity['StageOrder'] = 4;
            } else if($sn == 'Negotiation - T&C') {
                $opportunity['StageOrder'] = 5;
            } else if($sn == 'Negotiation') {
                $opportunity['StageOrder'] = 6;
            } else if($sn == 'Qualify') {
                $opportunity['StageOrder'] = 7;
            } else if($sn == 'Qualify - Delay') {
                $opportunity['StageOrder'] = 8;
            } else if($sn == 'Pre - Qualify') {
                $opportunity['StageOrder'] = 9;
            } else {
                $opportunity['StageOrder'] = -1;
            }

            # save min stage for this account
            if($opportunity['StageOrder'] < $minStage) {
                $minStage = $opportunity['StageOrder'];
            }
        }

        # remove all opportunities with StageOrder < minstage
        $index = 0;
        foreach ($account as $opportunity) {
            $so = $opportunity['StageOrder'];
            if($so > $minStage) {
                #echo("remove $index: ".$opportunity['StageName']."\n");
                unset($account[$index]);
            } else {
                #echo("keep ".$opportunity['StageName']."\n");
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
        #echo $accountid."\n";

        $accountConfigs = [];
        $accountOpportunities = [];
        $brokerids = [];
        $products = [];
        $stage = null;
        $accountname = null;

        if(isset($configs[$accountid])) 
        {
            $accountConfigs = $configs[$accountid];
            #echo "\taccountConfigs found\n";
            // extract the brokerids from the accountConfigs object into an array
            foreach($accountConfigs as $config) 
            {
                $brokerids[] = $config['BrokerID__c'];
                #echo "\tbid ".$config['BrokerID__c']." added\n";
            }
        }

        if(isset($opportunities[$accountid])) 
        {
            $accountOpportunities = $opportunities[$accountid];
            #echo "\taccountOpportunities found\n";
            // extract the product names, account names, opportunity names, stage from the accountOpportunities object into an array
            foreach($accountOpportunities as $opportunity) 
            {
                $accountname = $opportunity['Account']['Name'];
                $stage = $opportunity['StageName'];

                if($opportunity['OpportunityLineItems'] != null) 
                {
                    foreach($opportunity['OpportunityLineItems']['records'] as $r) 
                    {
                        $products[] = $r['PricebookEntry']['Product2']['Name'];
                        #echo "\tadded product: ".$r['PricebookEntry']['Product2']['Name']."\n";
                    }
                }
            }
            #echo "\tstage: $stage, accountname: $accountname\n";
        }
        

        // add all the brokerids and products accountContacts
        foreach($accountContacts as $contact) {
            $contact['stage'] = $stage;
            $contact['accountname'] = $accountname;
            $contact['products'] = $products;
            $contact['brokerids'] = $brokerids;
            //var_dump($contact);
            #echo "\tadd contact: ". $contact['Email']."\n";
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
    echo("downloaded ".count($contacts)." contacts\n");

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
