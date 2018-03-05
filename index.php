<?
if( strlen( $_GET["json"] ) > 0 ){
	json( $_GET["json"], $_GET["de"], $_GET["a"] );
	return;
}
?>

<html>
<script src="jquery-3.3.1.min.js"></script>
<script src="hs/code/highstock.js"></script>
<script src="hs/code/modules/exporting.js"></script>
<style>

input {
	margin: 3px;
}

.log {
	white-space: pre-wrap;
}
.error {
	color: red;
}
</style>


<?
echo main();


//------------------------------------------------
// main
//------------------------------------------------
function main(){

	$login = $_POST["login"];
	$usr = $_POST["usr"];
	$pas = $_POST["pas"];
	$cotiz = $_POST["cotiz"];
	$panel = $_POST["panel"];
	$precios = $_POST["precios"];
	$preciosdb = $_POST["preciosdb"];
	$graf = $_POST["graf"];
	$tck = $_POST["tck"];
	$de = $_POST["de"];
	$a = $_POST["a"];
	$panel = $_POST["panel"];
	$bearer = $_POST["bearer"];
	$sqlite = $_POST["sqlite"];

	$last = $_POST["last"];
	$pers = $_POST["pers"];
	$upd = $_POST["upd"];
	$updall = $_POST["updall"];

	$json = $_GET["json"];

	if( strlen($de) == 0 )
		$de = "2018-01-01";

	if( strlen($a) == 0 )
		$a = fecha_hoy();

	if( $login == "login" ){
		$json = login( $usr, $pas );
		$jsona = json_decode( $json, true );
		$bearer = $jsona["access_token"];
	}
	if( $panel == "panel" ){
		$panela = panel( $bearer, $panel );
		$titulos = $panela["titulos"];

		//formato del json en la funcion c-otiz 
		foreach( $titulos as $key => $value ){
			echo "<div>".$value["simbolo"]."</div>";
		}
	}
	if( $cotiz == "cotiz" ){
		$cotiza = cotiz( $bearer, $tck );
		foreach( $cotiza as $key => $value ){
			echo "<div>" .$key . ":" . $value ."</div>";
		}
	}
	if( $precios == "precios" ){
		$preciosa = precios( $tck, $de, $a, $bearer );
		if( $preciosa ){
			foreach( $preciosa as $k=>$v ){
				echo "<div>" . $v["fechaHora"] . ": " . $v["ultimoPrecio"] . "</div>";
			}
		}
	}
	if( $preciosdb == "preciosdb" ){
		$pra = preciosdb( $tck, $de, $a );
		foreach( $pra as $key => $pr ){
			echo "<div class=precio><span>" . $pr["fec"] . ": " . "</span><span>" 
			 . number_format( (float) $pr["close"], 2, '.', '' ) . "</span></div>";
		}
	}
	if( $graf == "graf" ){
		graf( $tck, $de, $a );
	}
	if( $sqlite == "sqlite" ){
		sqlite();
	}
	if( $last == "last" ){
		$lp = last( $tck );
		echo "<p>ultimo precio de $tck fue " . $lp;
	}
	if( $pers == "pers" ){
		pers( $tck, $de, $a, $bearer );

	}
	if( $upd == "upd" ){
		upd( $tck, $bearer );
	}
	if( $updall == "updall" ){
		updall( $bearer );
	}

	form( $usr, $pas, $panel, $cotiz, $tck, $de, $a, $bearer );
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
// updall
//------------------------------------------------
function updall( $bearer ){
	loguear( "updall invocado" );
	$cant = 0;

	$asx = assets( 'bcba' );

	foreach( $asx as $as ){
		$res = upd( $as["tck"], $bearer );
		if( $res == 0 )
			$cant = $cant + 1;
	}
	loguear( "updall: finalizado, $cant assets actualizados" );
	return;

}


//------------------------------------------------
// assets
//------------------------------------------------
function assets( $mkt, $tck = 'todos' ){
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
		$sql = "select * from asset a where ( a.mkt = '" . $mkt . "' or 'all' = '"  . $mkt . "' ) "
		 . " $criterio order by a.tck";
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "ass: error en sel: " . $e->getMessage() . " sql fue " . $sql, "error" );
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
// preciosdb
//------------------------------------------------
function preciosdb( $tck, $de = "2018-01-01", $a = "hoy" ){
	if( $a == "hoy" )
		$a = fecha_hoy();

	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "pdb: error en conn: " . $e->getMessage(), "error" );
		return;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$sql = "select * from as_pr ap where ap.tck = '" . $tck . "' and ap.fec between '" . $de . "' and '" . $a . "' order by fec";
	try {
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "pdb: error en sel: " . $e->getMessage(), "error" );
		return;
	}

	$i = 0;
	foreach( $pds as $row )
		$res[$i++] = $row;

	$pdo = null; //para close connection 
	$pds = null; //para close connection
	
	return $res;
}

