<?php

require_once dirname(__FILE__) . '/Exception.php';

/**
* Simple client for RESTful Metrics.
* 
* Usage: 
* 
* require 'path/to/RestfulMetrics/Client.php';
* 
* $rm = new RestfulMetrics_Client("apikey", "app_id");
* $rm->addMetric("simple", 1);
* $rm->addMetric("compound", array("a", "b", "c"));
*  
* @author   Adam Huttler <adam@restul-labs.com>
* @version  0.2
*/
class RestfulMetrics_Client
{
    /**
    * Whether the client should automatically assign and track distinct user ids. This is 
    * ignored when PHP is running in CLI.
    */
    const AUTO_DISTINCT_ID        = true;
    
    /**
    * When using automatic distinct user tracking, this will be set as the name of the cookie. 
    */
    const AUTO_DISTINCT_ID_COOKIE = "__rm_distinct_id";

    /**
    * When using automatic distinct user tracking, this will be set as the path for which the 
    * cookie is valid. 
    */
    const AUTO_DISTINCT_ID_PATH   = "/";
    
    /**
    * When using automatic distinct user tracking, this will be set as the domain for which the 
    * cookie is valid. If set to false, then it will default to $_SERVER['HTTP_HOST'].
    */
    const AUTO_DISTINCT_ID_DOMAIN = false;
    
    /**
    * Boolean flag to send requests asynchronously
    * 
    * @var boolean
    */
    public $asynchronous = false;

    /**
    * Boolean flag to disable in staging/development
    * 
    * @var boolean
    */
    public $disabled = false;
    
    /**
    * The API Key for the account
    * 
    * @var string
    */
    private $_apikey;
    
    /**
    * Appliction identifier
    * 
    * @var string
    */
    private $_appId;
    
    /**
    * Distinct user identifier - optionally set globally and/or automatically
    * 
    * @var string
    */
    private $_distinctId;
    
    /**
    * @param string $apikey     Account API Key
    * @param string $app_id     Application identifier
    * 
    * @return RestfulMetrics_Client
    */
    public function __construct($apikey = '', $app_id = '')
    {
        if($apikey)
        {
            $this->_apikey = $apikey;
        }
        
        if($app_id)
        {
            $this->_appId = $app_id;
        }
        
        if(self::AUTO_DISTINCT_ID && ('cli' !== PHP_SAPI))
        {
            $this->_autoTrackDistinctId();
        }
    }

    /**
    * Add a metric
    * 
    * @param string $metric                 The name of the metric
    * @param mixed $value                   The value - should be a scalar for standard metrics or an array for compound metrics
    * @param mixed $distinct_user_id        Optional unique user identifier for this metric. Note that this is generally assigned globally instead.
    */
    public function addMetric($metric, $value, $distinct_user_id = null)
    {
        if(!$this->_apikey)
        {
            throw new RestfulMetrics_Exception("API Key must be set before adding metrics");
        }
        
        if(!$this->_appId)
        {
            throw new RestfulMetrics_Exception("Application identifier must be set before adding metrics");
        }
        
        if($this->disabled)
        {
            return true;
        }
        
        if(is_array($value))
        {
            $endpoint = sprintf("http://track.restfulmetrics.com/apps/%s/compound_metrics.json", urlencode($this->_appId));
            $data = array(
                'compound_metric' => array(
                    'name' => $metric,
                    'values' => $value));
        }
        else
        {
            $endpoint = sprintf("http://track.restfulmetrics.com/apps/%s/metrics.json", urlencode($this->_appId));
            $data = array(
                'metric' => array(
                    'name' => $metric,
                    'value' => $value));
        }
        
        if($distinct_user_id)
        {
            $data['distinct_id'] = $distinct_user_id;
        }
        elseif($this->_distinctId)
        {
            $data['distinct_id'] = $this->_distinctId;
        }
        
        $json_data = json_encode($data);        
        
        if($this->asynchronous)
        {
            $this->_executeAsynchronousRequest($endpoint, $json_data);
        }
        else
        {
            $response = $this->_executeRequest($endpoint, $json_data);
            
            return $resposne;
        }
    }
    
    /**
    * Sets API key
    * 
    * @param string $key    Account API Key
    */
    public function setApiKey($key)
    {
        $this->_apikey = $key;
    }
    
    /**
    * Sets application identifier
    * 
    * @param string $app_id    Application identifier
    */
    public function setApplicationId($app_id)
    {
        $this->_appId = $app_id;
    }
    
    /**
    * Optionally set the user's distinct_id globally
    * 
    * @param string $id
    */
    public function setDistinctId($id)
    {
        $this->_distinctId = $id;
    }
    
    private function _autoTrackDistinctId()
    {
        if(isset($_COOKIE[self::AUTO_DISTINCT_ID_COOKIE]))
        {
            $this->setDistinctId($_COOKIE[self::AUTO_DISTINCT_ID_COOKIE]);
        }
        elseif(headers_sent($file, $line))
        {
            trigger_error("Can't set distinct_id cookie since headers were already sent at $file:$line");
        }
        else
        {
            $cookie = self::AUTO_DISTINCT_ID_COOKIE;
            $value  = sha1(uniqid(uniqid("rm", true), true));
            $expiry = time() + 86400 * 365 * 5;
            $path   = self::AUTO_DISTINCT_ID_PATH;
            $domain = self::AUTO_DISTINCT_ID_DOMAIN ? self::AUTO_DISTINCT_ID_DOMAIN : $_SERVER['HTTP_HOST'];
            
            setcookie($cookie, $value, $expiry, $path, $domain);
            
            $this->setDistinctId($value);
        }
    }
    
    private function _executeRequest($endpoint, $json_data)
    {
        $params = array('http' => array(
                            'method' => 'POST',
                            'content' => $json_data,
                            'header' => "Authorization: {$this->_apikey}\r\n" . 
                                        "Content-Type: application/json; charset=utf-8\r\n"));
        
        $ctx = stream_context_create($params);
        $fp  = fopen($endpoint, 'r', false, $ctx);
        if(!$fp) 
        {
            throw new RestfulMetrics_Exception("Problem executing request at $endpoint: $php_errormsg");
        }
        
        $response = stream_get_contents($fp);
        if($response === false) 
        {
            throw new RestfulMetrics_Exception("Problem reading response data: $php_errormsg");
        }
        
        $status = substr($http_response_header[0], 9, 3);
        
        switch($status)
        {
            case "200":
                return true;
                
            case "401":
                throw new RestfulMetrics_Exception("Received unauthorized response; check your API Key");
                
            default:
                throw new RestfulMetrics_Exception("An undefined error occurred when sending the metric. Received response status code: $status");
        }
    }
    
    private function _executeAsynchronousRequest($endpoint, $json_data)
    {
        $parts = parse_url($endpoint);
        $port  = isset($parts['port']) ? $parts['port'] : 80;
                
        $fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);
        if(!$fp) 
        {
            throw new RestfulMetrics_Exception("Failed to establish socket connection: $errstr");
        }

        $out  = "POST " . $parts['path'] . " HTTP/1.1\r\n";
        $out .= "Host: " . $parts['host'] . "\r\n";
        $out .= "Authorization: {$this->_apikey}\r\n";
        $out .= "Content-Type: application/json; charset=utf-8\r\n";
        $out .= "Content-Length: " . strlen($json_data) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        $out .= $json_data;
        
        fwrite($fp, $out);
        fclose($fp);
    }
}