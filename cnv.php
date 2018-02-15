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

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://www.google.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$body = curl_exec($ch); 

//------------------------------------------------
// bal
//------------------------------------------------
function bal( $tck ){

	if( strlen( $tck) == 0 ){
		loguear( "bal: por favor pase tck", "error" );
		return;
	}
	loguear( "bal: invocado para $tck" );

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
	$j = 0;
	foreach( $es as $k=> $v ){
		if( $i >= 2 ){
			$x = $v->find( 'td' );
			//echo $x[0]->plaintext;
			$files[$j]["url"] = "http://www.cnv.gob.ar" . $x[0]->find( "a", 0)->href;
			$files[$j]["cfec"] = trim( $x[0]->plaintext );
			$files[$j]["cfec"] = str_replace( "&nbsp;", " ", $files[$j]["cfec"] );
			$files[$j]["rfec"] = trim( $x[1]->plaintext );
			$files[$j]["nom"] = trim( $x[2]->plaintext );
			$files[$j]["id"] = trim( $x[3]->plaintext );
			$j++;
		}
		$i++;
	}

	// clean up memory
	$html->clear();
	unset($html);

	if( ! file_exists( "_" . $tck ) ){
		mkdir( "_" . $tck, 0777 );
	}
	foreach( $files as $k => $v ){
		$fx = file_cnv2loc( $v );
		$fx = "_" . $tck . "/" . $fx;
		if( file_exists( $fx ) ){
			loguear( "bal $tck: " . $v["id"] . " ya existe" );
		}
		else {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $v["url"] );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$server_output = curl_exec( $ch );
			$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			curl_close( $ch );
			$chk = file_put_contents( $fx, $server_output );
			if( ! $chk ){
				loguear( "bal $tck: " . $k . " : id " . $v["id"] . " cierre " . $v["cfec"] . " rec " . $v["rfec"] . " nom " . $v["nom"] . " FAIL", "error" );
			}
			loguear( "bal $tck: " . $k . " : id " . $v["id"] . " cierre " . $v["cfec"] . " rec " . $v["rfec"] . " nom " . $v["nom"] . " SAVED" );
		}
	}
	return;
}

//------------------------------------------------
// file_cnv2loc
//------------------------------------------------
function file_cnv2loc( $x ){
	//31 Dic 2016	10 Mar 2017 13:02	Balance Consolidado Anual (Completo) al 31 Dic 2016	4-463438-D
	//20161231_20170310-1302_4-463438-D_Balance-Consolidado-Anual-Completo-al-31-Dic-2016.zip
	$cierre = cnv_cierre( $x["cfec"] );
	$recep = cnv_recep( $x["rfec"] );
	$nom = cnv_nom_sanit( $x["nom"] );
	return $cierre . "_" . $recep . "_" . $x["id"] . "_" . $nom . ".zip";
}

//------------------------------------------------
// cnv nom sanit
//------------------------------------------------
function cnv_nom_sanit( $x ){
	$x = str_replace( " ", "-", $x );
	$x = str_replace( "(", "", $x );
	$x = str_replace( ")", "", $x );
	return $x;
}

//------------------------------------------------
// cnv cierre
//------------------------------------------------
function cnv_cierre( $fecx ){
	$fecx = str_replace( "Ene", "01", $fecx );
	$fecx = str_replace( "Feb", "02", $fecx );
	$fecx = str_replace( "Mar", "03", $fecx );
	$fecx = str_replace( "Abr", "04", $fecx );
	$fecx = str_replace( "May", "05", $fecx );
	$fecx = str_replace( "Jun", "06", $fecx );
	$fecx = str_replace( "Jul", "07", $fecx );
	$fecx = str_replace( "Ago", "08", $fecx );
	$fecx = str_replace( "Sep", "09", $fecx );
	$fecx = str_replace( "Set", "10", $fecx );
	$fecx = str_replace( "Nov", "11", $fecx );
	$fecx = str_replace( "Dic", "12", $fecx );
	$ax = explode( " ", $fecx );
	return $ax[2] . $ax[1] . $ax[0];
}

//------------------------------------------------
// cnv recep
//------------------------------------------------
function cnv_recep( $fecx ){
	$fecx = str_replace( "Ene", "01", $fecx );
	$fecx = str_replace( "Feb", "02", $fecx );
	$fecx = str_replace( "Mar", "03", $fecx );
	$fecx = str_replace( "Abr", "04", $fecx );
	$fecx = str_replace( "May", "05", $fecx );
	$fecx = str_replace( "Jun", "06", $fecx );
	$fecx = str_replace( "Jul", "07", $fecx );
	$fecx = str_replace( "Ago", "08", $fecx );
	$fecx = str_replace( "Sep", "09", $fecx );
	$fecx = str_replace( "Set", "10", $fecx );
	$fecx = str_replace( "Nov", "11", $fecx );
	$fecx = str_replace( "Dic", "12", $fecx );
	$ax = explode( " ", $fecx );
	return $ax[2] . $ax[1] . $ax[0] . "-" . substr( $ax[3], 0, 2 ) . substr( $ax[3], 3, 2 );
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