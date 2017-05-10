<?php

/**
 * Connection Class
 *
 * This class enables connection with the oracle instance.
 * The 'rman_report' application uses one connection with each target instance.
 * If you don't specify parameters in the creation of the object, it will connect with 
 * the application database, that is configured in the 'config/db.php' file. 
 **/
class conn {

	private $serv;
	private $inst;
	private $user = USERNAME;
	private $pass = PASSWORD;
	public $dbconn;

	/**
	 * Class constructor
	 *
	 * Can make 2 types of connection:
	 * - With the instance that have the application objects
	 * - With the target instance, that have all the information to be collected
	 *
	 * @param	string 	$server		Server name
	 * @param	string 	$instance	Instance name	
	 * @return	bool	TRUE if the connection was sucessfull, FALSE otherwise
	 **/
	function __construct ($server = NULL, $instance = NULL) {
		
		// Verify if the server name was passed throught parameters
		if ( $server === NULL ) {	
			$this->serv = SERVER;
			$this->inst = INSTANCE;
		} 
		else {
			$this->serv = $server;
			$this->inst = $instance;
		}
		
		// Construct the tns connection, treat exception like different listening port here
		if ($instance != 'PAR')
			$tns = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->serv.")(PORT = 1521)))(CONNECT_DATA=(SERVICE_NAME=".$this->inst."))) ";
		else
			$tns = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->serv.")(PORT = 1523)))(CONNECT_DATA=(SERVICE_NAME=".$this->inst."))) ";
		
		// Makes the connection, store the resource returned by oci_connect on dbconn
		$this->dbconn = @oci_connect($this->user, $this->pass, $tns);

		// If there was an error at the connection
		if (!$this->dbconn) {
			echo "Erro na conexão com o Oracle, verifique parametros.";
			return FALSE;
                }
		
		return TRUE;
	}

	function __destruct () {
		$this->dbconn = NULL;
	}


}

/**
 * Connection Set Class
 * 
 * This class stores an array of 'Connection Class' objects.
 * The array is indexed by the dbid from the instance, providing fast access to the connection
 * needed.
 */
class connSet {
	
	public $connArray = array();

	/**
	 * Class constructor
	 * 
	 * @param	object	$dbSet		'Database' object
	 * @return	bool	TRUE if all the connections where made sucessfully
	 */
	function __construct ($dbSet) {
		$error_count = 0;

		foreach ($dbSet->dbInstArray as $dia) {
			$c = new conn ($dia->hostname, $dia->instance);
			if (!is_bool($c))
				$this->connArray[$dia->dbid] = $c;
			else
				$error_count++;
		}

		if ($error_count == 0)
			return true;
		else
			return false;
	}

	function __destruct () {
		$this->connArray = NULL;
	}
}


?>
