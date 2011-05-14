<?php
/**
 * DoLater
 *
 * Copyright (c) 2010 Ramon Torres
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 2010 Ramon Torres
 * @package DoLater
 * @license The MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * DoLater Job record
 *
 * @package DoLater
 */
class DoLaterJob extends Rox_ActiveRecord {

	public static function model($class = __CLASS__) {
		return parent::model($class);
	}

	/**
	 * Returns the next available job
	 *
	 * @return DoLaterJob
	 */
	public static function nextJob() {
		$now = date('Y-m-d H:i:s');
		$conditions = array('locked' => false, "`run_at` < '{$now}'");
		return self::model()->findFirst(array('conditions' => $conditions));
	}

	/**
	 * Performs the job
	 *
	 * @return void
	 */
	public function perform() {
		try {
			$this->_perform();
		} catch (Exception $e) {
			return $this->reschedule();
		}

		$this->delete();
	}

	protected function _perform() {
		$handler = $this->unserializedHandler();
		call_user_func_array($handler['callback'], $handler['args']);
	}

	public function acquireLock() {
		$this->updateAttributes(array('locked' => true));
		return $this->datasource()->affectedRows() == 1;
	}

	public function reschedule() {
		$this->updateAttributes(array(
			'locked' => false,
			'run_at' => '+10 minutes'
		));
	}

	public function unserializedHandler() {
		return unserialize($this->handler);
	}

	/**
	 * Make save strict
	 *
	 * @throws DoLaterException
	 * @return void
	 */
	public function save() {
		if (!parent::save()) {
			throw new DoLaterException('Failed to save job');
		}
	}

	/**
	 * Before-save callback
	 *
	 * @return void
	 */
	protected function _beforeSave() {
		if (empty($this->run_at)) {
			$this->run_at = 'now';
		}

		if (in_array('run_at', $this->_modifiedAttributes)) {
			$this->run_at = date('Y-m-d H:i:s', strtotime($this->run_at));
		}

		if (in_array('handler', $this->_modifiedAttributes)) {
			$this->handler = serialize($this->handler);
		}
	}
}

/**
 * Package level exception
 *
 * @package DoLater
 */
class DoLaterException extends Exception {
}

/**
 * DoLater utility class
 *
 * @package DoLater
 */
class DoLater {

	/**
	 * Adds a task to the job queue
	 *
	 * @param callback|object $task
	 * @return void
	 */
	public static function enqueue($task, $options = array()) {
		$defaults = array('run_at' => 'now', 'args' => array());
		$options = array_merge($defaults, $options);

		if (is_object($task)) {
			$task = array($task, 'perform');
		}

		if (!is_callable($task)) {
			throw new DoLaterException('Task is not callable');
		}

		$handler = array(
			'callback' => $task,
			'args' => (array)$options['args']
		);

		$job = new DoLaterJob(array(
			'handler' => $handler,
			'run_at'  => $options['run_at'],
		));

		$job->save();
	}

	/**
	 * Processes the job queue
	 *
	 * @return void
	 */
	public static function processQueue() {
		while ($job = DoLaterJob::nextJob()) {
			if ($job->acquireLock()) {
				$job->perform();
			}
		}
	}
}
