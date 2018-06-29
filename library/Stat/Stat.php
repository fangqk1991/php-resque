<?php

namespace FC\Resque\Stat;

use FC\Resque\Resque;

class Stat
{
	public static function get($stat)
	{
		return intval(Resque::redis()->get('resque:stat:' . $stat));
	}

	public static function incr($stat, $by = 1)
	{
	    Resque::redis()->incrby('resque:stat:' . $stat, $by);
	}

	public static function decr($stat, $by = 1)
	{
	    Resque::redis()->decrby('resque:stat:' . $stat, $by);
	}

	public static function clear($stat)
	{
	    Resque::redis()->del('resque:stat:' . $stat);
	}
}