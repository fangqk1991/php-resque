<?php

namespace FC\Resque;

interface IJob
{
	/**
	 * @return bool
	 */
	public function perform();
}
