<?php

/**
 * Database Class
 * 
 * This class stores information of an oracle instance.
 *
 */
class dbInst {
	
	public $dbid;
	public $hostname;
	public $instance;
	public $application;
	public $env;
	public $last_check;

	/**
	 * Class constructor
	 *
	 * @param	string	$dbid		DBID of the instance
	 * @param	string	$hostname	Server name
	 * @param	string	$instance	Instance name
	 * @param	string	$application	Short description of the instance
	 * @param	string	$env		Environment (production, development, ...)
	 * @param	string	$last_check	Last check of the 'rman_report' application
	 * @return	void
	 */
	function __construct ($dbid, $hostname, $instance, $application, $env, $last_check) {
		$this->dbid = $dbid;
		$this->hostname = $hostname;
		$this->instance = $instance;
		$this->application = $application;
		$this->env = $env;
		$this->last_check = $last_check;
	}

	function __destruct () {
	}

	/**
	 * Update last_check
	 *
	 * @param	string	$last_check	Last check of the 'rman_report' application
	 * @return	void
	 */
	function upLastCheck ($last_check) {
		$this->last_check = $last_check;
	}
}

/**
 * Database Set Class
 *
 * This class stores an array of 'Database Class' objects.
 * The array is indexed by the dbid from the instance.
 *
 */
class dbSet {

	public $dbInstArray = array();

	/**
	 * Add a instance in the array of 'Database' objects
	 *
	 * @param	string	$hostname	Server name	
	 * @param	string	$instance	Instance name
	 * @param	string	$dbid		DBID of the instance
	 * @param	string	$application	Short description of the instance
	 * @param	string	$env		Environment (production, development, ...)
	 * @param	string	$last_check	Last check of the 'rman_report' application
	 * @return	void
	 */
	function addInstance ($hostname, $instance, $dbid, $application, $env, $last_check) {
		$this->dbInstArray[$dbid] = new dbInst($dbid, $hostname, $instance, $application, $env, $last_check);
	}
	
	/**
	 * Update last_check of an instance
	 *
	 * @param	string	$dbid		DBID of the instance
	 * @param	string	$last_check	Last check of the 'rman_report' application
	 * @return	void
	 */
	function upInstanceLC ($dbid, $last_check) {
		$this->dbInstArray[$dbid]->upLastCheck($last_check);
	}

}

?>
