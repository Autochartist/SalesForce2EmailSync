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


    function getEntity($query, $groupBy) 
    {
        $query .= " order by $groupBy";
        $url = $this->baseUrl."/services/data/v20.0/query?q=" . urlencode($query);
        $response = $this->call($url, $this->getAccessToken(), 'GET', [], true);

        $entities = [];
        if (!is_array($response) || (!isset($response['records'])) || (count($response['records']) == 0) ) {
            return $entities;
        }

        $previd = -1;
        foreach ($response['records'] as $record) {
            #var_dump($record);
            $id = $record[$groupBy];
            unset($record['attributes']);            
            if($previd != $id) {
                $entities[$id] = array($record);
            } else {
                $entities[$id][] = $record;
            }
            $previd = $id;
        }

        return $entities;
    }


    function getContacts() 
    {
        $query = "SELECT AccountId, firstName, lastName, Email FROM Contact";
        $groupBy = 'AccountId';
        return $this->getEntity($query, $groupBy);
    }


    function getConfigurations() 
    {
        $query = 'SELECT Account__c, BrokerID__c FROM Configuration__c';
        $groupBy = 'Account__c';
        return $this->getEntity($query, $groupBy);
    }    


    function getOpportunities()
    {
        $query = "SELECT Id, Name, AccountId, Account.Name, StageName, (Select Id, PricebookEntry.Product2.Name From OpportunityLineItems) FROM Opportunity";
        $groupBy = 'AccountId';
        return $this->getEntity($query, $groupBy);
    }

}

?>
