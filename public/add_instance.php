<?php
	// No browser cache
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	require_once "config.php";
	require_once $APP_ROOT . "/include/class/connection.php";

	/**
	 * Returns the dbid from the instance
	 *
	 * @param	$conn	object	Connection
	 * @return	$dbid	integer	DBID from the instance
	 */
	function get_dbid ($conn) {

		$sql = "select dbid from v\$database";
		$stmt = oci_parse($conn->dbconn, $sql);
		oci_execute($stmt, OCI_DEFAULT);
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
			$dbid = $row['DBID'];
		return $dbid;
	}

	/**
	 * Verify if the instance is already registered in the ora_instance table
	 *
	 * @param	$conn	object	Connection
	 * @param	$dbid	integer DBID from the instance
	 * @return	bool	TRUE if the instance is already registered, FALSE otherwise	
	 */
	function verify_registered_dbid ($conn, $dbid) {
	
		$sql = "select count(*) as count from ora_instance where dbid = :dbid";
		$stmt = oci_parse($conn->dbconn, $sql);
		oci_bind_by_name($stmt, ":dbid", $dbid);
		oci_execute($stmt, OCI_DEFAULT);
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
			$count = $row['COUNT'];
		if ($count === "0")
			return false;
		else
			return true;
	}
	

	/**
	 * Register the instance
	 *
	 * @param	$conn		object	Connection
	 * @param	$dbid		integer	DBID from the instance
	 * @param	$server		string	Server name
	 * @param	$instance	string	Instance name
	 * @param	$application	string	Short description of the instance
	 * @param	$env		string	Environment (production, development, ...)
	 * @return	bool		TRUE if registered with sucess, FALSE otherwise
	 */
	function register_instance ($conn, $dbid, $server, $instance, $application, $env) {

		// Insert into ora_instance table
		$sql = "insert into ora_instance (dbid, hostname, instance, application, env, active) values (:dbid, :server, :instance, :application, :env, 1)";
		$stmt = oci_parse($conn->dbconn, $sql);
		oci_bind_by_name($stmt, ":dbid", $dbid);
		oci_bind_by_name($stmt, ":server", $server);
		oci_bind_by_name($stmt, ":instance", $instance);
		oci_bind_by_name($stmt, ":application", $application);
		oci_bind_by_name($stmt, ":env", $env);
		oci_execute($stmt, OCI_DEFAULT);
		// Commit	
		$r = oci_commit($conn->dbconn);
		if (!$r) {    
 			$e = oci_error($stmt);
			oci_rollback($conn->dbconn);  // rollback changes
			trigger_error(htmlentities($e['message']), E_USER_ERROR);
			return false;
		}
		else 
			return true;
	}

	/**
	 * Collects RMAN logs from the instance
	 * Redirects the browser to the update url of the application, 
	 * updating the info from the instance referenced by the dbid.
	 *
	 * @param	$dbid	integer		DBID from the instance
	 * @return 	void
	 */
	function collect_logs ($dbid) {
		echo "Coletando logs do rman na instância cadastrada";		
		header("Refresh:0; url=../rman_update.php?dbid=$dbid&r=".mt_rand(0, 9999999));
	}


	// Parameters (POST)

	// Verify the required fields
	if ( isset($_POST['server']) && isset($_POST['instance']) ) {
		$server = strtoupper($_POST['server']);
		$instance = strtoupper($_POST['instance']);
		// Optional fields
		if ( isset($_POST['application']) )
			$application = strtoupper($_POST['application']);
		if ( isset($_POST['env']) )
			$env = strtoupper($_POST['env']);

		// Validate the connection with the instance
		try {
			$connTarget = new conn($server, $instance);
			$dbid = get_dbid ($connTarget);
			// Connection with the catalog
			try {
				$catalog = new conn();
				// Verify if its already registered
				if (! verify_registered_dbid($catalog, $dbid)) {
					if (register_instance($catalog, $dbid, $server, $instance, $application, $env))
						collect_logs($dbid);
				}
				else
					echo "Instância já foi registrada. <br/>";
			} catch (Exception $e) {
				echo $e->getMessage();
				echo "Conexão com o catálogo falhou. <br/>";
			}
		} catch (Exception $e) {
			echo $e->getMessage() . "<br/>";
			echo "Crie o usuário " . USERNAME . " no servidor " . $server . ", instancia " . $instance . "<br/>";
			echo "SQL> create user " . USERNAME . " identified by ..... <br/>";
			echo "SQL> grant connect, select_catalog_role to " . USERNAME . "; <br/>";
		}
	}
	else {

?>

<!doctype html>
<html>

<head>
<title>DB Inventory</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
	 require_once $APP_ROOT . "/include/html/header.php";
?>
</head>


<body>


<div id="layout">

<?php
        require_once $APP_ROOT . "/include/html/menu.php";
?>

        <div id="main">
                <div class="header">
                        <h1>DB Inventory</h1>
                        <h2>Add Oracle Instance</h2>
                </div>

                <div class="content">
                        <form class="pure-form pure-form-aligned"  name="cadastro" action="add_instance.php" method="post" >
                        <fieldset>

                                <div class="pure-control-group">
                                        <label for="server">Servidor</label>
                                        <input name="server" type="text" required>
                                </div>

                                <div class="pure-control-group">
                                        <label for="instance">Instância</label>
                                        <input name="instance" type="text" required>
                                </div>

                                <div class="pure-control-group">
                                        <label for="application">Aplicação</label>
                                        <input name="application" type="text" required>
                                </div>

                                <div class="pure-control-group">
                                        <label for="env">Ambiente</label>
			                <select name="env" class="pure-input-1-8" required>
						<option></option>
						<option>DEV</option>
						<option>QAS</option>
						<option>PRD</option>
					</select>
                                </div>

                                <div class="pure-controls">
                                        <button type="submit" class="pure-button pure-button-primary">Cadastrar</button>
                                </div>

                        </fieldset>
                        </form>
                </div>

        </div>
</div>


</body>

</html>
<?php

}

?>

