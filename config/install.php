<?php

	$configFile = "db2.php";

	if ( !file_exists($configFile) ) {

		try {
			$fp = fopen($configFile, "a");
			if ( !$fp ) {
				throw new Exception('Erro ao criar arquivo.');
			}
			else {
				echo "arquivo criado";

			}
		} catch (Exception $e) {
			echo "Exception occured";
		}
	}
?>
