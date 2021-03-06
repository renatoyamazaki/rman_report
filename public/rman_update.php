<?php
	// No browser cache
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	require_once "config.php";
	require_once $APP_ROOT . "/include/class/connection.php";
	require_once $APP_ROOT . "/include/class/database.php";
	require_once $APP_ROOT . "/include/class/rmaninfo.php";

	if ( isset($_GET['dbid']) ) {

		$dbid = $_GET['dbid'];
	
		$databases = new dbSet();
		$infos = new dbInfo();


		$catalogo = new conn();
		$SQL = <<<EOT
SELECT a.hostname,
       a.instance,
       a.dbid,
       a.application,
       a.env,
       to_char(a.last_check, 'DD/MM/YYYY HH24:MI:SS') AS last_check
FROM ora_instance a
WHERE a.dbid = :dbid
EOT;
		$stmt = oci_parse($catalogo->dbconn, $SQL);
		oci_bind_by_name($stmt, ":dbid", $dbid);
		oci_execute($stmt, OCI_DEFAULT);

		// adiciona cada database encontrada no catalogo no objeto da classe dbSet
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
			$databases->addInstance($row['HOSTNAME'], $row['INSTANCE'], $row['DBID'], $row['APPLICATION'], $row['ENV'], $row['LAST_CHECK'] );


		// realiza conexoes com todas as instancias encontradas no catalogo
		$connections = new connSet($databases);
		// recolhe informacoes novas de backups rman de todas as instancias
		// para isso compara com informações já existentes no catálogo
		$infos->getDbInfoInstances($connections, $catalogo);
		// atualiza o catalogo com todas as informacoes coletadas
		$infos->putDbInfoCatalog($catalogo);

	}

?>
