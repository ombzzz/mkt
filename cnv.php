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
	$hr = $_POST["hr"];
	$hrall = $_POST["hrall"];
	$balall = $_POST["balall"];
	$sqlite = $_POST["sqlite"];

	if( $bal == "bal" ){
		$asx = assets( 'bcba', $tck );
		bal( $asx[0] );
	}
	else if( $hr == "hr" ){
		$asx = assets( 'bcba', $tck );
		hr( $asx[0] );
	}
	else if( $balall == "balall" ){
		balall();
	}
	else if( $hrall == "hrall" ){
		hrall();
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
// assets
//------------------------------------------------
function assets( $mkt, $tck = 'todos'){
	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "ass: error en conn: " . $e->getMessage(), "error" );
		return false;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if( $tck == 'todos' )
		$criterio = "";
	else if( strlen( $tck ) > 0 )
		$criterio = "and tck = '" . $tck . "'";
	else {
		loguear( "ass: por favor indique criterio de seleccion", "error" );
		return false;
	}

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	try {
		$pds = $pdo->query( "select * from asset a where a.mkt = '" . $mkt . "' $criterio and tipo = 'acc' order by a.tck" );
	} catch( PDOException $e ) {
		loguear( "ass: error en sel: " . $e->getMessage(), "error" );
		return false;
	}

	$i = 0;
	foreach( $pds as $row )
		$res[$i++] = $row;

	$pdo = null; //para close connection 
	$pds = null; //para close connection
	
	return $res;
}


//------------------------------------------------
// balall
//------------------------------------------------
function balall(){
	loguear( "balall invocado" );
	$cant = 0;
	$canta = 0;

	$ass = assets( 'bcba', 'todos' );

	foreach( $ass as $asx ){
		$res = bal( $asx );
		if( $res == -1 ){
			loguear( "balall: " . $asx["tck"] . " FATAL error" );
		}
		else if( $res >= 0 ){
			loguear( "balall: " . $asx["tck"] . " $res archivos" );
			$canta = $canta + $res;
			$cant = $cant + 1;
		}

	}
	loguear( "balall: finalizado, $cant assets actualizados, $canta archivos" );
	return;
}


//------------------------------------------------
// hrall
//------------------------------------------------
function hrall(){
	loguear( "hrall invocado" );
	$cant = 0;
	$canta = 0;

	$ass = assets( 'bcba', 'todos' );

	foreach( $ass as $asx ){
		$res = hr( $asx );
		if( $res == -1 ){
			loguear( "hrall: " . $asx["tck"] . " FATAL error" );
		}
		else if( $res >= 0 ){
			loguear( "hrall: " . $asx["tck"] . " $res archivos" );
			$canta = $canta + $res;
			$cant = $cant + 1;
		}

	}
	loguear( "hrall: finalizado, $cant assets actualizados, $canta archivos" );
	return;
}

//------------------------------------------------
// bal
//------------------------------------------------
function bal( $ass ){

	$cant = 0;

	if( !is_array( $ass ) || strlen( $ass["tck"] ) == 0 ){
		loguear( "bal: por favor pase un asset bien formado", "error" );
		return -1;
	}
	$tck = $ass["tck"];
	loguear( "bal: invocado para $tck" );

	$url = $ass["balurl"];
	if( strlen( $url ) == 0 ){
		loguear( "bal: $tck sin url balance, volviendo", "error" );
		return -1;
	}

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
			$files[$j]["id"] = str_replace( "\t", "", $files[$j]["id"] );
			$files[$j]["id"] = str_replace( " ", "", $files[$j]["id"] );
			$j++;
		}
		$i++;
	}

	// clean up memory
	$html->clear();
	unset($html);

 	$basedir = "/media/W/Comun/mkt/_$tck";

	if( ! file_exists( $basedir ) ){
		$oldumask = umask(0); //http://php.net/manual/en/function.mkdir.php#1207
		$ret = mkdir( $basedir, 0777 );
		umask($oldumask); 
		if( !$ret ){
			loguear( "bal: no se pudo crear dir $basedir, volviendo", "error" );
			return -1;
		}
	}
	foreach( $files as $k => $v ){
		$fx = file_cnv2loc( $v );
		$fx = $basedir . "/" . $fx;
		$pathparts = pathinfo( $fx );
		$fxunzipped = $pathparts["filename"];
		if( file_exists( $fx ) || file_exists( $basedir . "/" . $fxunzipped ) ){
			loguear( "bal $tck: $fx ya existe" );
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
			else {
				loguear( "bal $tck: " . $k . " : id " . $v["id"] . " cierre " . $v["cfec"] . " rec " . $v["rfec"] . " nom " . $v["nom"] . " SAVED" );
				$cant++;
			}
		}
	}
	return $cant;
}

