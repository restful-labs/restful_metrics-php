<?php

require dirname(__FILE__) . '/RestfulMetrics/Client.php';
require dirname(__FILE__) . '/RestfulMetrics/Exception.php';

/**
* Abstract interface to RESTful Metrics library.
* 
* Usage: 
* 
* RestfulMetrics::setup("apikey", "app_id");
* RestfulMetrics::asynchronous(new PDO("DSN_STRING"));
* 
* RestfulMetrics::addMetric("simple", 1);
* RestfulMetrics::addMetric("compound", array("a", "b", "c"));
*  
* RestfulMetrics::runDelayedJobs();
* 
* @author   Adam Huttler <adam@restul-labs.com>
* @version  0.3
*/
abstract class RestfulMetrics
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
    * Prefix for the tables that will store delayed jobs.
    */
    const JOB_TABLE_PREFIX = "rm_";

    /**
    * Client object that will be used globally
    * 
    * @var RestfulMetrics_Client
    */
    static public $client;
    
    static public function setup($api_key, $app_id, $distinct_id = null)
    {
        if(!self::$client)
        {
            self::$client = new RestfulMetrics_Client();
        }
        
        self::$client->setApiKey($api_key);
        self::$client->setApplicationId($app_id);
        
        if($distinct_id)
        {
            self::$client->setDistinctId($distinct_id);
        }
    }
    
    static public function asynchronous(PDO $pdo)
    {
        self::$client->asynchronous = true;
        self::$client->setPdo($pdo);
    }
    
    static public function addMetric($metric, $value, $distinct_id = null)
    {
        return self::$client->addMetric($metric, $value, $distinct_id);
    }
    
    static public function runDelayedJobs()
    {
        if(!class_exists("RestfulMetrics_JobRunner"))
        {
            require dirname(__FILE__) . '/RestfulMetrics/JobRunner.php';
        }
        
        $jr = new RestfulMetrics_JobRunner(self::$client);
        $jr->run();
        
        if($errors = $jr->getErrors())
        {
            throw new RestfulMetrics_Exception("Errors running delayed jobs: \n" . implode("\n", $errors));
        }
    }
}