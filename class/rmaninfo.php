<?php

/**
	Classe que contem as informaçoes basicas dos backups rman
 **/
class rmanInfo {
	
	public $sessionRecid;
	public $status;
	public $timeStart;
	public $timeEnd;
	public $operation;
	public $log;
	public $log_error;
	
	function __construct ($sessionRecid, $status, $timeStart, $timeEnd, $operation) {

		$this->sessionRecid = $sessionRecid;
		$this->status = $status;
		$this->timeStart = $timeStart;
		$this->timeEnd = $timeEnd;
		$this->operation = $operation;
		$this->log = " ";
		$this->log_error = " ";

	}

	function __destruct () {
	}

	function upInfo ($status, $timeStart, $timeEnd, $operation) {

		// Status final, não fazer atualizacao	
		if ( ($this->status === 'COMPLETED WITH ERRORS') || ($this->status === 'COMPLETED WITH WARNINGS') ) {
			///
		} 
		else {
			// Se vier atualizacao de completed ou running
			if ( (strstr($status, 'COMPLETED')) && (!strstr($this->status, 'RUNNING')) )
				$this->status = $status;
			else {
				if (strstr($status,'RUNNING'))
					$this->status = $status;
			}
		}
		if ($timeStart < $this->timeStart)
			$this->timeStart = $timeStart;
		if ($timeEnd > $this->timeEnd)
			$this->timeEnd = $timeEnd;
		if (strstr($operation, 'level'))
			$this->operation = $operation;
		
	}
	
	function upLog ($log, $log_error) {
		$this->log = $log;
		$this->log_error = $log_error;
	}

}

/**
	Possui um vetor com varios objetos de rman 'rmanInfo'
 **/
class rmanInfoSet {

	public $rmanInfoArray = array();
	public $countObj;

	function __construct () {
	}
	
	function __destruct () {
		$rmanInfoArray = NULL;
	}

	function addRmanInfo ($sessionRecid, $status, $timeStart, $timeEnd, $operation) {
		if (array_key_exists($sessionRecid, $this->rmanInfoArray))
			$this->rmanInfoArray[$sessionRecid]->upInfo($status, $timeStart, $timeEnd, $operation);
		else
			$this->rmanInfoArray[$sessionRecid] = new rmanInfo($sessionRecid, $status, $timeStart, $timeEnd, $operation);
	}

	function upLog ($sessionRecid, $log, $log_error) {
		$this->rmanInfoArray[$sessionRecid]->upLog($log, $log_error);
	}
	
	function countObj () {
		$this->countObj = count($this->rmanInfoArray);
	}

	function setCount ($count) {
		$this->countObj = $count;
	}

}

/**
	Possui um vetor com varios objetos de conexao 'rmanInfoSet'
 **/
class dbInfo {

	public $rmanInfoSetArray = array();
	public $countObjMax;

	function __construct () {
	}

	function __destruct () {
	}
	
