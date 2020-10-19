<?php

namespace SendyPHP;


/**
 * Sendy Class
 */
class SendyPHP
{
    protected $installation_url;
    protected $api_key;
    protected $list_id;

    public function __construct(array $config)
    {
        //error checking
        $list_id = @$config['list_id'];
        $installation_url = @$config['installation_url'];
        $api_key = @$config['api_key'];
        
        if (empty($list_id)) {
            throw new \Exception("Required config parameter [list_id] is not set or empty");
        }
        
        if (empty($installation_url)) {
            throw new \Exception("Required config parameter [installation_url] is not set or empty");
        }
        
        if (empty($api_key)) {
            throw new \Exception("Required config parameter [api_key] is not set or empty");
        }

        $this->list_id = $list_id;
        $this->installation_url = $installation_url;
        $this->api_key = $api_key;
    }

    public function setListId($list_id)
    {
        if (empty($list_id)) {
            throw new \Exception("Required config parameter [list_id] is not set");
        }

        $this->list_id = $list_id;
    }

    public function getListId()
    {
        return $this->list_id;
    }

    public function subscribe(array $values)
    {
        if (empty($values)) {
            return array(
                'status' => false,
                'message' => "Array or values required"
            );
        }
        if (empty($values['email'])) {
            return array(
                'status' => false,
                'message' => "'email' field is mandatory"
            );
        }
        if(!empty($values['BrokerID'])) {
            $type = $type;
        }
        $type = 'subscribe.php';

        //Send the subscribe
        try {
            $result = strval($this->buildAndSend($type, $values));
        } catch(\Exception $e) {
            return array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        //Handle results
        switch ($result) {
            case '1':
                return array(
                    'status' => true,
                    'message' => 'Subscribed'
                    );
                break;

            case 'Already subscribed.':
                return array(
                    'status' => true,
                    'message' => 'Already subscribed.'
                    );
                break;

            default:
                return array(
                    'status' => false,
                    'message' => $result
                    );
                break;
        }
    }

    public function unsubscribe($email)
    {
        if (empty($email)) {
            return array(
                'status' => false,
                'message' => "'email' field is mandatory"
            );
        }

        $type = 'unsubscribe.php';

        //Send the unsubscribe
        try {
            $result = strval($this->buildAndSend($type, array('email' => $email)));
        } catch(\Exception $e) {
            return array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        //Handle results
        switch ($result) {
            case '1':
                return array(
                    'status' => true,
                    'message' => 'Unsubscribed'
                    );
                break;
            
            default:
                return array(
                    'status' => false,
                    'message' => $result
                    );
                break;
        }
    }

    public function substatus($email)
    {
        if (!isset($email)) {
            return array(
                'status' => false,
                'message' => "'email' field is mandatory"
            );
        }

        $type = 'api/subscribers/subscription-status.php';

        //Send the request for status
        try {
            $result = $this->buildAndSend($type, array(
                'email' => $email,
                'api_key' => $this->api_key,
                'list_id' => $this->list_id
            ));
        } catch(\Exception $e) {
            return array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        //Handle the results
        return array(
            'status' => true,
            'message' => $result
        );
    }

    public function subcount($list = "")
    {
        $type = 'api/subscribers/active-subscriber-count.php';

        //if a list is passed in use it, otherwise use $this->list_id
        if (empty($list)) {
            $list = $this->list_id;
        }

        //handle exceptions
        if (empty($list)) {
            throw new \Exception("method [subcount] requires parameter [list] or [$this->list_id] to be set.");
        }


        //Send request for subcount
        try {
            $result = $this->buildAndSend($type, array(
                'api_key' => $this->api_key,
                'list_id' => $list
            ));
        } catch(\Exception $e) {
            return array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        //Handle the results
        if (is_numeric($result)) {
            return array(
                'status' => true,
                'message' => $result
            );
        }

        //Error
        return array(
            'status' => false,
            'message' => $result
        );
    }

    public function createCampaign(array $values)
    {
        $type = 'api/campaigns/create.php';

        //Global options
        $global_options = array(
            'api_key' => $this->api_key
        );

        //Merge the passed in values with the global options
        $values = array_merge($global_options, $values);

        //Send request for campaign
        try {
            $result = $this->buildAndSend($type, $values);
        } catch(\Exception $e) {
            return array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        //Handle the results
        switch ($result) {
            case 'Campaign created':
            case 'Campaign created and now sending':
                return array(
                    'status' => true,
                    'message' => $result
                );
                break;

            default:
                return array(
                    'status' => false,
                    'message' => $result
                );
                break;
        }
    }

    private function buildAndSend($type, array $values)
    {
        //error checking
        if (empty($type)) {
            throw new \Exception("Required config parameter [type] is not set or empty");
        }

        if (empty($values)) {
            throw new \Exception("Required config parameter [values] is not set or empty");
        }

        //Global options for return
        $return_options = array(
            'list' => $this->list_id,
            'boolean' => 'true'
        );

        //Merge the passed in values with the options for return
        $content = array_merge($values, $return_options);

        //build a query using the $content
        $postdata = http_build_query($content);

        // init curl
        $ch = curl_init();

        // Settings to disable SSL verification for testing (leave commented for production use)
        curl_setopt($ch, CURLOPT_URL, $this->installation_url .'/'. $type);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        $result = curl_exec($ch);
        if($result == '') {
            curl_close($ch);
            throw new \Exception("cURL error: empty response.");
        }
        if($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            curl_close($ch);
            throw new \Exception("cURL error ({$errno}):\n {$error_message}");
        }
        curl_close($ch);
        return $result;
    }

    private static function array2csv($data)
    {
        if(($data == null) || (count($data) == 0))
            return null;
        if(count($data) == 1) {
            return $data[0];
        }
        
        $csv = "";
        $i = 0;
        foreach ($data as $item) {
            if($i == 0) {
                $csv = $item;
            } else {
                $csv .= ",".$item;
            }
            $i++;
        }
        return $csv;
    }

    function updateContact(&$contact, $listid) 
    {
        // set listid
        $this->setListId($listid);

        $contact['status'] = '';    // set default status to unknown

        // check status. 
        $res = $this->substatus($contact['Email']);
        if($res['status'] === false) {

            return "error Error getting subscription status: ".$res['message'];

        } 

        // only update details if the user is still subscribed
        if(($res['message'] == 'Subscribed') || ($res['message'] == 'Email does not exist in list')) {

            $subscriber = array(
                'email' => $contact['Email'],
                'FirstName' => $contact['FirstName'],
                'LastName' => $contact['LastName'],
                'AccountName' => $contact['accountname'],
                'BrokerIDs' => self::array2csv($contact['brokerids']),
                'Products' => self::array2csv($contact['products']),
                'Stage' => $contact['stage'],
                'api_key' => $this->api_key,
                'list_id' => $this->list_id
            );

            // send new info to sendy
            $res = $this->subscribe($subscriber);
            if($res['status'] != 1) {
                return "error Error subscribing: ".$res['message'];
            }   

            $res = $this->substatus($contact['Email']);
            if($res['status'] !== false) {
                $contact['status'] = $res['message'];
            }
    
        }

        return $res['message'];
    }

    function updateContacts(&$contacts, $lists) 
    {
        $n = count($contacts) * count($lists);
        $i = 0;
        $onepercent = floor($n / 100)+1;
    
        foreach($lists as $listid => $listname) 
        {
            echo "Updating $listname ($listid)\n";
        
            foreach($contacts as &$contact) 
            {    
                $this->updateContact($contact, $listid);

                $i++;
                if($i % $onepercent == 0) {
                    echo floor(($i/$n)*100)."%\n";
                }
            }
        }  
    }  
    
    
}

