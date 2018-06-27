<?php

namespace FC\Resque;

interface IResqueTask
{
	/**
	 * @return bool
	 */
	public function perform();
}
