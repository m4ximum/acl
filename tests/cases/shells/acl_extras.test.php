<?php
/**
 * Acl Extras Shell.
 * 
 * Enhances the existing Acl Shell with a few handy functions
 *
 * Copyright 2008, Mark Story.
 * 694B The Queensway 
 * toronto, ontario M8Y 1K9
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2008-2009, Mark Story.
 * @link http://mark-story.com
 * @version 0.5.1
 * @author Mark Story <mark@mark-story.com>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

App::import('Core', 'Shell');
App::import('Model', 'DbAcl');
App::import('Core', 'Controller');

if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}

$pluginPaths = Configure::read('pluginPaths');
foreach ($pluginPaths as $path) {
	$file = $path . 'acl_extras' . DS . 'vendors' . DS . 'shells' . DS . 'acl_extras.php';
	if (file_exists($file)) {
		require($file);
	}
}

if (!class_exists('AclExtrasShell')) {
	die('Could not load AclExtras Shell Quitting');
}

Mock::generatePartial(
	'ShellDispatcher', 'AclExtrasMockShellDispatcher',
	array('getInput', 'stdout', 'stderr', '_stop', '_initEnvironment')
);

Mock::generatePartial(
	'AclExtrasShell', 'MockAclExtrasShell',
	array('in', 'hr', 'out', 'err', 'createFile', '_stop', 'getControllerList')
);

Mock::generate('Aco', 'MockAco', array('children', 'verify', 'recover'));

//import test controller class names.
include dirname(dirname(dirname(__FILE__))) . DS . 'test_controllers.php';

/**
 * AclExtras Shell Test case
 *
 * @package acl_extras.tests.cases
 **/
class AclExtrasShellTestCase extends CakeTestCase {

	var $fixtures = array('core.aco', 'core.aro', 'core.aros_aco');

/**
 * start case, change the acl db config
 *
 * @return void
 **/
	function startCase() {
		Configure::write('Acl.classname', 'DbAcl');
		Configure::write('Acl.database', 'test_suite');
	}

/**
 * startTest
 *
 * @return void
 * @access public
 */
	function startTest() {
		$this->Dispatcher =& new AclExtrasMockShellDispatcher();
		$this->Task =& new MockAclExtrasShell($this->Dispatcher);
		$this->Task->Dispatch =& $this->Dispatcher;
		$this->Task->Dispatch->shellPaths = Configure::read('shellPaths');
	}

/**
 * end the test
 *
 * @return void
 **/
	function endTest() {
		unset($this->Task, $this->Dispatcher);
		ClassRegistry::flush();
	}

/**
 * test recover
 *
 * @return void
 **/
	function testRecover() {
		$this->Task->startup();
		$this->Task->args = array('Aco');
		$this->Task->Acl->Aco = new MockAco();

		$this->Task->Acl->Aco->expectOnce('recover');
		$this->Task->expectAt(0, 'in', array(new PatternExpectation('/recovered/')));
		$this->Task->recover();
	}

/**
 * test verify
 *
 * @return void
 **/
	function testVerify() {
		$this->Task->startup();
		$this->Task->args = array('Aco');
		$this->Task->Acl->Aco = new MockAco();

		$this->Task->Acl->Aco->expectOnce('verify');
		$this->Task->expectAt(0, 'in', array(new PatternExpectation('/valid/')));
		$this->Task->verify();
	}

/**
 * test startup
 *
 * @return void
 **/
	function testStartup() {
		$this->assertEqual($this->Task->Acl, null);
		$this->Task->startup();
		$this->assertTrue(is_a($this->Task->Acl, 'AclComponent'));
	}

/**
 * Test aco_update method.
 *
 * @return void
 **/
	function testAcoUpdate() {
		$tableName = $this->db->fullTableName('acos');
		$this->db->execute('TRUNCATE ' . $tableName);
		$this->Task->setReturnValue('getControllerList', array('Comments', 'Posts', 'BigLongNames'));
		$this->Task->startup();
		$this->Task->aco_update();

		$Aco = $this->Task->Acl->Aco;

		$result = $Aco->node('controllers/Comments');
		$this->assertEqual($result[0]['Aco']['alias'], 'Comments');

		$result = $Aco->children($result[0]['Aco']['id']);
		$this->assertEqual(count($result), 3);
		$this->assertEqual($result[0]['Aco']['alias'], 'add');
		$this->assertEqual($result[1]['Aco']['alias'], 'index');
		$this->assertEqual($result[2]['Aco']['alias'], 'delete');

		$result = $Aco->node('controllers/Posts');
		$this->assertEqual($result[0]['Aco']['alias'], 'Posts');
		$result = $Aco->children($result[0]['Aco']['id']);
		$this->assertEqual(count($result), 3);

		$result = $Aco->node('controllers/BigLongNames');
		$this->assertEqual($result[0]['Aco']['alias'], 'BigLongNames');
		$result = $Aco->children($result[0]['Aco']['id']);
		$this->assertEqual(count($result), 4);
	}

}
