<?php

if(!class_exists("RestfulMetrics"))
{
    require dirname(__FILE__) . "/../RestfulMetrics.php";
}

/**
* Simple client for RESTful Metrics. May be used directly if you don't want 
* to use the abstract RestfulMetrics class.
* 
* Usage: 
* 
* require 'path/to/RestfulMetrics/Client.php';
* 
* $rm = new RestfulMetrics_Client("apikey", "app_id");
* $rm->addMetric("simple", 1);
* $rm->addMetric("compound", array("a", "b", "c"));
*  
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
    * PDO object; needed for sending requests asynchronously as delayed jobs
    * 
    * @var PDO
    */
    private $_pdo;
    
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
        
        if(RestfulMetrics::AUTO_DISTINCT_ID && ('cli' !== PHP_SAPI))
        {
            $this->_autoTrackDistinctId();
        }
    }

    /**
    * Add a metric
    * 
    * @param string $metric                 The name of the metric
    * @param mixed $value                   The value - should be a scalar for standard metrics or an array for compound metrics
    * @param mixed $distinct_id             Optional unique user identifier for this metric. Note that this is generally assigned globally instead.
    */
    public function addMetric($metric, $value, $distinct_id = null)
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
        
        if(null === $distinct_id)
        {
            $distinct_id = $this->_distinctId;
        }
        
        if($this->asynchronous)
        {
            $this->_queueDelayedJob($metric, $value, $distinct_id);
            
            return;
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
        
        if(isset($distinct_id))
        {
            $key = array_key_exists("metric", $data) ? "metric" : "compound_metric";
            $data[$key]['distinct_id'] = $distinct_id;            
        }
        
        return $this->_executeRequest($endpoint, json_encode($data));
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
 
    /**
    * Set a PDO object for storing delayed jobs for asynchronous execution
    * 
    * @param PDO $pdo
    */
    public function setPdo(PDO $pdo)
    {
        $this->_pdo = $pdo;
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function getPdo()
    {
        return $this->_pdo;
    }
    
    private function _autoTrackDistinctId()
    {
        if(isset($_COOKIE[RestfulMetrics::AUTO_DISTINCT_ID_COOKIE]))
        {
            $this->setDistinctId($_COOKIE[RestfulMetrics::AUTO_DISTINCT_ID_COOKIE]);
        }
        elseif(headers_sent($file, $line))
        {
            trigger_error("Can't set distinct_id cookie since headers were already sent at $file:$line");
        }
        else
        {
            $cookie = RestfulMetrics::AUTO_DISTINCT_ID_COOKIE;
            $value  = sha1(uniqid(uniqid("rm", true), true));
            $expiry = time() + 86400 * 365 * 5;
            $path   = RestfulMetrics::AUTO_DISTINCT_ID_PATH;
            $domain = RestfulMetrics::AUTO_DISTINCT_ID_DOMAIN ? 
                RestfulMetrics::AUTO_DISTINCT_ID_DOMAIN : $_SERVER['HTTP_HOST'];
            
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
    
    private function _queueDelayedJob($metric, $value, $distinct_id)
    {
        if(!$this->_pdo instanceof PDO)
        {
            throw new RestfulMetrics_Exception("Can't queue a delayed job without first setting a PDO database connection");
        }
        
        $table = RestfulMetrics::JOB_TABLE_PREFIX . "job";
        
        $this->_pdo->prepare("INSERT INTO $table (app_id,metric,value,distinct_id,created_at) VALUES (?,?,?,?,?)")
                   ->execute(array($this->_appId, $metric, serialize($value), $distinct_id, date("Y-m-d H:i:s")));
    }
}