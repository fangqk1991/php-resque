<?php

namespace FC\Resque;

class ResqueLogger
{
	public $verbose;

	public function __construct($verbose = false) {
		$this->verbose = $verbose;
	}

	public function log($level, $message, array $context = array())
	{
		if ($this->verbose) {
			fwrite(
				STDOUT,
				'[' . $level . '] [' . strftime('%T %Y-%m-%d') . '] ' . $this->interpolate($message, $context) . PHP_EOL
			);
			return;
		}

		if (!($level === LogLevel::INFO || $level === LogLevel::DEBUG)) {
			fwrite(
				STDOUT,
				'[' . $level . '] ' . $this->interpolate($message, $context) . PHP_EOL
			);
		}
	}

	public function interpolate($message, array $context = array())
	{
		// build a replacement array with braces around the context keys
		$replace = array();
		foreach ($context as $key => $val) {
			$replace['{' . $key . '}'] = $val;
		}
	
		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}
}
