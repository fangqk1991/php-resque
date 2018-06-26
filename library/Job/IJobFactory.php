<?php

namespace FC\Resque\Job;

interface IJobFactory
{
	public function create($className, $args, $queue);
}
