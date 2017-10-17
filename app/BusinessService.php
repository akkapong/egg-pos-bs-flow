<?php

class BusinessService {

    private $config;
    private $classes   = [];
    private $stacks    = [];
    private $responses = [];


    public function __construct($configs) {
        $this->configs = $configs;
    } 

    //========== Start: Private Zone ==========//
    //Method for manage success
    protected function manageSuccess($processes, $params, $datas)
    {
        $each = [
            'processes' => $processes,
            'params'    => $params,
            'response'  => $datas,
        ];
        //keep response data to stack
        $this->stacks[] = $each

        //add data to response
        $this->addResponsedata($processes['service'], $datas);

        return $each;
    }

    //Method for manage fail
    protected function manageFail()
    {
        //get last stacks
        $lastProcesses = $this->stacks[-1];

        if (!empty($lastProcesses)) {
            //rollback
            //get processes
            $processes = $lastProcesses['processes'];
            //get class
            $class = $this->getClass($processes['service']);
            $this->requestMethod($class, $processes['fail'], $lastProcesses['response']);
        }
    }

    //Method for call method in class
    protected function requestMethod($class, $method, $params)
    {
        return $class->{$method}($params);
    }

    //Method for add respose data 
    protected function addResponsedata($service, $respose)
    {
        foreach ($respose as $key => $value) {
            $key = $service.'_'.$key;
            $this->responses[$key] = $value;
        }

    }

    //method for format param
    protected function formatParams($params, $formats)
    {
        $outputs = [];
        foreach ($formats as $key => $newKey) {
            if (isset($params[$key])) {
                $outputs[$newKey] = $params[$key];
                continue;
            }

            if (isset($this->responses[$key])) {
                $outputs[$newKey] = $this->responses[$key];
            }
        }

        return $outputs;
    }
    //========== End: Private Zone ==========//

    //========== Start: Public Zone ==========//
    //Method for set class
    public function setClass($name, $class) 
    {
        $this->classes[$name] = $class;
    }

    //Method for get class
    public function getClass($name)
    {
        return $this->classes[$name];
    }

    //Method for run service
    // processes is object
    //EX : [
    //          "main" => "methodName",
    //          "fail" => "methodName",
    //          "service" => "className",
    //          "format" => ["a" => "x"],
    //     ]
    public function runServices($params, $processes)
    {
        //TODO
        $service = $processes['service'];
        //format params
        $params  = $this->formatParams($params, $processes['format']);
        //get class
        $class   = $this->getClass($service);
        
        $res     = $this->requestMethod($class, $processes['main'], $params);

        if ($res['success']) {
            //Success
            $this->manageSuccess($processes, $params, $res['data']);
        } else {
            //Fail
            $this->manageFail();
        }

        return $this;

    } 
    //========== End: Public Zone ==========//

}