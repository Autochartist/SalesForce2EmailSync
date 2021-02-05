<?php


class SalesforceAPI {
    private $authUrl = "https://login.salesforce.com";
    private $baseUrl;
    private $username;
    private $password;
    private $clientId;
    private $clientSecret;

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

        $response = $this->call($url, http_build_query($oauth2TokenArgs), null, 'POST');
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
    public function call($url, $payload, $oauthtoken = '', $type = 'GET', $assoc = false)
    {
        if (strtoupper($type) == 'GET' && !empty($payload))
        {
            $url .= "?" . $payload;
        }

        $curl_request = curl_init($url);

        if (strtoupper($type) == 'POST')
        {
            curl_setopt($curl_request, CURLOPT_POST, 1);
        }
        else
        {
            curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, strtoupper($type));
        }

        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);


        if (!empty($oauthtoken))
        {
            $header = ['Authorization: Bearer ' . $oauthtoken, "Content-Type: application/json"];
            curl_setopt($curl_request, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($payload) && strtoupper($type) !== 'GET')
        {
            curl_setopt($curl_request, CURLOPT_POSTFIELDS, $payload);
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

    function deleteObject($objectName, $objectId)
    {
        $url = $this->baseUrl."/services/data/v20.0/sobjects/$objectName/" . $objectId;
        return $this->call($url, null, $this->getAccessToken(), 'DELETE', true);
    }

    function updateRecord($objectName, $objectId, $payload)
    {
        $url = $this->baseUrl."/services/data/v20.0/sobjects/$objectName/$objectId";
        return $this->call($url, json_encode($payload), $this->getAccessToken(), 'PATCH');
    }

    function removeAttributes(&$array)
    {   
        foreach($array as $k => &$v) {
            if(is_array($v)) {
                if(array_key_exists('attributes', $v)) {
                    unset($v['attributes']);
                }
                $this->removeAttributes($v);
            }
        }
    }
    
    function getEntity($query, $groupBy = NULL) 
    {
        if(!empty($groupBy)) {
            $query .= " order by $groupBy";
        }
        $entities = [];

        $url = $this->baseUrl."/services/data/v20.0/query?q=" . urlencode($query);
        do {
            $response = $this->call($url, null, $this->getAccessToken(), 'GET', true);
            
            if (!is_array($response) || (!isset($response['records'])) || (count($response['records']) == 0) ) {
                return $entities;
            }
            $array = $response['records'];
            $this->removeAttributes($array);

            if(!empty($groupBy)) {
                foreach ($array as $record) {
                    $id = $record[$groupBy];
                    $entities[$id][] = $record;
                }
            } else {
                $entities = array_merge($entities, $array);
            }

            if($response['done'] == false) {
                $url = $this->baseUrl.$response['nextRecordsUrl'];
            }
            
        } while($response['done'] == false);

        return $entities;
    }

    
    function deleteContacts($ids)
    {
        foreach($ids as $id) {
            $this->deleteObject("Contact", $id);
        }
    }
    
    function updateContactSendyStatus($contacts)
    {
        $n = count($contacts);
        $i = 0;
        $onepercent = floor($n / 100)+1;

        foreach($contacts as $contact) 
        {    
            $res = $this->updateRecord("Contact", $contact['Id'], ['SendyStatus__c'=>$contact['status']]);
            if(!empty($res)) {
                print('error updating '.$contact['Id'].' with status '.$contact['status'].': '.$res);
            }

            $i++;
            if($i % $onepercent == 0) {
                echo floor(($i/$n)*100)."%\n";
            }

        }
    }
    
    function getContacts() 
    {
        $query = "SELECT Id, AccountId, firstName, lastName, Email, Account.Name FROM Contact WHERE Email <> ''";
        $groupBy = 'AccountId';
        return $this->getEntity($query, $groupBy);
    }

    function getAccounts() 
    {
        $query = "SELECT Id, Name FROM Account";
        return $this->getEntity($query);
    }


    function getConfigurations() 
    {
        $query = 'SELECT Account__c, BrokerID__c FROM Configuration__c';
        $groupBy = 'Account__c';
        return $this->getEntity($query, $groupBy);
    }    


    function getOpportunities()
    {
        $query = "SELECT Id, Name, AccountId, StageName, (Select Id, PricebookEntry.Product2.Name From OpportunityLineItems) FROM Opportunity";
        $groupBy = 'AccountId';
        return $this->getEntity($query, $groupBy);
    }

}


?>
