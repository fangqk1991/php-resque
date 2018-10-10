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
            $curVal = $this->curVal + $this->delta;
            $curVal -= ($curVal - $this->startVal) % $this->delta;

            $this->curVal = $curVal <= $this->endVal ? $curVal : FALSE;
        }

        return $this->curVal;
    }

    protected function fc_afterGenerate($data = array())
    {
        if($this->curVal === NULL)
        {
            $this->curVal = max($this->startVal, time());
        }
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