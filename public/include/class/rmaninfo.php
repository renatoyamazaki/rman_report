<?php

/**
 *	Classe que contem as informaçoes basicas dos backups rman
 *
 *	
 */
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
 *	Possui um vetor com varios objetos de rman 'rmanInfo'
 *
 *
 */
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
 *	Possui um vetor com varios objetos de conexao 'rmanInfoSet'
 *
 *
 */
class dbInfo {

	public $rmanInfoSetArray = array();
	public $countObjMax;

	function __construct () {
	}

	function __destruct () {
	}
	

	/**
	 * Returns the last information on the catalog
	 *
	 * @param	object	$conn	Connection
	 * @param	string	$dbid	DBID from instance
	 * @return	array	$result	Array with indexed by 'last_backup' and 'last_recid'
	 */
	function last_info_catalog ($conn, $dbid) {

		$result = array();

		// Gets the most recent info from the catalog
$SQL = <<<EOT
SELECT session_recid,
       to_char(start_time, 'DD/MM/YYYY HH24:MI:SS') AS timestart
FROM rman_log
WHERE start_time =
    (SELECT max(start_time)
     FROM rman_log
     WHERE dbid = :dbid
       AND status LIKE 'COMPLETED%')
  AND dbid = :dbid
  AND status LIKE 'COMPLETED%'
EOT;

		$stmt = oci_parse($conn->dbconn, $SQL);
		oci_bind_by_name($stmt, ":dbid", $dbid);
		oci_execute($stmt, OCI_DEFAULT);

		// If its the first sincro, the values will be NULL
		$row = oci_fetch_array($stmt, OCI_BOTH);
		$last_backup = $row['TIMESTART'];
		if ($last_backup === NULL)
			$last_backup = '01/01/2001 00:00:00';
		$last_recid = $row['SESSION_RECID'];
		if ($last_recid === NULL)
			$last_recid = '0';

		$result = array( last_backup => $last_backup, last_recid => $last_recid );
		
		return $result;
	}


