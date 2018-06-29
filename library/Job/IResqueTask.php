<?php

namespace FC\Resque\Job;

interface IResqueTask
{
	public function perform($params);
}
