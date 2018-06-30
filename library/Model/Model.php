<?php

namespace FC\Utils\Model;

abstract class Model
{
    abstract protected function fc_propertyMapper();

    protected function fc_defaultInit() {}
    protected function fc_afterGenerate($data = array()) {}

    public function __construct()
    {
        $this->fc_defaultInit();
    }

    final public function fc_generate($data)
    {
        $propertyMap = $this->fc_propertyMapper();
        $propertyClassMap = $this->fc_propertyClassMapper();
        $itemClassMap = $this->fc_arrayItemClassMapper();

        foreach($propertyMap as $property => $jsonKey)
        {
            if(isset($data[$jsonKey]) && property_exists($this, $property))
            {
                if(isset($propertyClassMap[$property]) && is_array($data[$jsonKey]))
                {
                    $class = $propertyClassMap[$property];
                    $obj = new $class();
                    if($obj instanceof Model)
                    {
                        $obj->fc_generate($data[$jsonKey]);
                        $this->$property = $obj;
                    }
                }
                else if(isset($itemClassMap[$property]) && is_array($data[$jsonKey]))
                {
                    $arr = array();
                    $class = $itemClassMap[$property];
                    foreach ($data[$jsonKey] as $dic)
                    {
                        $obj = new $class();
                        if($obj instanceof Model)
                        {
                            $obj->fc_generate($dic);
                            array_push($arr, $obj);
                        }
                        else
                        {
                            array_push($arr, NULL);
                        }
                    }
                    $this->$property = $arr;
                }
                else
                {
                    $this->$property = $data[$jsonKey];
                }
            }
        }

        $this->fc_afterGenerate($data);
    }

    final protected function fc_encode()
    {
        $propertyMap = $this->fc_propertyMapper();

        $data = array();
        foreach($propertyMap as $property => $jsonKey)
        {
            if(property_exists($this, $property))
            {
                $data[$jsonKey] = $this->$property;
            }
        }

        return $data;
    }

    public function fc_retMap()
    {
        return $this->fc_encode();
    }

    protected function fc_propertyClassMapper()
    {
        return array();
    }

    protected function fc_arrayItemClassMapper()
    {
        return array();
    }
}