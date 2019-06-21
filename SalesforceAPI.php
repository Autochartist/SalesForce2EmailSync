<?php

class SalesforceAPI {
    private $baseUrl = "https://login.salesforce.com";
    private $username;
    private $password;
    private $clientId;
    private $clientSecret;

    private $accessToken = null;

    /**
     * SalesforceAPI constructor.
     * @param $baseUrl
     * @param $username
     * @param $password
     * @param $clientId
     * @param $clientSecret
     */
    public function __construct($baseUrl, $username, $password, $clientId, $clientSecret) {
        $this->baseUrl      = $baseUrl;
        $this->username     = $username;
        $this->password     = $password;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * authenticate Salesforce user
     */
    public function authenticate() {
        $url = $this->baseUrl . "/services/oauth2/token";

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
}

?>