<?php
/**
 * CreateDoLaterJobsTable migration
 *
 * @package app.migrations
 */
class CreateDoLaterJobsTable extends Rox_ActiveRecord_Migration {

	public function up() {
		$t = $this->createTable('do_later_jobs');
		$t->text('handler');
		$t->string('type');
		$t->datetime('run_at');
		$t->boolean('locked', array('default' => 0));
		$t->timestamps();
		$t->finish();
	}

	public function down() {
		$this->dropTable('do_later_jobs');
	}
}
