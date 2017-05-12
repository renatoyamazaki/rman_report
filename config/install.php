<?php

	$configFile = "db2.php";

	try {
		if ( !file_exists($configFile) ) {
			throw new Exception('Aruivo não existe. criando');
		}
		
//		$fp = fopen($fileName, "r");
//		if ( !$fp ) {
//			throw new Exception('Erro ao abrir arquivo.');
//		}
		
				
	} catch (Exception $e) {
		echo "Exception occured";
	}
?>