	// Retira informações de cada um dos bancos e coloca no objeto dbInfo
	function getDbInfoInstances ($connections, $catalog) {

		foreach ($connections->connArray as $dbid => $connection) {

			// checa se a conexão é válida
			if (!is_bool($connection->dbconn)) {
			
				// Verifica as informações mais recentes no catálogo
				// referentes a instância de interesse
				$SQL = "select session_recid, to_char(start_time, 'DD/MM/YYYY HH24:MI:SS') as timestart from rman_log where start_time = (select max(start_time) from rman_log where dbid = $dbid and status like 'COMPLETED%') and dbid = $dbid and status like 'COMPLETED%'";
				$stmt = oci_parse($catalog->dbconn, $SQL);
				oci_execute($stmt, OCI_DEFAULT);
				// Coloca na variável o último timestamp de backup com sucesso
				$row = oci_fetch_array($stmt, OCI_BOTH);
				$last_backup = $row['TIMESTART'];
				if ($last_backup === NULL)
					$last_backup = '01/01/2001 00:00:00';
				$last_recid = $row['SESSION_RECID'];
				if ($last_recid === NULL)	// ?????
					$last_recid = '0';
				

				// Utiliza a conexão com a instância que será feita a coleta de informações
				$this->rmanInfoSetArray[$dbid] = new rmanInfoSet();	
				// ID 375386.1
				$stmt = oci_parse($connection->dbconn, "alter session set optimizer_mode=RULE");
				oci_execute($stmt, OCI_DEFAULT);
				$SQL = "select session_recid, status, to_char(min(start_time), 'DD/MM/YYYY HH24:MI:SS') as timestart, to_char(max(end_time), 'DD/MM/YYYY HH24:MI:SS') as timeend, command_id, max(end_time) as time from v\$rman_status where session_recid <> $last_recid and start_time > to_date('$last_backup', 'DD/MM/YYYY HH24:MI:SS') group by session_recid, status, command_id order by 6 desc";
				$stmt = oci_parse($connection->dbconn, $SQL);
				oci_execute($stmt, OCI_DEFAULT);
				// Coloca informações coletadas no objeto
				while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false)
					$this->rmanInfoSetArray[$dbid]->addRmanInfo($row['SESSION_RECID'], $row['STATUS'], $row['TIMESTART'], $row['TIMEEND'], $row['COMMAND_ID']);
				// realiza uma contagem de quantos objetos o vetor possui
				$this->rmanInfoSetArray[$dbid]->countObj();

				if ($this->rmanInfoSetArray[$dbid]->countObj != 0) {
					// Coleta informações de log, iterando sobre cada objeto rmanInfo
					foreach ($this->rmanInfoSetArray[$dbid]->rmanInfoArray as $ri) {
							$SQL = "select output from v\$rman_output where session_recid = $ri->sessionRecid";
							$stmt = oci_parse($connection->dbconn, $SQL);
							oci_execute($stmt, OCI_DEFAULT);
							//zera a variavel output
							$output = "";
							$output_error = "";
							while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) {
								if ((strpos($row['OUTPUT'], "RMAN-") !== FALSE ) or (strpos($row['OUTPUT'], "ORA-") !== FALSE) or (strpos($row['OUTPUT'], "ANS") !== FALSE ))
									$output_error .= $row['OUTPUT'] . "\n";
								$output .= $row['OUTPUT'] . "\n";
							}
							$this->rmanInfoSetArray[$dbid]->upLog($ri->sessionRecid, $output, $output_error);

					}
				}

				// coloca na tabela a ultima atualizacao de checagem de dados novos
				$SQL = "update ora_instance set LAST_CHECK = sysdate where dbid = $dbid";
				$stmt = oci_parse($catalog->dbconn, $SQL);
				oci_execute($stmt, OCI_DEFAULT);				
			}
		}
	
		// COMMIT
		$stmt = oci_parse($catalog->dbconn, "commit");
		oci_execute($stmt, OCI_DEFAULT);

	}
	
	// Insere as informações do objeto dbInfo no catálogo
	function putDbInfoCatalog ($catalog) {
	
		foreach ($this->rmanInfoSetArray as $dbid => $risa) {
					
			foreach ($risa->rmanInfoArray as $ri) {
			
				$SQL = "select count(*) as count from rman_log where dbid = $dbid and session_recid = $ri->sessionRecid and start_time = to_date('$ri->timeStart', 'DD/MM/YYYY HH24:MI:SS')";
				$stmt = oci_parse($catalog->dbconn, $SQL);
				oci_execute($stmt, OCI_DEFAULT);
				
				// INSERE, SE NAO EXISTE REGISTRO (PK)
				$row = oci_fetch_array($stmt, OCI_BOTH);
				if ($row['COUNT'] == 0) {
					$SQL = "insert into rman_log (DBID, SESSION_RECID, STATUS, START_TIME, END_TIME, COMMAND_ID, LOG, LOG_ERROR) values ($dbid, $ri->sessionRecid, '$ri->status', to_date('$ri->timeStart', 'DD/MM/YYYY HH24:MI:SS'), to_date('$ri->timeEnd', 'DD/MM/YYYY HH24:MI:SS'), '$ri->operation', :log, :log_error)";
					$stmt = oci_parse($catalog->dbconn, $SQL);
					oci_bind_by_name($stmt, ":log", $ri->log);
					oci_bind_by_name($stmt, ":log_error", $ri->log_error);
					oci_execute($stmt, OCI_DEFAULT);
				}
				else {
					$SQL = "update rman_log set STATUS = '$ri->status', COMMAND_ID = '$ri->operation' , END_TIME = to_date('$ri->timeEnd', 'DD/MM/YYYY HH24:MI:SS'), LOG = :log, LOG_ERROR = :log_error where DBID = $dbid and SESSION_RECID = $ri->sessionRecid and START_TIME = to_date('$ri->timeStart', 'DD/MM/YYYY HH24:MI:SS')";
					$stmt = oci_parse($catalog->dbconn, $SQL);
					oci_bind_by_name($stmt, ":log", $ri->log);
					oci_bind_by_name($stmt, ":log_error", $ri->log_error);
					oci_execute($stmt, OCI_DEFAULT);
				}
			}
		}
		// COMMIT
		$stmt = oci_parse($catalog->dbconn, "commit");
		oci_execute($stmt, OCI_DEFAULT);
	}

	// Retira as informações guardadas no catálogo e coloca no objeto dbInfo
	function getDbInfoCatalog ($catalog, $databases, $userDate=NULL) {

		if ($userDate === NULL)
			$userDate = date("d/m/Y H:i:s", time()-86400);

		$stmt = oci_parse($catalog->dbconn, "select DBID, SESSION_RECID, STATUS, to_char(START_TIME, 'DD/MM/YYYY HH24:MI:SS') as TIMESTART, to_char(END_TIME, 'DD/MM/YYYY HH24:MI:SS') as TIMEEND, COMMAND_ID, END_TIME from rman_log where START_TIME between to_date('$userDate', 'DD/MM/YYYY HH24:MI:SS') and to_date('$userDate', 'DD/MM/YYYY HH24:MI:SS')+1 order by 7 desc");
		oci_execute($stmt, OCI_DEFAULT);
		
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) {
			$dbid = $row['DBID'];
			if (array_key_exists($dbid, $this->rmanInfoSetArray))
				$this->rmanInfoSetArray[$dbid]->addRmanInfo($row['SESSION_RECID'], $row['STATUS'], $row['TIMESTART'], $row['TIMEEND'], $row['COMMAND_ID']);
			else {
				$this->rmanInfoSetArray[$dbid] = new rmanInfoSet();
				$this->rmanInfoSetArray[$dbid]->addRmanInfo($row['SESSION_RECID'], $row['STATUS'], $row['TIMESTART'], $row['TIMEEND'], $row['COMMAND_ID']);
			}
			$this->rmanInfoSetArray[$dbid]->countObj();
		}

		$this->countMax();


		// ultima data de atualização no objeto de databases
		$stmt = oci_parse($catalog->dbconn, "select dbid, to_char(last_check, 'DD/MM/YYYY HH24:MI:SS') as last_check from ora_instance");
		oci_execute($stmt, OCI_DEFAULT);
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) 
			$databases->upInstanceLC($row['DBID'], $row['LAST_CHECK']);

	}

	function getRmanError ($connections, $dbid, $sessionrecid) {
		
		$stmt = oci_parse($connections->connArray[$dbid]->dbconn, "select output from v\$rman_output where session_recid = $sessionrecid and regexp_like(output, 'RMAN-|ORA-|ANS')");

		oci_execute($stmt, OCI_DEFAULT);

		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false)
			$errorLog .= $row['OUTPUT'] . "<br>";

		$this->rmanInfoSetArray[$dbid]->addRmanError($sessionrecid, $errorLog);

	}

	function countMax () {

		foreach ($this->rmanInfoSetArray as $risa)
			if ($risa->countObj > $this->countObjMax)
				$this->countObjMax = $risa->countObj;
	}

	function printDetail ($catalog, $dbid, $sessionrecid) {
		
		$SQL = "select c.name as instance, b.hostname, b.application, b.env, a.status, to_char(a.start_time, 'DD/MM/YYYY HH24:MI:SS') as timestart, to_char(a.end_time, 'DD/MM/YYYY HH24:MI:SS') as timeend, a.command_id, a.log from rman_log a, ora_instance b, rman.rc_database@catrman c where a.dbid = b.dbid and a.dbid = c.dbid and a.dbid = $dbid and a.session_recid = $sessionrecid"; 
		$stmt = oci_parse($catalog->dbconn, $SQL);
		oci_execute($stmt, OCI_DEFAULT);
		$row = oci_fetch_array($stmt, OCI_BOTH);

		echo "<table id='sortable' border='1'>\n";
		echo "<tr> <th><b>DESCRIPTION</b></th> <th><b>VALUE</b></th> </tr>";
		echo "<tr> <td>STATUS</td> <td>" . $row['STATUS'] . "</td> </tr>\n";
		echo "<tr> <td>INSTANCE</td> <td>" . $row['INSTANCE'] . "</td> </tr>\n";
		echo "<tr> <td>HOSTNAME</td> <td>" . $row['HOSTNAME'] . "</td> </tr>\n";
		echo "<tr> <td>SYSTEM</td> <td>" . $row['APPLICATION'] . "</td> </tr>\n";
		echo "<tr> <td>ENV</td> <td>" . $row['ENV'] . "</td> </tr>\n";
		echo "<tr> <td>TYPE</td> <td>" . $row['COMMAND_ID'] . "</td> </tr>\n";
		echo "<tr> <td>START TIME</td> <td>" . $row['TIMESTART'] . "</td> </tr>\n";
		echo "<tr> <td>END TIME</td> <td>" . $row['TIMEEND'] . "</td> </tr>\n";
		echo "</table>\n";

		echo "<table id='sortable'>";
		echo "<tr> <th><b>LOG</b></th> </tr>";	
		echo "<tr> <td><pre>" . $row['LOG']->load() . "</pre></td> </tr>\n";	

		echo "</table>";
		
	}
	
	function printReport ($databases, $catalog, $mode) {

		if ($mode == 0) {
			$DGREEN="<td bgcolor='#006a3d'>";
			$GREEN="<td bgcolor='#00a760'>";
			$LGREEN="<td bgcolor='#91ccb4'>";
			$RED="<td bgcolor='#db4137'>";
			$BLUE="<td bgcolor='#3752db'>";
		}
		else {
			$DGREEN="<td class='dgreen'>";
			$GREEN="<td class='green'>";
			$LGREEN="<td class='lgreen'>";
			$RED="<td class='red'>";
			$BLUE="<td class='blue'>";
		}
		
		///////////////////////////////////////////////////////////////

		echo "<table id='sortable' border='1'>\n";
		echo "<tr> <th><b>UPDATE</b></th> <th><b>ENV</b></th> <th><b>SYSTEM</b></th> <th><b>HOST</b></th> <th><b>INSTANCE</b></th> <th><b>DBID</b></th>";

		for ($i = 1 ; $i<= $this->countObjMax ; $i++)
			 echo "<th>TIME</th>";
		echo "</tr>\n";


		foreach ($databases->dbInstArray as $dbid => $dbInst) {
			echo "<tr> <td>" . $dbInst->last_check . "</td> <td>" . $dbInst->env . "</td> <td>" . $dbInst->application . "</td> <td>" . $dbInst->hostname . "</td> <td>" . $dbInst->instance . "</td> <td>" . $dbid . "</td>";

			$i = 1;			

			if (isset($this->rmanInfoSetArray[$dbid])) {

				// cada uma das execucoes do rman (por instância)
				foreach ($this->rmanInfoSetArray[$dbid]->rmanInfoArray as $ri) {
					
					$COLOR="<td>";

					if ($ri->status == 'COMPLETED') {
						switch (true) {
							case stristr($ri->operation, 'level2'):
								$COLOR=$LGREEN;
								break;
							case stristr($ri->operation, 'level1'):
								$COLOR=$GREEN;
								break;
							case stristr($ri->operation, 'level0'):
								$COLOR=$DGREEN;
								break;
						}				
					}
					else {
						if (strstr($ri->status, 'RUNNING'))
							$COLOR=$BLUE;
						else {
							$COLOR=$RED;
						}
					}
					if ( ($mode == "1") || ($mode == "2") )
						echo "$COLOR <a href='rman_report_detail.php?DBID=" . $dbid . "&SESSION=" . $ri->sessionRecid . "'>" . $ri->timeStart . " </a> </td>";
					else
						echo "$COLOR" . $ri->timeStart . "</td>";
					$i++;
				}

			}
	
			for ($j = $i ; $j<= $this->countObjMax ; $j++)
				echo "<td> </td>";
			echo "</tr>";
		}

		echo "</table>\n";

		echo "<table>";
		echo "<tr><th colspan=2> Legenda</th></tr>";
		echo "<tr><td>RMAN Level 0 - OK</td> $DGREEN &nbsp; &nbsp; &nbsp;</td></tr>";
		echo "<tr><td>RMAN Level 1 - OK</td> $GREEN &nbsp; &nbsp; &nbsp;</td></tr>";
		echo "<tr><td>RMAN Archives - OK</td> $LGREEN &nbsp; &nbsp; &nbsp;</td></tr>";
		echo "<tr><td>RMAN - Error</td> $RED &nbsp; &nbsp; &nbsp;</td></tr>";
		echo "<tr><td>RMAN - Running</td> $BLUE &nbsp; &nbsp; &nbsp;</td></tr>";
		echo "</table>";


		if ($mode == 0) {
			$this->printStructError ($databases, $catalog);
		}

	}

	function printStructError ($databases, $catalog) {

		echo "<table class='sortable' id='sortable' border=1>";
		echo "<tr><th><b>HOST</b></th> <th><b>INSTANCE</b></th> <th><b>DBID</b></th> <th><b>TIME</b></th> <th><b>ERROR LOG</b></th> </tr>";
		foreach ($this->rmanInfoSetArray as $dbid => $risa) {
			foreach ($risa->rmanInfoArray as $ri) {
				if (($ri->status != 'COMPLETED') and ($ri->status != 'RUNNING')) {
					$SQL = "select log_error from rman_log where dbid = $dbid and session_recid = $ri->sessionRecid";
					$stmt = oci_parse($catalog->dbconn, $SQL);
					oci_execute($stmt, OCI_DEFAULT);
					$row = oci_fetch_array($stmt, OCI_BOTH);

					echo "<tr> <td>" . $databases->dbInstArray[$dbid]->hostname . "</td><td>" . $databases->dbInstArray[$dbid]->instance . "</td><td>" . $dbid . "</td> <td>" . $ri->timeStart . "</td> <td> <pre>" . $row['LOG_ERROR']->load() . " </pre></td>  </tr>";
				}
				
			}
		}
		echo "</table>";
	
	}

}



?>
