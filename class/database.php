<?php

/**
	Classe responsavel por informacoes gerais de instancias oracle
 **/
class dbInst {
	
	public $dbid;
	public $hostname;
	public $instance;
	public $application;
	public $env;
	public $last_check;

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

	function upLastCheck ($last_check) {
		$this->last_check = $last_check;
	}
}

/**
	Conjunto de objetos 'dbInst'
 **/
class dbSet {

	// vetor com todos os BDs encontrados no catalogo rman
	public $dbInstArray = array();

	function __construct () {
	}
	
	function __destruct () {
	}

	function addInstance ($hostname, $instance, $dbid, $application, $env, $last_check) {
		$this->dbInstArray[$dbid] = new dbInst($dbid, $hostname, $instance, $application, $env, $last_check);
	}
	
	function upInstanceLC ($dbid, $last_check) {
		$this->dbInstArray[$dbid]->upLastCheck($last_check);
	}

}

?>
