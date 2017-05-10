<?php

/**
 *	Classe que contem tudo que e necessario para realizar a conexao com o BD
 *	Como a aplicacao do rman_report utiliza conexoes com varias outras instancias target
 *	E necessario serparar a logica da conexao com a aplicacao e com os targets
 *	Ao construir o objeto sem parametros, a conexao sera realizada com a aplicacao.
 **/
class conn {

	private $serv;
	private $inst;
	private $user = USERNAME;
	private $pass = PASSWORD;
	public $dbconn;

	/**
	 *	Pode realizar 2 tipos de conexao 
	 *	- Com o servidor que possui os objetos da aplicacao
	 *	- Com o servidor target, que possui as informacoes a serem coletadas
	 **/
	function __construct ($server = NULL, $instance = NULL) {
		
		// Seta parametros de conexao
		// Conexao para usuario da aplicacao
		if ( $server === NULL ) {	
			$this->serv = SERVER;
			$this->inst = INSTANCE;
		} 
		// Caso seja conexao com os targets
		else {
			$this->serv = $server;
			$this->inst = $instance;
		}

		// Realiza a conexao
		// Armazena a conexao na variavel dbconn		
		if ($instance != 'PAR')
			$tns = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->serv.")(PORT = 1521)))(CONNECT_DATA=(SERVICE_NAME=".$this->inst."))) ";
		else
			$tns = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->serv.")(PORT = 1523)))(CONNECT_DATA=(SERVICE_NAME=".$this->inst."))) ";
		
		$this->dbconn = @oci_connect($this->user, $this->pass, $tns);

		if (!$this->dbconn) {
			echo "Erro na conexão com o Oracle, verifique parametros.";
                }

	}


	function __destruct () {
		$this->dbconn = NULL;
	}


}

/**
 *	Possui um vetor com varios objetos de conexao 'conn'
 **/
class connSet {
	
	public $connArray = array();

	function __construct ($dbSet) {
		foreach ($dbSet->dbInstArray as $dia) 
			$this->connArray[$dia->dbid] = new conn ($dia->hostname, $dia->instance);
	}

	function __destruct () {
		$this->connArray = NULL;
	}
}


?>