//------------------------------------------------
// hr
//------------------------------------------------
function hr( $ass ){

	$cant = 0;

	if( !is_array( $ass ) || strlen( $ass["tck"] ) == 0 ){
		loguear( "hr: por favor pase un asset bien formado", "error" );
		return -1;
	}
	$tck = $ass["tck"];
	loguear( "hr: invocado para $tck" );

	$url = $ass["hrurl"];
	if( strlen( $url ) == 0 ){
		loguear( "hr: $tck sin url hr, volviendo", "error" );
		return -1;
	}

	loguear( "hr $tck: get $url" );
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

	if( ! is_object( $html ) ){
		loguear( "hr $tck: get de url volvio no objeto", "error" );
		return -1;
	}
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
			$files[$j]["id"] = str_replace( "\t", "", $files[$j]["id"] );
			$files[$j]["id"] = str_replace( " ", "", $files[$j]["id"] );
			$j++;
		}
		$i++;
	}

	// clean up memory
	$html->clear();
	unset($html);

 	$basedir = "/media/W/Comun/mkt/_$tck/hr";

	if( ! file_exists( $basedir ) ){
		$oldumask = umask(0); //http://php.net/manual/en/function.mkdir.php#1207
		$ret = mkdir( $basedir, 0777 );
		umask($oldumask); 
		if( !$ret ){
			loguear( "hr: no se pudo crear dir $basedir, volviendo", "error" );
			return -1;
		}
	}
	foreach( $files as $k => $v ){
		$fx = file_cnv2loc( $v );
		$fx = $basedir . "/" . $fx;
		if( file_exists( $fx ) ){
			loguear( "hr $tck: $fx ya existe" );
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
				loguear( "hr $tck: " . $k . " : id " . $v["id"] . " cierre " . $v["cfec"] . " rec " . $v["rfec"] . " nom " . $v["nom"] . " FAIL", "error" );
			}
			else {
				loguear( "hr $tck: " . $k . " : id " . $v["id"] . " cierre " . $v["cfec"] . " rec " . $v["rfec"] . " nom " . $v["nom"] . " SAVED" );
				$cant++;
			}
		}
	}
	return $cant;
}

//------------------------------------------------
// file_cnv2loc
//------------------------------------------------
function file_cnv2loc( $x ){
	//31 Dic 2016	10 Mar 2017 13:02	Balance Consolidado Anual (Completo) al 31 Dic 2016	4-463438-D
	//20161231_20170310-1302_4-463438-D_Balance-Consolidado-Anual-Completo-al-31-Dic-2016.zip
	$cierre = cnv_cierre( $x["cfec"] );
	$recep = cnv_recep( $x["rfec"] );
	$nom = substr( cnv_nom_sanit( $x["nom"] ), 0, 150 );
	$id = str_replace( "(*)", "_ASK_", $x["id"] );

	return $cierre . "_" . $recep . "_" . $id . "_" . $nom . ".zip";
}

//------------------------------------------------
// cnv nom sanit
//------------------------------------------------
function cnv_nom_sanit( $x ){
	$x = str_replace( " ", "-", $x );
	$x = str_replace( "(", "", $x );
	$x = str_replace( ")", "", $x );
	$x = str_replace( "/", "_", $x );
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
	$fecx = str_replace( "Set", "09", $fecx );
	$fecx = str_replace( "Oct", "10", $fecx );
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
	$fecx = str_replace( "Set", "09", $fecx );
	$fecx = str_replace( "Oct", "10", $fecx );
	$fecx = str_replace( "Nov", "11", $fecx );
	$fecx = str_replace( "Dic", "12", $fecx );
	$ax = explode( " ", $fecx );
	return $ax[2] . $ax[1] . $ax[0] . "-" . substr( $ax[3], 0, 2 ) . substr( $ax[3], 3, 2 );
}

//---------------------------r--------------------
// form
//------------------------------------------------
function form( $tck ){

	echo <<<FINN
	<h4>cnv</h4>

	<form method=POST>
	<label>tck
	<input name=tck value="$tck" type=text>
	<input type=submit name=bal value=bal>
	<input type=submit name=hr value=hr>
	<input type=submit name=balall value=balall>
	<input type=submit name=hrall value=hrall>
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
	$pds = $pdo->query( "select tck from asset order by tck" );
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



?>

</html>