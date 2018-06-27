<?php

namespace FC\Resque;

class Resque
{
	const VERSION = '1.3';

    const DEFAULT_INTERVAL = 5;

	public static $redis = null;
	protected static $redisServer = null;
	protected static $redisDatabase = 0;

	public static function setBackend($server, $database = 0)
	{
		self::$redisServer   = $server;
		self::$redisDatabase = $database;
		self::$redis         = null;
	}

	public static function redis()
	{
		if (self::$redis !== null) {
			return self::$redis;
		}

		if (is_callable(self::$redisServer)) {
			self::$redis = call_user_func(self::$redisServer, self::$redisDatabase);
		} else {
			self::$redis = new Redis(self::$redisServer, self::$redisDatabase);
		}

		return self::$redis;
	}

	/**
	 * fork() helper method for php-resque that handles issues PHP socket
	 * and phpredis have with passing around sockets between child/parent
	 * processes.
	 *
	 * Will close connection to Redis before forking.
	 *
	 * @return int Return vars as per pcntl_fork(). False if pcntl_fork is unavailable
	 */
	public static function fork()
	{
		if(!function_exists('pcntl_fork')) {
			return false;
		}

		// Close the connection to Redis before forking.
		// This is a workaround for issues phpredis has.
		self::$redis = null;

		$pid = pcntl_fork();
		if($pid === -1) {
			throw new \RuntimeException('Unable to fork child worker.');
		}

		return $pid;
	}

	/**
	 * Push a job to the end of a specific queue. If the queue does not
	 * exist, then create it as well.
	 *
	 * @param string $queue The name of the queue to add the job to.
	 * @param array $item Job description as an array to be JSON encoded.
	 */
	public static function push($queue, $item)
	{
		$encodedItem = json_encode($item);
		if ($encodedItem === false) {
			return false;
		}
		self::redis()->sadd('queues', $queue);
		$length = self::redis()->rpush('queue:' . $queue, $encodedItem);
		if ($length < 1) {
			return false;
		}
		return true;
	}

	/**
	 * Pop an item off the end of the specified queue, decode it and
	 * return it.
	 *
	 * @param string $queue The name of the queue to fetch an item from.
	 * @return array Decoded item from the queue.
	 */
	public static function pop($queue)
	{
        $item = self::redis()->lpop('queue:' . $queue);

		if(!$item) {
			return;
		}

		return json_decode($item, true);
	}

	/**
	 * Remove items of the specified queue
	 *
	 * @param string $queue The name of the queue to fetch an item from.
	 * @param array $items
	 * @return integer number of deleted items
	 */
	public static function dequeue($queue, $items = Array())
	{
	    if(count($items) > 0) {
		return self::removeItems($queue, $items);
	    } else {
		return self::removeList($queue);
	    }
	}

	/**
	 * Remove specified queue
	 *
	 * @param string $queue The name of the queue to remove.
	 * @return integer Number of deleted items
	 */
	public static function removeQueue($queue)
	{
	    $num = self::removeList($queue);
	    self::redis()->srem('queues', $queue);
	    return $num;
	}

	/**
	 * Pop an item off the end of the specified queues, using blocking list pop,
	 * decode it and return it.
	 *
	 * @param array         $queues
	 * @param int           $timeout
	 * @return null|array   Decoded item from the queue.
	 */
	public static function blpop(array $queues, $timeout)
	{
	    $list = array();
	    foreach($queues AS $queue) {
		$list[] = 'queue:' . $queue;
	    }

	    $item = self::redis()->blpop($list, (int)$timeout);

	    if(!$item) {
		return;
	    }

	    /**
	     * Normally the Resque_Redis class returns queue names without the prefix
	     * But the blpop is a bit different. It returns the name as prefix:queue:name
	     * So we need to strip off the prefix:queue: part
	     */
	    $queue = substr($item[0], strlen(self::redis()->getPrefix() . 'queue:'));

	    return array(
		'queue'   => $queue,
		'payload' => json_decode($item[1], true)
	    );
	}

