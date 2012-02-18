<?php

require_once dirname(__FILE__) . '/Exception.php';

/**
* Simple client for RESTful Metrics.
* 
* Usage: 
* 
* require 'path/to/RestfulMetrics/Client.php';
* $rm = new RestfulMetrics_Client("apikey", "app_id");
* $rm->addMetric("foo", 1, "user_id");
*  
* @author   Adam Huttler <adam@restul-labs.com>
* @version  0.1
*/
class RestfulMetrics_Client
{
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
    }

    /**
    * Add a metric
    * 
    * @param string $metric                 The name of the metric
    * @param mixed $value                   The value - should be a scalar for standard metrics or an array for compound metrics
    * @param mixed $distinct_user_id        Optional unique user identifier
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