//------------------------------------------------
// upd
//------------------------------------------------
function upd( $tck, $bearer ){
	loguear( "upd: invocado para $tck" );
	$lp = last( $tck );
	if( $lp == null ){
		loguear( "upd $tck: sin precios registrados => se buscaran desde 3/12/2001" );
		$de = "2001-12-03";
	}
	else {
		loguear( "upd $tck: ult precio " . $lp );
		$de = fecha_offset( $lp, 1 ); 
	}
	$a = fecha_hoy();
	if( $lp == $a ){
		loguear( "upd $tck: up to date => NOP" );
		return 0;
	}
	$cant = pers( $tck, $de, $a, $bearer );
	if( $cant >= 0 ){
		loguear( "upd $tck: $cant precios registrados" );
		return 0;
	}
	else {
		loguear( "upd $tck: problemas registrando precios", "error" );
		return 1;
	}
}

//------------------------------------------------
// pers
//------------------------------------------------
function pers( $tck, $de, $a, $bearer ){
	loguear( "pers: invocado para $tck de $de a $a" );

	$cant = 0;
	$preciosa = precios( $tck, $de, $a, $bearer );
	if( !is_array( $preciosa ) && $preciosa == false ){
		loguear( "pers $tck $de $a: problemas en get de precios", "error" );
		return -1;
	}
	else {
	 loguear( "pers $tck $de $a: " . count( $preciosa ) . " precios obtenidos, recorriendo" );
	}

	$database = 'mkt';
	$dsn = "sqlite:$database.db";
	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "pers: error en conn: " . $e->getMessage(), "error" );
		return;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $pdo->beginTransaction();

	foreach( $preciosa as $key => $value ){
		$tck = strtolower( $tck );
		$fec = substr( $value["fechaHora"], 0, 10 );
		$open = $value["apertura"];
		$close = $value["ultimoPrecio"];
		$min = $value["minimo"];
		$max = $value["maximo"];
		$vol = $value["volumenNominal"];
		$montop = $value["montoOperado"];

		$sql = <<<FINN
		insert into as_pr( tck, fec, open, close, min, max, vol, montop ) values (
			'$tck',
			'$fec',
			$open,
			$close,
			$min,
			$max,
			$vol,
			$montop
		)
FINN;

		try {
			$pdo->exec( $sql );
		} catch( PDOException $e ) {
			$pdo->rollback();
			loguear( "pers: error en ins: " . $e->getMessage() . " sql $sql", "error" );
			return -1;
		}

		$cant = $cant + 1;
		if( $cant%100 == 0 )
			loguear( "pers $tck $de $a: van $cant" );
	}
	loguear( "pers $tck $de $a: fuera de cursor" );

	if($cant){
		try {
			$pdo->commit();
		} catch( PDOException $e ) {
			loguear( "pers $tck $de $a: error en c-ommit: " . $e->getMessage(), "error" );
			return -1;
		}

		loguear( "pers $tck: --commit--" );

	}
	
	$pdo = null; //para close connection 
	$pds = null; //para close connection

	return $cant;

}

//------------------------------------------------
// last
//------------------------------------------------
function last( $tck ){

	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "last $tck: error " . $e->getMessage(), "error" );
		return;
	}

	$tck = strtolower( $tck );
	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$pds = $pdo->query( "select max( fec ) fec from as_pr where tck = '" . $tck. "'" );
	$row = $pds->fetch();

	$pdo = null; //para close connection 
	$pds = null; //para close connection

	return $row[ "fec" ];
}

