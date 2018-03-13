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

.precio{
	white-space: pre-wrap;
	font-family: "courier new";
}

.precioopc{
	white-space: pre-wrap;
	font-family: "courier new";
	color: blue;
	margin-left: 30px;
}

.precio span, .precioopc span{
	margin-left: 5px;
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
	$updvol = $_POST["updvol"];

	$opc_updall = $_POST["opc_updall"];
	$opc_collar = $_POST["opc_collar"];

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
				echo "<div>" . $v["fechaHora"] . ": " . $v["ultimoPrecio"] . " " . $v["obs"] . "</div>";
			}
		}
	}
	if( $preciosdb == "preciosdb" ){
		$pra = preciosdb( trim(strtolower( $tck )), $de, $a, 1 );
		if( !is_array( $pra ) ){
			echo "<div>problemas obteniendo precios</div>";
		}
		else {
			foreach( $pra as $key => $pr ){
				echo "<div class=precio><span>" . $pr["fec"] . "</span>"
				. "<span>" .  str_pad( number_format( (float) $pr["close"], 2, '.', '' ), 10, " ", STR_PAD_LEFT ) . "</span>"
				. "<span> " . str_pad( number_format( (float) $pr["montop"], 2, '.', '' ), 15, " ", STR_PAD_LEFT ). "</span>"
				. "</div>";
			}
		}
	}
	if( $graf == "graf" ){
		if( preg_match( "/merval/i", $tck ) == 0 )
			$tck = $tck . " merval";

		graf( trim( strtolower( $tck )), $de, $a );
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
	if( $updvol == "updvol" ){
		$cant = updvol(  $tck, $de, $a, $bearer );
		echo "<div>$cant precios actualizados</div>";
	}

	if( $opc_updall == "opc_updall" ){
		$cant = opc_updall( $bearer );
		echo "<div>$cant precios actualizados</div>";
	}

	if( $opc_collar == "opc_collar" ){
		opc_collar( $tck, $hoy );
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

	$asx = assets( 'bcba', 'todos', 'acc' );

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
function assets( $mkt = 'todos', $tck = 'todos', $tipo = 'todos', $getopc = 0 ){
	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "ass: error en conn: " . $e->getMessage(), "error" );
		return false;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if( $tipo == 'todos' )
		$criterio2 = "";
	else if( strlen( $tipo ) > 0 )
		$criterio2 = "and tipo = '" . $tipo . "'";
	else {
		loguear( "ass: por favor indique tipo", "error" );
		return false;
	}

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	try {
		if( $getopc ){
			//llamador nos pasa un ticker de una accion y nos pide le devolvamos todas las opciones de esa accion

			if( $tipo <> 'opc' ){
				loguear( "ass: para acciones de opcion por favor pasar tipo opc, se recibio $tipo", "error" );
				return false;
			}

			if( $tck == 'todos' )
				$criterio = "";
			else if( strlen( $tck ) > 0 )
				$criterio = "and substr( a.tck, 1, 3 ) = ( select b.opctck from asset b where b.tipo = 'acc' and b.tck = '" . $tck . "' )";
			else {
				loguear( "ass: por favor indique tck", "error" );
				return false;
			}

			$sql = "select * from asset a where ( a.mkt = '" . $mkt . "' or 'todos' = '"  . $mkt . "' ) "
			 . " $criterio $criterio2 order by a.tck";
		}
		else {
			if( $tck == 'todos' )
				$criterio = "";
			else if( strlen( $tck ) > 0 )
				$criterio = "and tck = '" . $tck . "'";
			else {
				loguear( "ass: por favor indique tck", "error" );
				return false;
			}

			$sql = "select * from asset a where ( a.mkt = '" . $mkt . "' or 'todos' = '"  . $mkt . "' ) "
			 . " $criterio $criterio2 order by a.tck";
		}
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
// cotizdb
//
// a diferencia de preciosdb devuelve solo el precio ( es decir un escalar, no un a-rray )
//------------------------------------------------
function cotizdb( $tck, $tp = "close", $fec = "hoy" ){
	$pra = preciosdb( $tck, $fec, $fec );
	if( !is_array( $pra ) && $pra == false ){
		return -1;
	}
	if( count($pra) )
		return $pra[0][$tp];
	else
		return -2;
}

//------------------------------------------------
// preciosdb
//------------------------------------------------
function preciosdb( $tck, $de = "2018-01-01", $a = "hoy", $desc = 0 ){
	if( $a == "hoy" )
		$a = fecha_hoy();

	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "pdb: error en conn: " . $e->getMessage(), "error" );
		return false;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if( $desc )
		$descx = "order by fec desc";
	else
		$descx = "order by fec";

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$sql = "select * from as_pr ap where ap.tck = '" . $tck . "' and not ( ap.obs = 'opc' and ap.close = 0 ) and ap.fec between '" . $de . "' and '" . $a . "' $descx";
	try {
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "pdb: error en sel: " . $e->getMessage() . " sql es $sql", "error" );
		return false;
	}

	$i = 0;
	foreach( $pds as $row ){
		$res[$i++] = $row;
	}

	if( $i == 0 )
		$res = [];

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
// upd
//------------------------------------------------
function opc_updall( $bearer ){
	loguear( "opc_upda: invocado" );

	$url = "https://api.invertironline.com/api/Cotizaciones/Opciones/De%20Acciones/argentina";
	loguear( "opc_upda: $url" );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );

	$preciosa = json_decode( $server_output, true );
	if( !is_array( $preciosa ) ){
		loguear( "opc_upda: no vino un array", "error" );
		return -1;
	}
	else if( count( $preciosa ) == 1 && "xx" . key( $preciosa ) == "xxmessage" ){
		loguear( "opc_upda: error: " . $preciosa["message"], "error" );
		return false;
	}
	else if( count( $preciosa ) == 0 ){
		loguear( "opc_upda: array con cero elementos", "error" );
	}

	$database = 'mkt';
	$dsn = "sqlite:$database.db";
	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		loguear( "opcupd $tck: error en conn: " . $e->getMessage(), "error" );
		return;
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $pdo->beginTransaction();

	//"titulos": [
  //  {
  //      "simbolo": "AGRC17.0AB",
  //      "puntas": {
  //          "cantidadCompra": 5,
  //          "precioCompra": 2.55,
  //          "precioVenta": 3.4,
  //          "cantidadVenta": 2
  //      },
  //      "ultimoPrecio": 3.2,
  //      "variacionPorcentual": 6.66,
  //      "apertura": 3.6,
  //      "maximo": 3.6,
  //      "minimo": 2.4,
  //      "ultimoCierre": 3.2,
  //      "volumen": 134,
  //      "cantidadOperaciones": 17,
  //      "fecha": "2018-03-09T17:00:16.053",
  //      "tipoOpcion": null,
  //      "precioEjercicio": null,
  //      "fechaVencimiento": null,
  //      "mercado": "BCBA",
  //      "moneda": "AR$"
  //  }
  //]
  $cant = 0;
	foreach( $preciosa["titulos"] as $key => $value ){

		$tck = strtolower( $value["simbolo"] );
		$fec = substr( $value["fecha"], 0, 10 );
		$open = $value["apertura"];
		$close = $value["ultimoPrecio"];
		$min = $value["minimo"];
		$max = $value["maximo"];
		$vol = $value["volumen"];
		$cantop = $value["cantidadOperaciones"];
		$montop = $vol * 100 * (( $max + $min ) / 2 );
		$bid = $value["puntas"]["precioCompra"];
		$bidq = $value["puntas"]["cantidadCompra"];
		$ask = $value["puntas"]["precioVenta"];
		$askq = $value["puntas"]["cantidadVenta"];

		$sql = <<<FINN
		insert or replace into as_pr( tck, fec, open, close, min, max, vol, montop, obs, cantop, bid, bidq, ask, askq ) values (
			'$tck',
			'$fec',
			$open,
			$close,
			$min,
			$max,
			$vol,
			$montop,
			'opc',
			$cantop,
			$bid,
			$bidq,
			$ask,
			$askq
		)
FINN;

		try {
			$pdo->exec( $sql );
		} catch( PDOException $e ) {
			$pdo->rollback();
			loguear( "opc_upda: error en ins: " . $e->getMessage() . " sql $sql", "error" );
			return -1;
		}

		$sql = <<<FINN
		insert or replace into asset( tck, den, mkt, tipo ) values (
			'$tck',
			'$tck',
			'bcba',
			'opc'
		)
FINN;

		try {
			$pdo->exec( $sql );
		} catch( PDOException $e ) {
			$pdo->rollback();
			loguear( "opc_upda: error en ins: " . $e->getMessage() . " sql $sql", "error" );
			return -1;
		}

		$cant++;
	}	if($cant){
		try {
			$pdo->commit();
		} catch( PDOException $e ) {
			loguear( "opc_upda:: error en c-ommit: " . $e->getMessage(), "error" );
			return -1;
		}
		loguear( "opc_upda: --commit--" );
	}
	
	$pdo = null; //para close connection 
	$pds = null; //para close connection

	return $cant;
}

//------------------------------------------------
// opc strike
//
// recibe el string de un ticker de opciones, le extrae el precio
//  de strike y lo devuelve
//------------------------------------------------
function opc_strike( $opc ){
	preg_match( "/[a-zA-Z]+([0-9.]+)[a-zA-Z][a-zA-Z]/", $opc, $matches );
	return $matches[1] . "00";
}

//------------------------------------------------
// opc calls
//
// recibe un ticker de share y una fecha, devuelve sus calls que tengan cotizacion para esa fecha
//------------------------------------------------
function opc_calls( $tck, $fec, $conbid = 0, $conask = 0 ){

	$asx = assets( 'todos', $tck );
	$opcpref = $asx[0]["opctck"];

	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		die ( 'Oops' . $e->getMessage() ); // Exit, displaying an error message
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if( $conbid )
		$criterio = " and bid <> 0";
	if( $conask )
		$criterio = " and ask <> 0";

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$sql = "select ap.tck from as_pr ap where ap.fec = '$fec' and ap.obs = 'opc' and substr( ap.tck, 1, length( '$opcpref' ) ) = '$opcpref' and substr( ap.tck, length( '$opcpref') + 1, 1 ) = 'c' and ap.close <> 0 $criterio order by ap.tck";

	try {
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "opcc: error en sel: " . $e->getMessage() . " sql es $sql", "error" );
		return;
	}

	$i = 0;
	foreach( $pds as $row ){
		$adevolver[$i++] = $row[ "tck" ];
	}
	$pdo = null; //para close connection 
	$pds = null; //para close connection

	if( $i == 0 )
		$adevolver = [];

	return $adevolver;
}

