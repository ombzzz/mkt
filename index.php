<?
if( strlen( $_GET["json"] ) > 0 ){
	json( $_GET["json"] );
	return;
}
?>

<html>
<script src="jquery-3.3.1.min.js"></script>
<script src="hs/code/highstock.js"></script>
<script src="hs/code/modules/exporting.js"></script>
<style>
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

	if( $login == "login" ){
		$json = login( $usr, $pas );
		$jsona = json_decode( $json, true );
		$bearer = $jsona["access_token"];
	}
	if( $cotiz == "cotiz" ){
		$cotizx = cotiz( $bearer, $panel );
		$cotiza = json_decode( $cotizx, true );
		$titulos = $cotiza["titulos"];

		//formato del json en la funcion c-otiz
		foreach( $titulos as $key => $value ){
			echo "<div>".$value["simbolo"]."</div>";
		}
	}
	if( $precios == "precios" ){
		$preciosx = precios( $tck, $de, $a, $bearer );
		$preciosa = json_decode( $preciosx, true );
		print_r( $preciosa );
	}
	if( $preciosdb == "preciosdb" ){
		$pra = preciosdb( $tck );
		foreach( $pra as $key => $pr ){
			echo "<div class=precio><span>" . $pr["fec"] . ": " . "</span><span>" 
			 . number_format( (float) $pr["close"], 2, '.', '' ) . "</span></div>";
		}
	}
	if( $graf == "graf" ){
		graf( $tck );
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
		$res = upd( $as, $bearer );
		if( $res == 0 )
			$cant = $cant + 1;
	}
	$pdo = null; //para close connection 
	$pds = null; //para close connection
	loguear( "updall finalizado, $cant assets actualizados" );
	return;

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
		loguear( "updall: error en conn: " . $e->getMessage(), "error" );
		return;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$sql = "select * from as_pr ap where ap.tck = '" . $tck . "' and ap.fec between '" . $de . "' and '" . $a . "' order by fec";
	try {
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "updall: error en sel: " . $e->getMessage(), "error" );
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
	$preciosx = precios( $tck, $de, $a, $bearer );
	$preciosa = json_decode( $preciosx, true );
	if( count( $preciosa ) == 1 && key( $preciosa ) == "message" ){
		loguear( "pers $tck $de $a: problemas en get de precios: " . key( $preciosa ) . "->" . $preciosa["message"], "error" );
		return -1;
	}
	else if( $preciosa[0]["fechaHora"] > 0 ){
	 loguear( "pers $tck $de $a: precios obtenidos, recorriendo" );
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

	try {
		$pdo->commit();
	} catch( PDOException $e ) {
		loguear( "pers $tck $de $a: error en c-ommit: " . $e->getMessage(), "error" );
		return -1;
	}

	loguear( "pers $tck: --commit--" );

	$pdo = null; //para close connection 
	$pds = null; //para close connection

	return $cant;

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
  }
  */
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
//------------------------------------------------
function precios( $tck, $de, $a, $bearer ){
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
	loguear( "precios: volvio curl, devolviendo precios" );
  return $server_output;
}

//------------------------------------------------
// cotiz
//------------------------------------------------
function cotiz( $bearer, $panel ){

	///api/{pais}/Titulos/Cotizacion/Paneles/{instrumento}
	//pais: argentina, brasil, chile, colombia, estados_unidos, mexico, peru
	//instrumento: Acciones, Bonos, Opciones, Monedas, Cauciones, cHPD, Futuros
		
	//GET /api/Cotizaciones/{Instrumento}/{Panel}/{Pais}
	//instrumento: Acciones, Bonos, Opciones, Monedas, Cauciones, cHPD, Futuros
	//panel(argentina): Merval, Panel General, Merval 25, Merval Argentina, Burcap, CEDEARs

	//$url = "https://api.invertironline.com/api/argentina/Titulos/Cotizacion/Paneles/Acciones";
	$panel = str_replace( " ", "%20", $panel );
	$url = "https://api.invertironline.com/api/Cotizaciones/Acciones/$panel/argentina";
	echo "cotiz: $url";

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );
  return $server_output;

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
	<input name=pas value="" type=text>
	<br><input type=submit name=login value=login>
	<hr>
	<label>panel ( Merval, Panel General, Merval 25, Merval Argentina, Burcap, CEDEARs )
	<input name=panel value="$panel" type=text>
	<input type=submit name=cotiz value=cotiz>
	<br>
	<label>tck
	<input name=tck value="$tck" type=text>
	<label>de ( ej 2018-02-25 )  habria desde 3/12/2001
	<input name=de value="$de" type=text>
	<label>a
	<input name=a value="$a" type=text>
	<input type=submit name=precios value=precios>
	<input type=submit name=preciosdb value=preciosdb>
	<input type=submit name=graf value=graf>
	<input type=submit name=last value=last>
	<input type=submit name=pers value=pers>
	<input type=submit name=upd value=upd>
	<input type=submit name=updall value=updall>
	<br>
	<textarea cols=100 name=bearer>$bearer</textarea>
	<hr>
	<input type=submit name=sqlite value=sqlite>
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
function json( $tck ){
	header('Content-type: application/json');
//echo 
//"[
//[1297728000000,51.41]
//]";
//	 return;
//echo "[[1297728000000,51.41]]";
	// return;
	$ret = "[\n";
	$pra = preciosdb( $tck );
	foreach( $pra as $key => $pr ){
		$ret = $ret . "[" . strtotime( $pr["fec"] ) . "000" . "," . number_format( (float) $pr["close"], 2, '.', '' ) . "]";
		if( $key < count( $pra ) - 1 )
			$ret = $ret . ",\n";
	}
	$ret = $ret . "\n]";
	echo $ret;
}


function graf( $tck ){

	echo <<<FINN
<div id="container" style="height: 400px; min-width: 310px"></div>

<script>

$.getJSON( 'index.php?json=$tck', function( data ){
	Highcharts.stockChart('container', {
		rangeSelector: {selected: 1 },

		title: {text: 'molinos semino'},

		series: [
		{
			name: 'semi',
			data: data,
			tooltip: {valueDecimals: 2 }
		}
		]
	});

});
</script>
FINN;
}

?>

</html>