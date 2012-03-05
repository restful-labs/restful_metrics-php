# RestfulMetrics-php v0.3
A simple PHP library for RESTful Metrics. See http://www.restfulmetrics.com

## Setup and Configuration 

1) Adjust the class constants in the base RestfulMetrics class as needed. 

2) Run the setup.sql queries to initialize your database tables for delayed jobs. 
   Make sure to adjust the table names if you want to use non-default prefixes.


## Basic Usage

```
   RestfulMetrics::setup("API_KEY", "APP_ID");
   RestfulMetrics::asynchronous(new PDO("DSN_STRING"));

   RestfulMetrics::addMetric("simple_metric", 1);
   RestfulMetrics::addMetric("compound_metric", array("foo", "bar");
```

## Alternate/Verbose Usage

```
   require '[PATH/TO]/RestfulMetrics/Client.php';
   $rm = new RestfulMetrics_Client([APIKEY], [APP_ID]);
   $rm->addMetric("simple", 1);
   $rm->addMetric("compound", array("a", "b", "c"));
```

## Asynchronous Execution via Delayed Jobs

```
   RestfulMetrics::setup("API_KEY", "APP_ID");
   RestfulMetrics::asynchronous(new PDO("DSN_STRING"));
   RestfulMetrics::runDelayedJobs();
```
