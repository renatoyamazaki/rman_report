<?php
	// No browser cache
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	require_once "config.php";
	require_once $APP_ROOT . "/include/class/connection.php";
	require_once $APP_ROOT . "/include/class/database.php";
	require_once $APP_ROOT . "/include/class/rmaninfo.php";


	// PARAMETROS /////////////////////////////////////////////////////////
	//
	// dt_rman (data)
	// mode (web / email)
	if ( isset($_POST['dt_rman']) ) 
		$dt_rman = $_POST['dt_rman'];	
	else
		$dt_rman = NULL;

	if ( isset($_GET['mode']) && ($_GET['mode'] == 'email') ) {
		$mode = 0;
		$dt_rman = date("d/m/Y", time()-86400);
	}
	else
		$mode = 1;
	// FIM PARAMETROS /////////////////////////////////////////////////////


	// Cria 2 objetos para armazenar e interar as informacoes do rman
	$databases = new dbSet();
	$infos = new dbInfo();

	// Conecta na base de catalogo, retirando as informacoes que sao pertinentes
	$catalogo = new conn();
//	$SQL = "select a.hostname, a.instance, a.dbid, a.application, a.env, to_char(a.last_check, 'DD/MM/YYYY HH24:MI:SS') as last_check from ora_instance a where active <> 0 order by a.application, a.hostname";
$SQL = <<<EOT
select a.hostname, a.instance, a.dbid, a.application, a.env, to_char(a.last_check, 'DD/MM/YYYY HH24:MI:SS') as last_check from ora_instance a where active <> 0 order by a.application, a.hostname
EOT;
	$stmt = oci_parse($catalogo->dbconn, $SQL);
	oci_execute($stmt, OCI_DEFAULT);
	// Adiciona cada database encontrada no catalogo no objeto da classe dbSet
	while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
		$databases->addInstance($row['HOSTNAME'], $row['INSTANCE'], $row['DBID'], $row['APPLICATION'], $row['ENV'], $row['LAST_CHECK'] );
	// Insere informacoes do catalogo no objeto da classe 'dbInfo'
	$infos->getDbInfoCatalog($catalogo, $databases, $dt_rman);

	// Caso seja para relatorio web
	if ($mode == 1) {
?>
<!doctype html>
<html>

<head>
<title>Oracle - RMAN Backup Report</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
	 require_once $APP_ROOT . "/include/html/header.php";
?>

<script>
$(function() {	
	// campos de calendario
	$('#cal1').datepick();
});

</script>
</head>

<body>

<div id ="layout">

<?php
        require_once $APP_ROOT . "/include/html/menu.php";
?>

	<div id="main">
		<div class="header">			
			<h1>Rman Backup Report</h1>

			<form class="pure-form pure-form-aligned"  name="" action="rman_report.php" method="post" >
			<fieldset>

			<div class="pure-control-group">
				<input name="dt_rman" type="text" size="8" value="
<?php 
	if ($dt_rman === NULL)
		echo date("d/m/Y"); 
	else
		echo $dt_rman;
?>
" id="cal1">

				<button type="submit" class="pure-button pure-button-primary">Go</button>
			</div>
			<fieldset>
			</form>
		</div>

		<div class="content">
<?php
	}

	// Imprime a tabela com todas as informacoes coletadas
	$infos->printReport($databases, $catalogo, $mode);

	// Caso seja para relatorio web
	if ($mode == 1) {
?>
		</div>
	</div>
</div>
</body>
</html>
<?php
	}
?>