	/**
	 * Return the size (number of pending jobs) of the specified queue.
	 *
	 * @param string $queue name of the queue to be checked for pending jobs
	 *
	 * @return int The size of the queue.
	 */
	public static function size($queue)
	{
		return self::redis()->llen('queue:' . $queue);
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $trackStatus Set to true to be able to monitor the status of a job.
	 *
	 * @return string|boolean Job ID when the job was created, false if creation was cancelled due to beforeEnqueue
	 */
	public static function enqueue($queue, $class, $args = null, $trackStatus = false)
	{
		$id         = Resque::generateJobId();
		$hookParams = array(
			'class' => $class,
			'args'  => $args,
			'queue' => $queue,
			'id'    => $id,
		);

        Event::trigger('beforeEnqueue', $hookParams);
		ResqueJob::create($queue, $class, $args, $trackStatus, $id);
        Event::trigger('afterEnqueue', $hookParams);

		return $id;
	}

	/**
	 * Reserve and return the next available job in the specified queue.
	 *
	 * @param string $queue Queue to fetch next available job from.
	 * @return false|ResqueJob|object
	 */
	public static function reserve($queue)
	{
		return ResqueJob::reserve($queue);
	}

	/**
	 * Get an array of all known queues.
	 *
	 * @return array Array of queues.
	 */
	public static function queues()
	{
		$queues = self::redis()->smembers('queues');
		if(!is_array($queues)) {
			$queues = array();
		}
		return $queues;
	}

	/**
	 * Remove Items from the queue
	 * Safely moving each item to a temporary queue before processing it
	 * If the Job matches, counts otherwise puts it in a requeue_queue
	 * which at the end eventually be copied back into the original queue
	 *
	 * @private
	 *
	 * @param string $queue The name of the queue
	 * @param array $items
	 * @return integer number of deleted items
	 */
	private static function removeItems($queue, $items = Array())
	{
		$counter = 0;
		$originalQueue = 'queue:'. $queue;
		$tempQueue = $originalQueue. ':temp:'. time();
		$requeueQueue = $tempQueue. ':requeue';

		// move each item from original queue to temp queue and process it
		$finished = false;
		while (!$finished) {
			$string = self::redis()->rpoplpush($originalQueue, self::redis()->getPrefix() . $tempQueue);

			if (!empty($string)) {
				if(self::matchItem($string, $items)) {
					self::redis()->rpop($tempQueue);
					$counter++;
				} else {
					self::redis()->rpoplpush($tempQueue, self::redis()->getPrefix() . $requeueQueue);
				}
			} else {
				$finished = true;
			}
		}

		// move back from temp queue to original queue
		$finished = false;
		while (!$finished) {
			$string = self::redis()->rpoplpush($requeueQueue, self::redis()->getPrefix() .$originalQueue);
			if (empty($string)) {
			    $finished = true;
			}
		}

		// remove temp queue and requeue queue
		self::redis()->del($requeueQueue);
		self::redis()->del($tempQueue);

		return $counter;
	}

	/**
	 * matching item
	 * item can be ['class'] or ['class' => 'id'] or ['class' => {:foo => 1, :bar => 2}]
	 * @private
	 *
	 * @params string $string redis result in json
	 * @params $items
	 *
	 * @return (bool)
	 */
	private static function matchItem($string, $items)
	{
	    $decoded = json_decode($string, true);

	    foreach($items as $key => $val) {
		# class name only  ex: item[0] = ['class']
		if (is_numeric($key)) {
		    if($decoded['class'] == $val) {
			return true;
		    }
		# class name with args , example: item[0] = ['class' => {'foo' => 1, 'bar' => 2}]
    		} elseif (is_array($val)) {
		    $decodedArgs = (array)$decoded['args'][0];
		    if ($decoded['class'] == $key &&
			count($decodedArgs) > 0 && count(array_diff($decodedArgs, $val)) == 0) {
			return true;
			}
		# class name with ID, example: item[0] = ['class' => 'id']
		} else {
		    if ($decoded['class'] == $key && $decoded['id'] == $val) {
			return true;
		    }
		}
	    }
	    return false;
	}

	/**
	 * Remove List
	 *
	 * @private
	 *
	 * @params string $queue the name of the queue
	 * @return integer number of deleted items belongs to this list
	 */
	private static function removeList($queue)
	{
	    $counter = self::size($queue);
	    $result = self::redis()->del('queue:' . $queue);
	    return ($result == 1) ? $counter : 0;
	}

	/*
	 * Generate an identifier to attach to a job for status tracking.
	 *
	 * @return string
	 */
	public static function generateJobId()
	{
		return md5(uniqid('', true));
	}
}
