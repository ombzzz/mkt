<html>
<script src="jquery-3.3.1.min.js"></script>
<style>
.log {
	white-space: pre-wrap;
}
.error {
	color: red;
}
</style>


<?
include_once('shdp/simple_html_dom.php');

echo main();


//------------------------------------------------
// main
//------------------------------------------------
function main(){

	$tck = $_POST["tck"];
	$bal = $_POST["bal"];
	$sqlite = $_POST["sqlite"];

	if( $bal == "bal" ){
		bal( $tck );

	}
	if( $sqlite == "sqlite" ){
		sqlite();
	}

	form( $tck );
}

//------------------------------------------------
// bal
//------------------------------------------------
function bal( $tck ){

	$url = "http://www.cnv.gob.ar/InfoFinan/Zips.asp?Lang=0&CodiSoc=3&DescriSoc=Agrometal%20Sociedad%20Anonima%20Industrial%20%20%20%20%20%20&Letra=A&TipoDocum=1&TipoArchivo=1&TipoBalance=1";

	$html = file_get_html( $url );
/*
div.contenido_derecha
	table  [titulo]
	table [archivos]
		tr [cabecera]
		tr [datos]
			td [fecha cierre]
				<a href="/Infofinan/BLOB_Zip.asp?cod_doc=463438&amp;error_page=Error.asp">31&nbsp;Dic&nbsp;2016</a>
			td [fecha recepcion]
			td [descripcion]
			td [id]
*/
	// Find all <td> in <table> which class=hello 

	$es = $html->find( 'div.contenido_derecha', 0 )->find( 'table', 1)->find( tr );
	$i = 0;
	foreach( $es as $k=> $v ){
		if( $i >= 2 ){
			$x = $v->find( 'td' );
			echo "http://www.cnv.gob.ar" . $x[0]->find( "a", 0)->href;
			echo $x[0]->plaintext;
			echo $x[1]->plaintext;
			echo $x[2]->plaintext;
			echo $x[3]->plaintext;
			echo "<br>\n";	
		}
		$i++;
	}
	return;

	$ret['Title'] = $html->find( 'title', 0 )->innertext;
	$ret['Rating'] = $html->find( 'div[class="general rating"] b', 0 )->innertext;

	// get overview
	foreach( $html->find( 'div[class="info"]' ) as $div ){
		// skip user comments
		if($div->find('h5', 0)->innertext=='User Comments:')
			return $ret;

		$key = '';
		$val = '';

		foreach( $div->find('*') as $node ){
			if( $node->tag == 'h5' )
				$key = $node->plaintext;

			if( $node->tag == 'a' && $node->plaintext != 'more' )
				$val .= trim( str_replace( "\n", '', $node->plaintext ) );

			if( $node->tag=='text' )
				$val .= trim( str_replace( "\n", '', $node->plaintext ) );
		}

		$ret[$key] = $val;
	}

	// clean up memory
	$html->clear();
	unset($html);

	foreach( $ret as $k => $v )
  	echo '<strong>'.$k.' </strong>'.$v.'<br>';

}


//------------------------------------------------
// form
//------------------------------------------------
function form( $tck ){

	echo <<<FINN
	<h4>cnv</h4>

	<form method=POST>
	<label>tck
	<input name=tck value="$tck" type=text>
	<input type=submit name=bal value=bal>
	<hr>
	<input type=submit name=sqlite value=sqlite>
	</form>

FINN;

}

//------------------------------------------------
// loguear
//------------------------------------------------
function loguear( $que, $tipo = "inform", $display = 1, $rendlog = 0, $nomail = 0 ){

	$filename = "cnv.log";
	if( !$fp = fopen($filename, 'a+' ) ){
		echo "no se puede abrir w+ el archivo $fileName";
	}
	else {
		$fecha = date( "Ymd H:i:s" );

		fwrite( $fp, "$fecha $que\n" );
		if( $display ){
			$cls = "log $tipo";
			echo "<div class=' " . $cls . "'>$que</div>";
		}
		fclose( $fp );
	}

	if( $tipo == "error" )
		$error = 1;
	else
		$error = 0;

	if( $rendlog ){
		log_insertar( "$fecha $que", $error );
	}

	if( $tipo == "error" && ! $nomail ){
		$ret = reportar( "dms.gti@ecogas.com.ar", "obiazzi@ecogas.com.ar", "$fecha $que", "rend.php " . substr( $que, 0, 50 ) );
		if( $ret != "ok" ){
			//recursivo pero sin envio de mail ya que esta fallanado
			//por seguridad recursividad hacemos s-leep
			$sleepsec = 60;
			sleep($sleepsec);
			//        c-encuyx, q-ue,                                                  t-ipo  $d-isp, l-og  n-omail
			loguear( "rend.loguear: envio de mails no volvio ok sino $ret", "error", 1,     1,    1 );
		}
	}

	return;
}

//------------------------------------------------
// log_insertar
//------------------------------------------------
function log_insertar( $que, $error ){
	return;
}

function reportar( $de, $a, $subj, $que ){
	return "ok";
}

//------------------------------------------------
// sqlite
//------------------------------------------------
function sqlite(){

	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		die ( 'Oops' . $e->getMessage() ); // Exit, displaying an error message
	}

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$pds = $pdo->query( "select tck from asset" );
	foreach( $pds as $row ){
		echo "<div>" . $row[ "tck" ] . "</div>";
	}
	$pdo = null; //para close connection 
	$pds = null; //para close connection
}


//------------------------------------------------
// fecha_offset
//------------------------------------------------
function fecha_offset( $str, $dias ){
	$time = strtotime( $str );
	$time = $time + ( $dias * 24 * 60 * 60);
	return date( 'Y-m-d', $time );
}

//------------------------------------------------
// fecha_hoy
//------------------------------------------------
function fecha_hoy(){
	return date('Y-m-d');
}


//------------------------------------------------
// assets
//------------------------------------------------
function assets( $mkt ){
	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "updall: error en conn: " . $e->getMessage(), "error" );
		return;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	try {
		$pds = $pdo->query( "select tck, mkt from asset a where a.mkt = 'bcba'" );
	} catch( PDOException $e ) {
		loguear( "updall: error en sel: " . $e->getMessage(), "error" );
		return;
	}

	$i = 0;
	foreach( $pds as $row )
		$res[$i++] = $row[ "tck" ];

	$pdo = null; //para close connection 
	$pds = null; //para close connection
	
	return $res;
}



?>

</html>