<?php
	require_once "class/connection.php";
	require_once "config/db.php";
	require_once "class/database.php";
	require_once "class/rmaninfo.php";


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
	$stmt = oci_parse($catalogo->dbconn, "select a.hostname, a.instance, a.dbid, a.application, a.env, to_char(a.last_check, 'DD/MM/YYYY HH24:MI:SS') as last_check from ora_instance a order by a.application, a.hostname");
	oci_execute($stmt, OCI_DEFAULT);
	// Adiciona cada database encontrada no catalogo no objeto da classe dbSet
	while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
		$databases->addInstance($row['HOSTNAME'], $row['INSTANCE'], $row['DBID'], $row['APPLICATION'], $row['ENV'], $row['LAST_CHECK'] );
	// Insere informacoes do catalogo no objeto da classe 'dbInfo'
	$infos->getDbInfoCatalog($catalogo, $databases, $dt_rman);

	// Caso seja para relatorio web
	if ($mode == 1) {
?>
<html>

<head>

<title>Oracle - RMAN Backup Report</title>

<!-- CSS -->
<link rel="stylesheet" href="css/jquery.datepick.css" />
<link rel="stylesheet" href="css/common.css" />
<link rel="stylesheet" href="css/pure-min.css" />

<!-- JS -->
<script src="js/jquery-1.11.3.min.js"></script>
<script src="js/jquery.auto-complete.min.js"></script>
<script src="js/jquery.plugin.min.js"></script>
<script src="js/jquery.datepick.min.js"></script>
<script src="js/jquery.datepick-pt-BR.js"></script>
<script src="js/sorttable.js"></script>

<script>
$(function() {	
	// campos de calendario
	$('#cal1').datepick();
});
</script>
	
</head>

<body>

<div id ="layout">

	<div id="main">
		<div class="center">			
			<h1>Rman Backup Report</h1>

			<form class="pure-form pure-form-aligned"  name="" action="rman_report.php" method="post" >
			<fieldset>

			<div class="pure-control-group">
				<label for="laudo">Data</label>
				<input name="dt_rman" type="text" size="8" value="
<?php 
	if ($dt_rman === NULL)
		echo date("d/m/Y"); 
	else
		echo $dt_rman;
?>
" id="cal1">

				<button type="submit" class="pure-button pure-button-primary">Consultar</button>
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
