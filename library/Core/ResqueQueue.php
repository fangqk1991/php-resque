<?php

namespace FC\Resque\Core;

use Redis;
use RuntimeException;

class ResqueQueue
{
	public static function removeQueue($queue)
	{
        Resque::redis()->del('resque:queue:' . $queue);
        Resque::redis()->srem('resque:queues', $queue);
	}

	public static function queueSize($queue)
	{
		return Resque::redis()->llen('resque:queue:' . $queue);
	}

	public static function queues()
	{
		$queues = Resque::redis()->smembers('resque:queues');
        sort($queues);
		return $queues;
	}
}
