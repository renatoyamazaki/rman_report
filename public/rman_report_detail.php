<?php
	// No browser cache
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	require_once "config.php";
	require_once $APP_ROOT . "/include/class/connection.php";
	require_once $APP_ROOT . "/include/class/rmaninfo.php";

	if (isset($_GET['DBID']) and isset($_GET['SESSION'])) {
		if (is_numeric($_GET['DBID']) and is_numeric($_GET['SESSION'])) {
			$DBID = htmlentities($_GET['DBID']);
			$SESSIONRECID = htmlentities($_GET['SESSION']);
		}
	}

	$infos = new dbInfo();
	$catalogo = new conn();

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
</head>

<body>

<div id ="layout">

<?php
        require_once $APP_ROOT . "/include/html/menu.php";
?>

	<div id="main">
		<div class="header">
			<h1>RMAN Backup Report</h1>
		</div>
	
		<div class="content">
<?php


	$infos->printDetail($catalogo, $DBID, $SESSIONRECID);
?>

		</div>
	</div>
</div>

</body>
</html>
