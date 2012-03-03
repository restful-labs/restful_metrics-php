<?php
 
require_once dirname(__FILE__) . "/Client.php" ;
  
class RestfulMetrics_JobRunner
{
    /**
    * @var RestfulMetrics_Client
    */
    private $_client;

    /**
    * @var array
    */
    private $_errors = array();
    
    private $_lockId;
    
    /**
    * @var PDO
    */
    private $_pdo;
    
    private $_table_jobs;
    
    private $_table_log;
    
    public function __construct(RestfulMetrics_Client $client)
    {
        $this->_client = $client;
        $this->_client->asynchronous = false;
        
        $this->_pdo = $client->getPdo();
        
        $this->_table_jobs = RestfulMetrics::JOB_TABLE_PREFIX . "job";
        $this->_table_log  = RestfulMetrics::JOB_TABLE_PREFIX . "job_log";
    }
    
    public function run()
    {
        $this->_lockId = md5(uniqid(getmypid(), true));
        
        $jobs = $this->_fetchJobs();
        
        foreach($jobs as $job)
        {
            try
            {
                $this->_client->setApplicationId($job['app_id']);
                $this->_client->addMetric($job['metric'], unserialize($job['value']), $job['distinct_id']);
                
                $this->_logCompletedJob($job);
            }
            catch(Exception $e)
            {
                $this->_pdo->prepare("UPDATE {$this->_table_jobs} SET attempts = attempts + 1, last_error = ? WHERE id = ?")
                           ->execute(array($e->getMessage(), $job['id']));
                
                $this->_errors[] = $e->getMessage();
            }
        }
        
        // Unlock locked jobs
        $this->_pdo->prepare("UPDATE {$this->_table_jobs} SET lock_id = NULL WHERE lock_id = ?")
                   ->execute(array($this->_lockId));
    }
    
    public function getErrors()
    {
        return $this->_errors;
    }
    
    /**
    * @return array     Multi-dimensional array of jobs to run
    */
    private function _fetchJobs()
    {
        $this->_pdo->prepare("UPDATE {$this->_table_jobs} SET lock_id = ? WHERE lock_id IS NULL")
                   ->execute(array($this->_lockId));
        
        $sth = $this->_pdo->prepare("SELECT * FROM {$this->_table_jobs} WHERE lock_id = ?");
        $sth->execute(array($this->_lockId));
        
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function _logCompletedJob(array $job)
    {
        $this->_pdo->prepare("INSERT INTO {$this->_table_log} (id,app_id,metric,value,distinct_id,created_at,sent_at) VALUES(?,?,?,?,?,?,?)")
                   ->execute(array($job['id'], $job['app_id'], $job['metric'], $job['value'], $job['distinct_id'], $job['created_at'], date("Y-m-d H:i:s")));
        
        $this->_pdo->prepare("DELETE FROM {$this->_table_jobs} WHERE id = ? LIMIT 1")
                   ->execute(array($job['id']));
    }
}