	/**
	 * Gets all the info from the target instance
	 *
	 * @param	object	$conn		Connection
	 * @param	string	$dbid		DBID from instance
	 * @param	string	$last_backup	Last date info
	 * @param	string	$last_recid	Last recid info
	 * @return	void
	 */
	function get_info_instance ($conn, $dbid, $last_backup, $last_recid) {

		// ID 375386.1
		$SQL = "alter session set optimizer_mode=RULE";
		$stmt = oci_parse($conn->dbconn, $SQL);
		oci_execute($stmt, OCI_DEFAULT);

$SQL = <<<EOT
SELECT session_recid,
       status,
       to_char(min(start_time), 'DD/MM/YYYY HH24:MI:SS') AS timestart,
       to_char(max(end_time), 'DD/MM/YYYY HH24:MI:SS') AS timeend,
       command_id,
       max(end_time) AS TIME
FROM v\$rman_status
WHERE session_recid <> :last_recid
  AND start_time > to_date(:last_backup, 'DD/MM/YYYY HH24:MI:SS')
GROUP BY session_recid,
         status,
         command_id
ORDER BY 6 DESC
EOT;

		$stmt = oci_parse($conn->dbconn, $SQL);
		oci_bind_by_name($stmt, ":last_recid", $last_recid);
		oci_bind_by_name($stmt, ":last_backup", $last_backup);
		oci_execute($stmt, OCI_DEFAULT);

		// All the info collected goes to the object
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false)
			$this->rmanInfoSetArray[$dbid]->addRmanInfo($row['SESSION_RECID'], $row['STATUS'], $row['TIMESTART'], $row['TIMEEND'], $row['COMMAND_ID']);	
	}


	/**
	 * Gets the log from the rman executions
	 *
	 * @param	object	$conn	Connection
	 * @param	string	$dbid	DBID from instance
	 * @return	void
	 */
	function get_log_instance ($conn, $dbid) {

		// Iterates over the rman info objects created before
		foreach ($this->rmanInfoSetArray[$dbid]->rmanInfoArray as $ri) {
			$SQL = "select output from v\$rman_output where session_recid = :recid";
			$stmt = oci_parse($conn->dbconn, $SQL);
			oci_bind_by_name($stmt, ":recid", $ri->sessionRecid);
			oci_execute($stmt, OCI_DEFAULT);

			// In output goes all the log
			// In output_error goes only the lines with error
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

	/**
	 * Updates last check info
	 *
	 * @param	object	$conn	Connection
	 * @param	string	$dbid	DBID from instance
	 * @return	bool		TRUE if registered with sucess, FALSE otherwise
	 */
	function update_last_check ($conn, $dbid) {

		$SQL = "update ora_instance set LAST_CHECK = sysdate where dbid = :dbid";
		$stmt = oci_parse($conn->dbconn, $SQL);
		oci_bind_by_name($stmt, ":dbid", $dbid);
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
	 * Gets rman info from each target instance
	 *
	 * @param	object	$connections	
	 * @param	object	$catalog	
	 * @return	void
	 */
	function getDbInfoInstances ($connections, $catalog) {

		foreach ($connections->connArray as $dbid => $connection) {

			// Gets info from last executions of the catalog
			$last_info = $this->last_info_catalog($catalog, $dbid);
		
			// Creates a new object to put the info from target dbid
			$this->rmanInfoSetArray[$dbid] = new rmanInfoSet();
			
			// Populates the object
			$this->get_info_instance($connection, $dbid, $last_info[last_backup], $last_info[last_recid]);
			$this->get_log_instance($connection, $dbid);
			
			// Updates last check
			$this->update_last_check($catalog, $dbid);
		}
	}


	/**
	 *
	 *
	 *
	 *
	 *
	 *
	 */
	function putDbInfoCatalog ($catalog) {
	
		foreach ($this->rmanInfoSetArray as $dbid => $risa) {
					
			foreach ($risa->rmanInfoArray as $ri) {
			
$SQL = <<<EOT
SELECT count(*) AS COUNT
FROM rman_log
WHERE dbid = :dbid
  AND session_recid = :session_recid
  AND start_time = to_date(:start_time, 'DD/MM/YYYY HH24:MI:SS')
EOT;
				$stmt = oci_parse($catalog->dbconn, $SQL);
				oci_bind_by_name($stmt, ":dbid", $dbid);
				oci_bind_by_name($stmt, ":session_recid", $ri->sessionRecid);
				oci_bind_by_name($stmt, ":start_time", $ri->timeStart);
				oci_execute($stmt, OCI_DEFAULT);
				
				// INSERE, SE NAO EXISTE REGISTRO (PK)
				$row = oci_fetch_array($stmt, OCI_BOTH);
				if ($row['COUNT'] == 0) {
$SQL = <<<EOT
INSERT INTO rman_log (DBID, SESSION_RECID, STATUS, START_TIME, END_TIME, COMMAND_ID, LOG, LOG_ERROR)
VALUES (:dbid,
        :session_recid,
        :status,
        to_date(:start_time, 'DD/MM/YYYY HH24:MI:SS'),
        to_date(:end_time, 'DD/MM/YYYY HH24:MI:SS'),
        :command_id,
        :log,
        :log_error)
EOT;
					$stmt = oci_parse($catalog->dbconn, $SQL);
					oci_bind_by_name($stmt, ":dbid", $dbid);
					oci_bind_by_name($stmt, ":session_recid", $ri->sessionRecid);
					oci_bind_by_name($stmt, ":status", $ri->status);
					oci_bind_by_name($stmt, ":start_time", $ri->timeStart);
					oci_bind_by_name($stmt, ":end_time", $ri->timeEnd);
					oci_bind_by_name($stmt, ":command_id", $ri->operation);
					oci_bind_by_name($stmt, ":log", $ri->log);
					oci_bind_by_name($stmt, ":log_error", $ri->log_error);
					oci_execute($stmt, OCI_DEFAULT);
				}
				else {
$SQL = <<<EOT
UPDATE rman_log
SET STATUS = :status,
    COMMAND_ID = :command_id,
    END_TIME = to_date(:end_time, 'DD/MM/YYYY HH24:MI:SS'),
    LOG = :log,
    LOG_ERROR = :log_error
WHERE DBID = :dbid
  AND SESSION_RECID = :session_recid
  AND START_TIME = to_date(:start_time, 'DD/MM/YYYY HH24:MI:SS')
EOT;
					$stmt = oci_parse($catalog->dbconn, $SQL);
					oci_bind_by_name($stmt, ":status", $ri->status);
					oci_bind_by_name($stmt, ":command_id", $ri->operation);
					oci_bind_by_name($stmt, ":end_time", $ri->timeEnd);
					oci_bind_by_name($stmt, ":dbid", $dbid);
					oci_bind_by_name($stmt, ":session_recid", $ri->sessionRecid);
					oci_bind_by_name($stmt, ":start_time", $ri->timeStart);
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

$SQL = <<<EOT
	SELECT DBID,
       SESSION_RECID,
       STATUS,
       to_char(START_TIME, 'DD/MM/YYYY HH24:MI:SS') AS TIMESTART,
       to_char(END_TIME, 'DD/MM/YYYY HH24:MI:SS') AS TIMEEND,
       COMMAND_ID,
       END_TIME
FROM rman_log
WHERE START_TIME BETWEEN to_date(:start_time, 'DD/MM/YYYY HH24:MI:SS') AND to_date(:start_time, 'DD/MM/YYYY HH24:MI:SS')+1
ORDER BY 7 DESC
EOT;

		$stmt = oci_parse($catalog->dbconn, $SQL);
		oci_bind_by_name($stmt, ":start_time", $userDate);
		oci_execute($stmt, OCI_DEFAULT);
		
		while (($row = oci_fetch_array($stmt, OCI_BOTH)) != false) {
			$dbid = $row['DBID'];
                        // caso seja uma das instancias que ja estao no objeto 'databases'
                        if (array_key_exists($dbid, $databases->dbInstArray)) {
				if (! array_key_exists($dbid, $this->rmanInfoSetArray))
					$this->rmanInfoSetArray[$dbid] = new rmanInfoSet();				
				$this->rmanInfoSetArray[$dbid]->addRmanInfo($row['SESSION_RECID'], $row['STATUS'], $row['TIMESTART'], $row['TIMEEND'], $row['COMMAND_ID']);
				$this->rmanInfoSetArray[$dbid]->countObj();
			}
		}

		$this->countMax();
	}

	function getRmanError ($connections, $dbid, $sessionrecid) {
		
		$SQL = "select output from v\$rman_output where session_recid = :session_recid and regexp_like(output, 'RMAN-|ORA-|ANS')";
		$stmt = oci_parse($connections->connArray[$dbid]->dbconn, $SQL);
		oci_bind_by_name($stmt, ":session_recid", $sessionrecid);
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

	/**
	 *
	 *
	 *
	 */
	function printDetail ($catalog, $dbid, $sessionrecid) {
		
$SQL = <<<EOT
SELECT b.instance,
       b.hostname,
       b.application,
       b.env,
       a.status,
       to_char(a.start_time, 'DD/MM/YYYY HH24:MI:SS') AS timestart,
       to_char(a.end_time, 'DD/MM/YYYY HH24:MI:SS') AS timeend,
       a.command_id,
       a.log
FROM rman_log a,
     ora_instance b
WHERE a.dbid = b.dbid
  AND a.dbid = :dbid
  AND a.session_recid = :session_recid
EOT;

		$stmt = oci_parse($catalog->dbconn, $SQL);
		oci_bind_by_name($stmt, ":dbid", $dbid);
		oci_bind_by_name($stmt, ":session_recid", $sessionrecid);
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

		echo "<table id='report' border='1'>\n";
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

		echo "<table id='legend'>";
		echo "<tr><th colspan=2> Legend</th></tr>";
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
					$SQL = "select log_error from rman_log where dbid = :dbid and session_recid = :session_recid";
					$stmt = oci_parse($catalog->dbconn, $SQL);
					oci_bind_by_name($stmt, ":dbid", $dbid);
					oci_bind_by_name($stmt, ":session_recid", $ri->sessionRecid);
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
