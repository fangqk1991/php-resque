<?php

namespace FC\Resque;

interface IResqueTask
{
	public function perform($args);
}
