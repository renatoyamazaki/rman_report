<?php
	// No browser cache
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	require_once "config.php";
	require_once $APP_ROOT . "/include/class/connection.php";

	// Configuration file
	$configFile = $WEB_ROOT . "/db.php";



	/**
	 * Verify if the application objects are already created
	 *
	 * @param	$conn	object	Connection
	 * @param	$user	string	DB Username		
	 * @return	bool	TRUE if there are objets of the user,
	 * FALSE otherwise
	 */
	function verify_objects ($conn, $username) {
		$sql = "select count(*) as count from all_objects where owner = upper(:username)";
		$stmt = oci_parse($conn->dbconn, $sql);
		oci_bind_by_name($stmt, ":username", $username);
		oci_execute($stmt, OCI_DEFAULT);
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
			$count = $row['COUNT'];
		if ($count === "0")
			return false;
		else
			return true;
	}

	/**
	 * Create database objects of rman_report application
	 *
	 * @param	$conn	object Connection
	 * @return	bool	TRUE if sucess, FALSE otherwise
	 */
	function create_objects ($conn) {

		$sql = "create table ora_instance ( \"DBID\" NUMBER NOT NULL ENABLE, \"HOSTNAME\" VARCHAR2(50) NOT NULL ENABLE, \"INSTANCE\" VARCHAR2(50) NOT NULL ENABLE, \"APPLICATION\" VARCHAR2(20), \"ENV\" VARCHAR2(3), \"LAST_CHECK\" DATE, \"ACTIVE\" NUMBER(1,0) DEFAULT 1)";
		$stmt = oci_parse($conn->dbconn, $sql);
		oci_execute($stmt, OCI_DEFAULT);

		$sql = "create table rman_log (	\"DBID\" NUMBER, \"SESSION_RECID\" NUMBER, \"STATUS\" VARCHAR2(23), \"START_TIME\" DATE, \"COMMAND_ID\" VARCHAR2(33), \"LOG\" CLOB, \"END_TIME\" DATE, \"LOG_ERROR\" CLOB, PRIMARY KEY (\"DBID\", \"SESSION_RECID\", \"START_TIME\") USING INDEX)";
		$stmt = oci_parse($conn->dbconn, $sql);
		oci_execute($stmt, OCI_DEFAULT);
	
	}


	// Parameters (POST)

	// Verify the required fields
	if ( isset($_POST['server']) && isset($_POST['instance']) ) {
		$server = $_POST['server'];
		$instance = $_POST['instance'];
		$username = $_POST['username'];
		$password = $_POST['password'];
	

		// Validate the connection with the instance
		try {
			$conn = new conn($server, $instance, $username, $password);

			// Create configuration file with database connection info
			if ( !file_exists($configFile) ) {
				try {
					$fp = @fopen($configFile, "a");
					if ( !$fp ) {
						throw new Exception('Erro ao criar arquivo, verifique as permissões do diretório "config".');
					}
					else {
						fwrite($fp, "<?php\n");
						fwrite($fp, "define ('SERVER', '$server');\n");
						fwrite($fp, "define ('INSTANCE', '$instance');\n");
						fwrite($fp, "define ('USERNAME', '$username');\n");
						fwrite($fp, "define ('PASSWORD', '$password');\n");
						fwrite($fp, "?>\n");
						fclose($fp);
					}
				} catch (Exception $e) {
					echo $e->getMessage();
				}
				// Verify if db objects are already created, create otherwise
				if ( verify_objects ($conn, $username) ) {
					echo "Objetos de banco já existem.";
				} else {
					create_objects($conn);					
				}
			} else {
				echo "Arquivo de configuração já existe.";
			}
		} catch (Exception $e) {
			echo $e->getMessage() . "<br/>";
		}
	}
	else {
		
		if ( file_exists($configFile) ) {
			echo "Arquivo de configuração já existe";
		}
		else {

?>
<!doctype html>
<html>

<head>
<title>RMAN Report</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- CSS -->
<link rel="stylesheet" href="../css/pure-min.css" />
<link rel="stylesheet" href="../css/side-menu.css" />
</head>


<body>


<div id="layout">

        <div id="main">
                <div class="header">
                        <h1>RMAN Report</h1>
                        <h2>Install</h2>
                </div>

                <div class="content">
                        <form class="pure-form pure-form-aligned"  name="cadastro" action="install.php" method="post" >
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
                                        <label for="username">Usuário</label>
                                        <input name="username" type="text" required>
                                </div>

                                <div class="pure-control-group">
					<label for="password">Senha</label>
                                        <input name="password" type="password" required>
                                </div>

                                <div class="pure-controls">
                                        <button type="submit" class="pure-button pure-button-primary">Configurar</button>
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
	}
?>