//------------------------------------------------
// opc puts
//
// recibe un ticker de share y una fecha, devuelve sus calls que tengan cotizacion para esa fecha
//------------------------------------------------
function opc_puts( $tck, $fec, $conbid = 0, $conask = 0 ){

	$asx = assets( 'todos', $tck );
	$opcpref = $asx[0]["opctck"];

	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		die ( 'Oops' . $e->getMessage() ); // Exit, displaying an error message
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if( $conbid )
		$criterio = " and bid <> 0";
	if( $conask )
		$criterio = " and ask <> 0";

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$sql = "select ap.tck from as_pr ap where ap.fec = '$fec' and ap.obs = 'opc' and substr( ap.tck, 1, length( '$opcpref' ) ) = '$opcpref' and substr( ap.tck, length( '$opcpref') + 1, 1 ) = 'v' and ap.close <> 0 $criterio order by ap.tck";

	try {
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "opcc: error en sel: " . $e->getMessage() . " sql es $sql", "error" );
		return;
	}

	$i = 0;
	foreach( $pds as $row ){
		$adevolver[$i++] = $row[ "tck" ];
	}
	$pdo = null; //para close connection 
	$pds = null; //para close connection

	if( $i == 0 )
		$adevolver = [];

	return $adevolver;
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
	 loguear( "pers $tck $de $a: " . count( $preciosa ) . " precios obtenidos <- se recorreran e insertaran" );
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
		if( $value["sinvol"] )
			$obs = "sinvol";
		else 
			$obs = "";

		$sql = <<<FINN
		insert into as_pr( tck, fec, open, close, min, max, vol, montop, obs ) values (
			'$tck',
			'$fec',
			$open,
			$close,
			$min,
			$max,
			$vol,
			$montop,
			'$obs'
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

	if( strlen( $tck ) > 0 && strlen( $de ) > 0 && strlen( $a ) > 0 && strlen( $bearer ) > 0 )
		$dummy = 0;
	else {
		loguear( "precios: algun param nulo tck $tck de $de a $a bearer $bearer", "error" );
		return false;
	}

	$hayprecios = 0;
	$tck = strtoupper($tck);

	//el rest no devuelve el hasta inclusive, por eso sumamos uno
	$ax = fecha_offset( $a, 1 );

	//GET /api/{mercado}/Titulos/{simbolo}/Cotizacion/seriehistorica/{fechaDesde}/{fechaHasta}/{ajustada}
	//mercado: bCBA, nYSE, nASDAQ, aMEX, bCS, rOFX
	$url = "https://api.invertironline.com/api/bCBA/Titulos/$tck/Cotizacion/seriehistorica/$de/$ax/ajustada";
	loguear( "precios $tck: $url" );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );

	$preciosa = json_decode( $server_output, true );
	if( count( $preciosa ) == 1 && "xx" . key( $preciosa ) == "xxmessage" ){
		loguear( "precios $tck: error: " . $preciosa["message"], "error" );
		return false;
	}
	else if( count( $preciosa ) == 0 ){
		loguear( "precios $tck: sin precios historicos para $tck" );
	}
	else if( $preciosa[0]["fechaHora"] > 0 ){
		$hayprecios = 1;
	}
	else {
		//loguear( "precios: error: no se recibio array bien formado, sino " . var_dump( $precios ), "error" );
		return false;
	}

	if( $a == fecha_hoy() ){
		loguear( "precios $tck: $a es hoy => llamando a cotiz" );
		//serie historica no devuelve el precio de hoy, lo buscamo434s por cotizacion
		$cotiz = cotiz( $bearer, $tck );
		if( !is_array( $cotiz ) && $cotiz == false ){
			loguear( "precios $tck: problemas en cotiz, no volvio un array", "error" );
			return false;
		}

		$ulth = substr( $preciosa[ count($preciosa)-1 ]["fechaHora"], 0, 10 );
		$ultc = substr( $cotiz["fechaHora"], 0, 10 );
		//en algun momento la historica lo trae, chequeamos si lo trajo a hoy, para no meterlo dos veces en el a-rray
		//ademas chequeamos que la cotiz recibida no sea anterior al hasta solicitado
		if( $ultc != $ulth && $ultc >= $a ){
			$cotiz["sinvol"] = 1;
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
	$tck = strtoupper($tck);
	$url = "https://api.invertironline.com/api/bCBA/Titulos/$tck/Cotizacion";

	loguear( "cotiz: $url");

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
	<input type=submit name=opc_updall value=opc_updall>
	<input type=submit name=opc_collar value=opc_collar>
	<hr>
	<input type=submit name=sqlite value=sqlite>
	<input type=submit name=updvol value=updvol>
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
	if( !is_array( $pra ) && $pra == false ){
		return false;
	}

	foreach( $pra as $key => $pr ){
		$ret = $ret . "[" . strtotime( $pr["fec"] ) . "000" . "," 
		 . number_format( (float) $pr["close"], 2, '.', '' ) . "]";
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
		$asx = assets( 'todos', $value );
		$den = $asx[0]["den"];
		$tx = $asx[0]["tck"];

		$url = "index.php?json=$tx&de=$de&a=$a";

		echo <<<FINN
<div id="container_$tx" style="height: 350px; min-width: 310px"></div>

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

	foreach ( $as as $key => $value) {
		$asx = assets( 'todos', $value );
		$den = $asx[0]["den"];
		$tx = $asx[0]["tck"];

		$pra = preciosdb( $tx, $de, $a, 1 );
		if( !is_array( $pra ) && $pra == false ){
			return "error en preciosdb";
		}

		$cant = 0;
		if( count($pra)){
			echo "<div class=precio><span>" . str_pad( "tck fec", 20, " ", STR_PAD_LEFT ). "</span>"
			. "<span>" .  str_pad( "close", 10, " ", STR_PAD_LEFT ) . "</span>"
			. "<span>" . str_pad( "montop", 20, " ", STR_PAD_LEFT ). "</span>"
			. "<span>" . str_pad( "vol", 18, " ", STR_PAD_LEFT ). "</span>"
			. "<span>" . str_pad( "obs", 10, " ", STR_PAD_LEFT ) . "</span>"
			. "</div>";

			foreach( $pra as $key => $pr ){
				if( $cant == 0 )
					$fechaparaopc = $pr["fec"];

				echo "<div class=precio><span>" . str_pad( $tx . " " . $pr["fec"], 20, " ", STR_PAD_LEFT ). "</span>"
				. "<span>" .  str_pad( number_format( (float) $pr["close"], 2, '.', ',' ), 10, " ", STR_PAD_LEFT ) . "</span>"
				. "<span>" . str_pad( number_format( (float) $pr["montop"], 2, '.', ',' ), 20, " ", STR_PAD_LEFT ). "</span>"
				. "<span>" . str_pad( number_format( (float) $pr["vol"], 0, '.', ',' ), 18, " ", STR_PAD_LEFT ). "</span>"
				. "<span>" . str_pad( $pr["obs"], 10, " ", STR_PAD_LEFT ) . "</span>"
				. "</div>";
				$cant++;

			} //fin recorrida de precios
		}
		//mostramos las opciones del share
		$opca = assets( 'todos', $tx, 'opc', 1 );
		if( count($opca ) ){
					echo "<div class=precioopc><span>" . str_pad( "tck fec", 25, " ", STR_PAD_LEFT ) . "</span>"
				. "<span>" .  str_pad( "close", 10, " ", STR_PAD_LEFT ) . "</span>"
				. "<span>" . str_pad( "montop", 15, " ", STR_PAD_LEFT ). "</span>"
				. "<span>" . str_pad( "vol", 10, " ", STR_PAD_LEFT ). "</span>"
				. "<span>" . str_pad( "obs", 10, " ", STR_PAD_LEFT ) . "</span>"
				. "<span>" . str_pad( "cantop", 10, " ", STR_PAD_LEFT ) . "</span>"
				. "<span>" . str_pad( "bid", 10, " ", STR_PAD_LEFT ) . str_pad( "bidq", 10, " ", STR_PAD_LEFT ) . "</span>"
				. "<span>" . str_pad( "ask", 10, " ", STR_PAD_LEFT ) . str_pad( "askq", 10, " ", STR_PAD_LEFT ) . "</span>"
				. "</div>";

			foreach( $opca as $key2 => $opc ){
				$popc = preciosdb( $opca[$key2]["tck"], $fechaparaopc, $fechaparaopc, 1 );
				if( !is_array( $popc ) && $popc == false ){
					return "error (2) en preciosdb";
				}

				if( count($popc) ){
					echo "<div class=precioopc><span>" . str_pad( $opca[$key2]["tck"] . " " . $fechaparaopc, 25, " ", STR_PAD_LEFT ) . "</span>"
					. "<span>" .  str_pad( number_format( (float) $popc[0]["close"], 2, '.', ',' ), 10, " ", STR_PAD_LEFT ) . "</span>"
					. "<span>" . str_pad( number_format( (float) $popc[0]["montop"], 2, '.', ',' ), 15, " ", STR_PAD_LEFT ). "</span>"
					. "<span>" . str_pad( number_format( (float) $popc[0]["vol"], 0, '.', ',' ), 10, " ", STR_PAD_LEFT ). "</span>"
					. "<span>" . str_pad( $popc[0]["obs"], 10, " ", STR_PAD_LEFT ) . "</span>"
					. "<span>" . str_pad( number_format( (float) $popc[0]["cantop"], 0, '.', ',' ), 10, " ", STR_PAD_LEFT ). "</span>"
					. "<span>" . str_pad( number_format( (float) $popc[0]["bid"], 2, '.', ',' ), 10, " ", STR_PAD_LEFT ) 
					 . str_pad( "[" . number_format( (float) $popc[0]["bidq"], 0, '.', ',' ) . "]", 10, " ", STR_PAD_LEFT ) . "</span>"
					. "<span>" . str_pad( number_format( (float) $popc[0]["ask"], 2, '.', ',' ), 10, " ", STR_PAD_LEFT )
				 	 . str_pad( "[" . number_format( (float) $popc[0]["askq"], 0, '.', ',' ) . "]", 10, " ", STR_PAD_LEFT ) . "</span>"
					. "</div>";
				} //fin hay precio <> 0 para la opcion
			} //fin recorrida de opciones
		} //fin hay opciones para la accion
		opc_collar($tx, $fechaparaopc );
	} //fin recorrida de assets
	return "ok";
}

//------------------------------------------------
// updvol
//------------------------------------------------
function updvol( $tck, $de = "2018-01-01", $a = "hoy", $bearer ){
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

  $pdo->beginTransaction();

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$sql = "select * from as_pr ap where ap.tck = '" . $tck . "' and ap.fec between '" . $de . "' and '" . $a . "' and obs = 'sinvol' order by fec desc";
	try {
		$pds = $pdo->query( $sql );
	} catch( PDOException $e ) {
		loguear( "updv: error en sel: " . $e->getMessage() . " sql es $sql", "error" );
		return;
	}

	$cant = 0;
	foreach( $pds as $row ){
		$preciosa = precios( $tck, $row["fec"], $row["fec"], $bearer );
		if( $preciosa ){

			$open = $preciosa[0]["apertura"];
			$close = $preciosa[0]["ultimoPrecio"];
			$min = $preciosa[0]["minimo"];
			$max = $preciosa[0]["maximo"];
			$vol = $preciosa[0]["volumenNominal"];
			$montop = $preciosa[0]["montoOperado"];
			$fec = $row["fec"];

			$sql = <<<FINN
			update as_pr set 
				open = $open,
				close = $close,
				min = $min,
				max = $max,
				vol = $vol,
				montop = $montop,
				obs = null
			where tck = '$tck'
				and fec = '$fec';
FINN;

			try {
				$pdo->exec( $sql );
			} catch( PDOException $e ) {
				$error = $e->getMessage();
				$pdo->rollback();
				loguear( "pers: error en ins: " . $error . " sql $sql", "error" );
				return -1;
			}
			$cant++;
		}
	}

	if( $cant ){
		try {
			$pdo->commit();
		} catch( PDOException $e ) {
			loguear( "pers $tck $de $a: error en c-ommit: " . $e->getMessage(), "error" );
			return -1;
		}
	}
	
	$pdo = null; //para close connection 
	$pds = null; //para close connection


	return $cant;
}

//------------------------------------------------
// opc collar
//------------------------------------------------
function opc_collar( $tck, $fec = "0", $lotes = 1, $debug = 0 ){
	echo "<div class=debug>collar invocado para $lotes lotes de $tck</div>";

	//yo llamo "collar" a lo siguiente,,,no se si es lo que verdaderamente significa, pero por ahora le pongo
	// ese nombre a falta de otro :-) (orl, 10032018)		
	// --https://docs.google.com/spreadsheets/d/1H_kYMdOdtQfdzTahXCtJj3SpL9LJa3lkIyIM4CSPod0/edit#gid=416901632
	//						
	//comision min(iva incl) 42.35(k4)
	//comision(iva incl) 0.01 (k6)
	//
	//apbr(shr) 			cotiza a 	148.5(g4)		
	//pbrc130.ab(call)	tiene strike	130(e5)	cotiza a	24(g5) <- el strike debe ser mayor a precioef
	//pbrv130.ab(put)	tiene strike	130(e6)	cotiza a	1.7(g6) <- el strike debe ser mayor a precioef

	//compro	100(d9=d10*100)	shares de	(shr)
	//lanzo	1(d10)	lote de	(call)
	//compro	1(d12)	lote de	(put)
	//precioef = 128.1085 k9=G4*(1+K6)+if(G6*K6*D9<K4,G6+K4/D9,G6*(1+K6))-G5

	//ESCEN 1	pbr cotiza a mas de 	130(e5)
	//entonces me ejercen el call
	//precioef = 128.7 k16=E5*(1-K6)
	//resultado = 0.46% k17=(K16-K9)/K9
	

	//ESCEN 2	apbr 	cotiza a menos de 130(e6)
	//entonces ejerzo	el put
	//precioef = 128.7 =E6*(1-K6) 
	//resultado = 0.46% k23=(K22-K9)/K9

	//***
	if( $fec == "0" )
		$fec = fecha_hoy();

	$calls = opc_calls( $tck, $fec, 1 );
	if( count($calls) == 0 ){
		echo "<div class=debug> sin calls para $tck $fec</div>";
		return 0;
	}

	foreach( $calls as $key => $call ){
		$puts = opc_puts( $tck, $fec, 0, 1 );
		if( count($puts) == 0 ){
			echo "<div class=debug>sin puts para $tck $fec</div>";
			return 0;
		}

		foreach( $puts as $key2 => $put ){

			//comision min(iva incl) 42.35(k4)
			$k4 = 42.35;

			if( $debug )
			echo "<div class=debug>comision min(iva incl) $k4 </div>";

			//comision(iva incl) 0.01 (k6)
			$k6 = 0.01;

			if( $debug )
			echo "<div class=debug>comision(iva incl) $k6</div>";

			//apbr(shr) 			cotiza a 	148.5(g4)		
			$shr= $tck;
			$g4 = cotizdb( $shr, "close", $fec );
			if( $g4 < 0 ){
				loguear( "collar: cotizdb volvio con $g4 para $shr $fec", "error" );
				return 1;
			}

			if( $debug )
			echo "<div class=debug>shr $shr cotiza $fec a $g4</div>";

			//pbrc130.ab(call)	tiene strike	130(e5)	cotiza a	24(g5) <- el strike debe ser mayor a precioef
			//$call = "pbrc130.ab";
			$e5 = opc_strike( $call );
			$g5 = cotizdb( $call, "bid", $fec );
			if( $g5 < 0 ){
				loguear( "collar: cotizdb volvio con $g5 para $call $fec", "error" );
				return 1;
			}

			if( $debug )
			echo "<div class=debug>call $call tiene strike	$e5 cotiza a $g5 <- el strike debe ser mayor a precioef</div>";

			//pbrv130.ab(put)	tiene strike	130(e6)	cotiza a	1.7(g6) <- el strike debe ser mayor a precioef
			//$put = "pbrv130.ab";
			$e6 = opc_strike( $put );
			$g6 = cotizdb( $put, "ask", $fec );
			if( $g6 < 0 ){
				loguear( "collar: cotizdb volvio con $g6 para $call $fec", "error" );
				return 1;
			}
			if( $debug )
			echo "<div class=debug>put $put tiene strike	$e6 cotiza a $g6 <- el strike debe ser mayor a precioef</div>";

			//lanzo	1(d10)	lote de	(call)
			$d10 = $lotes;
			//compro	1(d12=d10)	lote de	(put)
			$d12 = $d10;
			//compro	100(d9=d10*100)	shares de	(shr)
			$d9 = $d10 * 100;

			//precioef = 128.1085 k9=G4*(1+K6)+if(G6*K6*D9<K4,G6+K4/D9,G6*(1+K6))-G5
			$k9 = $g4*(1+$k6);
			if( $g6 * $k6 * $d9 < $k4 )
				$aux = $g6 + $k4 / $d9;
			else
				$aux = $g6 * ( 1 + $k6 );

			$k9 = $k9 + $aux - $g5;
			$precioef = $k9;
			if( $debug )
			echo "<div class=debug>precioef_shr = $precioef = k9 = G4 $g4 * ( 1 + k6 $k6 ) + if ( g6 $g6 * k6 $k6 * d9 $d9 < k4 $k4, g6 $g6 + k4 $k4 / d9 $d9, g6 $g6 * (1+ k6 $k6 ) ) - g5 $g5 </div>";

			//los strikes deben ser mayor a precioef
			if( $e5 > $precioef && $e6 > $precioef )
				$dummy = 1;
			else {
				if( $debug )
				echo "<div class=collar>FAIL $shr $call $put: precioef $precioef e5 $e5 e6 $e6</div>\n";
				continue;
			}

			//ESCEN 1	pbr cotiza a mas de 	130(e5)
			//entonces me ejercen el call
			//precioef = 128.7 k16=e5*(1-k6)
			$k16 = $e5 * ( 1 - $k6);
			$precioef_esc1 = $k16;
			if( $debug )
			echo "<div class=debug>precioef_esc1 = $k16 = k16 = e5 $35 * ( 1 - k6 $k6 )</div>";

			//resultado = 0.46% k17=(k16-k9)/k9
			$k17 = ( $k16 - $k9 ) / $k9;
			$k17 = round( $k17, 4 );
			$result_esc1 = $k17;
			if( $debug )
			echo "<div class=debug>result_esc1 = $k17 = k17 = ( k16 $k16 - k9 $k9 ) / k9 $k9</div>";
			
			//ESCEN 2	apbr 	cotiza a menos de 130(e6)
			//entonces ejerzo	el put
			//precioef = 128.7 k22=e6*(1-k6) 
			$k22 = $e6 * ( 1 - $k6 );
			$precioef_esc2 = $k22;
			if( $debug )
			echo "<div class=debug>precioef_esc2 = $k22 = k22 = e6 $36 * ( 1 - k6 $k6 )</div>";

			//resultado = 0.46% k23=(k22-k9)/k9
			$k23 = ($k22-$k9 ) / $k9;
			$k23 = round( $k23, 4 );
			$result_esc2 = $k23;
			if( $debug )
			echo "<div class=debug>result_esc2 = $k23 = k23 = ( k22 $k22 - k9 $k9 ) / k9 $k9</div>";

			if( ( $result_esc1 > 0 and $result_esc2 > 0 ) || $debug ) {
				$result_esc1pje = number_format( (float) $result_esc1 * 100, 2, '.', '' ) . "%";
				$result_esc2pje = number_format( (float) $result_esc2 * 100, 2, '.', '' ) . "%";
			}
			echo "<div class=collar>$shr $call $put: $result_esc1pje $result_esc2pje</div>\n";
		} //fin recorrida de puts
	} //fin recorrida de calls
	return;
}

?>

</html>