//------------------------------------------------
// precios
/*
  "ultimoPrecio": 125.35,
  "variacion": 0,
  "apertura": 131.2,
  "maximo": 132,
  "minimo": 125,
  "fechaHora": "2018-02-07T17:00:34.037",
  "tendencia": "sube",
  "cierreAnterior": 0,
  "montoOperado": 51400059.55,
  "volumenNominal": 402982,
  "precioPromedio": 0,
  "moneda": "peso_Argentino",
  "precioAjuste": 0,
  "interesesAbiertos": 0,
  "puntas": null,
  "cantidadOperaciones": 0
*/
//------------------------------------------------
function precios( $tck, $de, $a, $bearer ){
	$hayprecios = 0;
	$tck = strtoupper($tck);

	//GET /api/{mercado}/Titulos/{simbolo}/Cotizacion/seriehistorica/{fechaDesde}/{fechaHasta}/{ajustada}
	//mercado: bCBA, nYSE, nASDAQ, aMEX, bCS, rOFX
	$url = "https://api.invertironline.com/api/bCBA/Titulos/$tck/Cotizacion/seriehistorica/$de/$a/ajustada";
	loguear( "precios: $url" );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );

	$preciosa = json_decode( $server_output, true );
	if( count( $preciosa ) == 1 && "xx" . key( $preciosa ) == "xxmessage" ){
		loguear( "precios: error: " . $preciosa["message"], "error" );
		return false;
	}
	else if( count( $preciosa ) == 0 ){
		loguear( "precios: sin precios historicos para $tck" );
	}
	else if( $preciosa[0]["fechaHora"] > 0 ){
		$hayprecios = 1;
	}
	else {
		//loguear( "precios: error: no se recibio array bien formado, sino " . var_dump( $precios ), "error" );
		return false;
	}

	if( $a == fecha_hoy() ){
		//serie historica no devuelve el precio de hoy, lo buscamo434s por cotizacion
		$cotiz = cotiz( $bearer, $tck );
		$ulth = substr( $preciosa[ count($preciosa)-1 ]["fechaHora"], 0, 10 );
		$ultc = substr( $cotiz["fechaHora"], 0, 10 );
		//en algun momento la historica lo trae, chequeamos si lo trajo a hoy, para no meterlo dos veces en el a-rray
		//ademas chequeamos que la cotiz recibida no sea anterior al hasta solicitado
		if( $ultc != $ulth && $ultc >= $a ){
			array_unshift( $preciosa, $cotiz );
			$hayprecios = 1;
		}
	}
	if( $hayprecios == 1 ){
  	return $preciosa;
	}
 	else{
 		return Array();
 	}
}

//------------------------------------------------
// panel
//------------------------------------------------
function panel( $bearer, $panel ){

	///api/{pais}/Titulos/Cotizacion/Paneles/{instrumento}
	//pais: argentina, brasil, chile, colombia, estados_unidos, mexico, peru
	//instrumento: Acciones, Bonos, Opciones, Monedas, Cauciones, cHPD, Futuros
		
	//GET /api/Cotizaciones/{Instrumento}/{Panel}/{Pais}
	//instrumento: Acciones, Bonos, Opciones, Monedas, Cauciones, cHPD, Futuros
	//panel(argentina): Merval, Panel General, Merval 25, Merval Argentina, Burcap, CEDEARs

	// usa paneles:
	// Dow Jones Industrial
	// Dow Jones Transportation
	// Dow Jones Utilities
	// Nasdaq 100
	// SP100
	// SP500
	// SP500 Value
	// SP500 Growth
	// SP400 MidCap
	// SP400 MidCap Value
	// S400 MidCap Growth
	// SP600 SmallCap
	// SP600 SmallCap Value
	// SP600 SmallCap Growth
	// SP500 Dividendos
	// ADRs

	//$url = "https://api.invertironline.com/api/argentina/Titulos/Cotizacion/Paneles/Acciones";
	$panel = str_replace( " ", "%20", $panel );
	$url = "https://api.invertironline.com/api/Cotizaciones/Acciones/$panel/argentina";
	loguear( "panel: $url" );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );
	$panela = json_decode( $server_output, true );
  return $panela;

	//{
	// "simbolo": "AGRO",
	//  "puntas": {
	//  	 "cantidadCompra": 0.0,
	//  	 "precioCompra": 0.000,
	//  	 "precioVenta": 25.500,
	//  	 "cantidadVenta": 22.0 
	//  },
	//  "ultimoPrecio": 22.500,
	//  "variacionPorcentual": -1.74,
	//  "apertura": 22.600,
	//  "maximo": 23.400, 
	//  "minimo": 21.500,
	//  "ultimoCierre": 22.500,
	//  "volumen": 0.0,
	//  "cantidadOperaciones": 242.0,
	//  "fecha": "2018-02-09T17:00:06.503", 
	//  "tipoOpcion": null,
	//  "precioEjercicio": null,
	//  "fechaVencimiento": null,
	//  "mercado": "BCBA",
	//  "moneda": "AR$"
	// }

}


