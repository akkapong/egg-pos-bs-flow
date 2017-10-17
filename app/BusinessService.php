<?php
namespace Eggdigital;

class BusinessService {

    private $config;
    private $classes      = [];
    private $stacks       = [];
    private $responses    = [];
    private $breakProcess = '';


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
        $this->stacks[] = $each;

        //add data to response
        $this->addResponsedata($processes['service'], $datas);

        return $each;
    }

    //Method for manage fail
    protected function manageFail($service, $process, $response)
    {
        $this->addResponsedata($service, $response);
        switch ($process) {
            case 'rollback':
                $this->rollback();
                break;
            case 'break':
                $this->breakProcess = $service;
                break;
        }
    }

    //Method for manage rollback
    protected function rollback()
    {
        //get last stacks
        $lastProcesses = $this->stacks[-1];

        if (!empty($lastProcesses)) {
            //rollback
            //get processes
            $processes = $lastProcesses['processes'];
            //get class
            $class = $this->getClass($processes['service']);
            $this->requestMethod($class, $processes['rollback'], $lastProcesses['response']);
        }
    }

    //Method for get value from object
    protected function getValueFormObj($keys, $obj)
    {
        $key  = $keys[0];

        //keep in $obj
        if (isset($obj[$key])) {
            $obj  = $obj[$key];

            if (count($keys) > 1) {
                //cut first
                $keys = array_slice($keys, 1);
                return $this->getValueFormObj($keys, $obj);
            }
        } else {
            return "";
        }

        return $obj;

    }

    //Method for call method in class
    protected function requestMethod($class, $method, $params)
    {
        return $class->{$method}($params);
    }

    //Method for add respose data 
    protected function addResponsedata($service, $respose)
    {
        $this->responses[$service] = $respose;
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

            $resVal = $this->getValueFormObj(explode('.', $key), $this->responses);
            if (!empty($resVal)) {
                $outputs[$newKey] = $resVal;
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

    //Method for get response
    public function getResponse($key='')
    {
        if (empty($key) && !empty($this->breakProcess)) {
            return $this->responses[$this->breakProcess];
        
        }

        if (isset($this->responses[$key])) {
            return $this->responses[$key];
        }

        return $this->responses;
        
    }

    //Method for run service
    // processes is object
    //EX : [
    //          "main"     => "methodName",
    //          "fail"     => "rollback",
    //          "rollback" => "methodName",
    //          "service"  => "className",
    //          "format"   => ["a" => "x"],
    //     ]
    public function runServices($params, $processes)
    {
        if (!empty($this->breakProcess)) {
            return $this;
        }
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
            $this->manageFail($processes['service'], $processes['fail'], $res);
            if ($processes['fail'] == 'rollback') {
                $this->rollback();
            }
            
        }

        return $this;

    } 
    //========== End: Public Zone ==========//

}