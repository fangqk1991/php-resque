<?php

namespace FC\Resque\Schedule;

use FC\Resque\Core\Resque;
use InvalidArgumentException;

class LoopRule
{
    private $_startVal;     // timestamp
    private $_endVal;       // timestamp
    private $_delta;        // seconds
    private $_conditions;

    private $_curVal;       // timestamp

    public function __construct($startVal, $endVal, $delta, $conditions = NULL)
    {
        $curValue = max($startVal, time());
        $diff = ($curValue - $startVal) % $delta;

        if($diff > 0)
        {
            $curValue -= $diff;
        }
        else
        {
            $curValue -= $delta;
        }

        $this->_delta = $delta;
        $this->_startVal = $startVal;
        $this->_endVal = $endVal;
        $this->_curVal = $curValue;
    }

    public function next()
    {
        if($this->_curVal !== NULL)
        {
            $curValue = $this->_curVal + $this->_delta;
            $this->_curVal = $curValue <= $this->_endVal ? $curValue : NULL;
        }

        return $this->_curVal;
    }
}