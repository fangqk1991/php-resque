<?php

namespace FC\Resque\Schedule;

use FC\Model\FCModel;

class LoopRule extends FCModel
{
    public $startVal;     // timestamp
    public $endVal;       // timestamp
    public $delta;        // seconds
    public $curVal;       // timestamp

    public function next()
    {
        if($this->curVal !== NULL)
        {
            $curValue = $this->curVal + $this->delta;
            $this->curVal = $curValue <= $this->endVal ? $curValue : NULL;
        }

        return $this->curVal;
    }

    protected function fc_afterGenerate($data = array())
    {
        $curVal = $this->curVal;

        if($curVal === NULL)
        {
            $curVal = time();
        }

        $curVal = max($this->startVal, $curVal);
        $diff = ($curVal - $this->startVal) % $this->delta;

        if($diff > 0)
        {
            $curVal -= $diff;
        }
        else
        {
            $curVal -= $this->delta;
        }

        $this->curVal = $curVal;
    }

    protected function fc_propertyMapper()
    {
        return [
            'startVal' => 'start_val',
            'endVal' => 'end_val',
            'delta' => 'delta',
            'curVal' => 'cur_val',
        ];
    }

    public static function generate($startVal, $endVal, $delta, $curVal = NULL)
    {
        $obj = new LoopRule();
        $obj->fc_generate([
            'start_val' => $startVal,
            'end_val' => $endVal,
            'delta' => $delta,
            'cur_val' => $curVal,
        ]);
        return $obj;
    }
}