//------------------------------------------------
// cotiz
//
// ultimoPrecio:22.8
// variacion:1.33
// apertura:22.7
// maximo:23.15
// minimo:21.95
// fechaHora:2018-02-14T17:00:06.0980769-03:00
// tendencia:sube
// cierreAnterior:22.5
// montoOperado:1838556.05
// volumenNominal:550
// precioPromedio:0
// moneda:peso_Argentino
// precioAjuste:0
// interesesAbiertos:0
// puntas:Array
// cantidadOperaciones:137
//------------------------------------------------
function cotiz( $bearer, $tck ){
	//$url = "https://api.invertironline.com/api/argentina/Titulos/Cotizacion/Paneles/Acciones";
	$tck = strtoupper($tck);
	$url = "https://api.invertironline.com/api/bCBA/Titulos/$tck/Cotizacion";

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );
	$cotiza = json_decode( $server_output, true );
  return $cotiza;
}

//------------------------------------------------
// form
//------------------------------------------------
function form( $usr, $pas, $panel, $cotiz, $tck, $de, $a, $bearer ){

	echo <<<FINN
	<h4>mkt</h4>

	<form method=POST>
	<label>usr
	<input name=usr value="$usr" type=text>
	<br>
	<lable>pas
	<input name=pas value="" type=password>
	<br><input type=submit name=login value=login>
	<hr>
	<label>panel ( Merval, Panel General, Merval 25, Merval Argentina, Burcap, CEDEARs )
	<input name=panel value="$panel" type=text>
	<input type=submit name=panel value=panel>
	<br>
	<label>tck
	<textarea rows=4 cols=100 name=tck>$tck</textarea>
	<br>
	<label>de ( ej 2018-02-25, habria desde 3/12/2001 )
	<input name=de value="$de" type=text>
	<label>a <input name=a value="$a" type=text>
	<br>
	<input type=submit name=cotiz value=cotiz>
	<input type=submit name=precios value=precios>
	<input type=submit name=preciosdb value=preciosdb>
	<input type=submit name=graf value=graf>
	<input type=submit name=last value=last>
	<input type=submit name=pers value=pers>
	<input type=submit name=upd value=upd>
	<input type=submit name=updall value=updall>
	<hr>
	<input type=submit name=sqlite value=sqlite>
	<textarea rows=4 cols=100 name=bearer>$bearer</textarea>
	</form>

FINN;

}

//------------------------------------------------
// login
//------------------------------------------------
function login( $usr, $pas ){

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, "https://api.invertironline.com/token");
	curl_setopt( $ch, CURLOPT_POST, 1 );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, "username=$usr&password=$pas&grant_type=password");

	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

	$server_output = curl_exec( $ch );

	curl_close( $ch );

  return $server_output;

}


//------------------------------------------------
// loguear
//------------------------------------------------
function loguear( $que, $tipo = "inform", $display = 1, $rendlog = 0, $nomail = 0 ){

	$filename = "mkt.log";
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
// json
//------------------------------------------------
function json( $tck, $de, $a ){
	header('Content-type: application/json');
//echo 
//"[
//[1297728000000,51.41]
//]";
//	 return;
//echo "[[1297728000000,51.41]]";
	// return;
	$ret = "[\n";
	$pra = preciosdb( $tck, $de, $a );
	foreach( $pra as $key => $pr ){
		$ret = $ret . "[" . strtotime( $pr["fec"] ) . "000" . "," . number_format( (float) $pr["close"], 2, '.', '' ) . "]";
		if( $key < count( $pra ) - 1 )
			$ret = $ret . ",\n";
	}
	$ret = $ret . "\n]";
	echo $ret;
}


//------------------------------------------------
// graf
//------------------------------------------------
function graf( $tck, $de, $a ){

	$tck = str_replace( "\r\n", " ", $tck );
	$tck = str_replace( "\n", " ", $tck );

	if( strlen(trim($tck)) == 0 ){
		return "verifique parametros";
	}
	$as = explode( " ", trim( $tck ) );

	foreach ( $as as $key => $value) {
		$asx = assets( 'all', $value );
		$den = $asx[0]["den"];
		$tx = $asx[0]["tck"];

		$url = "index.php?json=$tx&de=$de&a=$a";

		echo <<<FINN
<div id="container_$tx" style="height: 600px; min-width: 310px"></div>

<script>
$.getJSON( '$url', function( data ){
	console.log( "$url");
	Highcharts.stockChart('container_$tx', {
		rangeSelector: {selected: 1 },

		title: {text: '$den'},

		series: [
		{
			name: '$tx',
			data: data,
			tooltip: {valueDecimals: 2 }
		}
		]
	});

});
</script>
FINN;

	}
	return "ok";
}

?>

</html>