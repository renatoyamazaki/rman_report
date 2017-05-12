<?php
	// No browser cache
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	require_once "class/connection.php";
	require_once "config/db.php";
	require_once "class/rmaninfo.php";

	if (isset($_GET['DBID']) and isset($_GET['SESSION'])) {
		if (is_numeric($_GET['DBID']) and is_numeric($_GET['SESSION'])) {
			$DBID = htmlentities($_GET['DBID']);
			$SESSIONRECID = htmlentities($_GET['SESSION']);
		}
	}

?>
<html>

<head>

<title>Oracle - RMAN Backup Report</title>

<!-- CSS -->
<link rel="stylesheet" href="css/jquery.datepick.css" />
<link rel="stylesheet" href="css/common.css" />
<link rel="stylesheet" href="css/pure-min.css" />

</head>

<body>

<div id ="layout">

	<div id="main">
		<div class="center">
			<h1>RMAN Backup Report</h1>
		</div>
	
		<div class="content">
<?php

	$infos = new dbInfo();
	$catalogo = new conn();
	$infos->printDetail($catalogo, $DBID, $SESSIONRECID);
?>

		</div>
	</div>
</div>

</body>
</html>
