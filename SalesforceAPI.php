<?php


class SalesforceAPI {
    private $authUrl = "https://login.salesforce.com";
    private $baseUrl;
    private $username;
    private $password;
    private $clientId;
    private $clientSecret;
    private $debugLimit = -1;

    private $accessToken = null;

    /**
     * SalesforceAPI constructor.
     * @param $username
     * @param $password
     * @param $clientId
     * @param $clientSecret
     */
    public function __construct($username, $password, $clientId, $clientSecret) {
        $this->username     = $username;
        $this->password     = $password;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * authenticate Salesforce user
     */
    public function authenticate() {
        $url = $this->authUrl . "/services/oauth2/token";

        $oauth2TokenArgs = array(
            "username" => $this->username,
            "password" => $this->password,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
            "grant_type" => "password"
        );

        $response = $this->call($url, null, 'POST', http_build_query($oauth2TokenArgs));
        if (property_exists($response, "access_token") && $response->access_token != '') {
            $this->accessToken = $response->access_token;
            $this->baseUrl = $response->instance_url;
        } else {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * @return string
     */
    public function getAccessToken() {
        return $this->accessToken;
    }

    /**
     * Salesfore api call
     * @param $url
     * @param string $oauthtoken
     * @param string $type
     * @param array $arguments
     * @param bool $assoc
     * @return mixed
     */
    public function call($url, $oauthtoken = '', $type = 'GET', $arguments = [], $assoc = false)
    {
        if (strtoupper($type) == 'GET' && !empty($arguments))
        {
            $url .= "?" . $arguments;
        }

        $curl_request = curl_init($url);

        if (strtoupper($type) == 'POST')
        {
            curl_setopt($curl_request, CURLOPT_POST, 1);
        }
        elseif (strtoupper($type) == 'PUT')
        {
            curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        elseif (strtoupper($type) == 'DELETE')
        {
            curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);


        if (!empty($oauthtoken))
        {
            $header = ['Authorization: Bearer ' . $oauthtoken];
            curl_setopt($curl_request, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($arguments) && strtoupper($type) !== 'GET')
        {
            curl_setopt($curl_request, CURLOPT_POSTFIELDS, $arguments);
        }

        $result = curl_exec($curl_request);

        curl_close($curl_request);

        if ($assoc) {
            $decodedResult = json_decode($result, true);
        } else {
            $decodedResult = json_decode($result);
        }
        return $decodedResult;
    }


    function getContacts($accounts) 
    {
        $progress = 0;
        $contacts = [];
        foreach($accounts as $accountId) {
            $progress++;
            $query = "SELECT firstName, lastName, Email FROM Contact WHERE accountId='{$accountId}'";
            $url = $this->baseUrl."/services/data/v20.0/query?q=" . urlencode($query);
            $response = $this->call($url, $this->getAccessToken(), 'GET', [], true);
        
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
                echo "\tdownloaded $progress of ".count($accounts)."\n";
                #break;
            } 
            if(($this->debugLimit > 0) && ($this->debugLimit <= count($contacts))) {
                break;
            }

        }
        return $contacts;
    }


    function getConfigurations(array $accounts) 
    {
        // Get configuration details
        $progress = 0;
        $configs = [];
        foreach($accounts as $accountId) {
            $progress++;
            $query = "SELECT BrokerID__c FROM Configuration__c where Account__c = '$accountId'";
            $url = $this->baseUrl."/services/data/v20.0/query?q=" . urlencode($query);
            $response = $this->call($url, $this->getAccessToken(), 'GET', [], true);

            $bids = [];
            if (is_array($response) && isset($response['records']) && count($response['records']) > 0 ) {
                foreach ($response['records'] as $record) {
                    $bids[] = $record['BrokerID__c'];
                }
            }
            $configs[$accountId] = $bids;

            if($progress % 10 == 0) {
                echo "\tdownloaded $progress of ".count($accounts)."\n";
            } 
            if(($this->debugLimit > 0) && ($this->debugLimit <= count($configs))) {
                break;
            }
        }
        return $configs;
    }    


    function getOpportunities()
    {
        // Get all opportunities
        $query = "SELECT Id, Name, AccountId, Account.Name, stageName, (Select Id, PricebookEntry.Product2.Name From OpportunityLineItems) FROM Opportunity";
        $url = $this->baseUrl."/services/data/v20.0/query?q=" . urlencode($query);
        $response = $this->call($url, $this->getAccessToken(), 'GET', [], true);
        // Extract opportunity details from Salesforce response
        $opportunities = [];
        if (is_array($response) && isset($response['records']) && count($response['records']) > 0 ) {
            foreach ($response['records'] as $record) {
                $opportunity = [];
                $products = [];
                $opportunity['id'] = $record['Id'];
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
        return $opportunities;
    }


}

?>
