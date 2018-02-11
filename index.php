<html>
<?

main();

//------------------------------------------------
// main
//------------------------------------------------
function main(){

	$login = $_POST["login"];
	$usr = $_POST["usr"];
	$pas = $_POST["pas"];
	$cotiz = $_POST["cotiz"];
	$precios = $_POST["precios"];
	$tck = $_POST["tck"];
	$de = $_POST["de"];
	$a = $_POST["a"];
	$panel = $_POST["panel"];
	$bearer = $_POST["bearer"];
	$sqlite = $_POST["sqlite"];

	$last = $_POST["last"];
	$pers = $_POST["pers"];

	if( $login == "login" ){
		$json = login( $usr, $pas );
		$jsona = json_decode( $json, true );
		//var_dump( $jsona );
		$bearer = $jsona["access_token"];
	}
	if( $cotiz == "cotiz" ){
		$cotizx = cotiz( $bearer, $panel );
		$cotiza = json_decode( $cotizx, true );
		$titulos = $cotiza["titulos"];

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

		foreach( $titulos as $key => $value ){
			echo "<div>".$value["simbolo"]."</div>";
		}
	}
	if( $precios == "precios" ){
		$preciosx = precios( $bearer, $de, $a, $tck );
		$preciosa = json_decode( $preciosx, true );
		print_r( $preciosa );
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

	form( $usr, $pas, $panel, $cotiz, $tck, $de, $a, $bearer );
}


//------------------------------------------------
// pers
//------------------------------------------------
function pers( $tck, $de, $a, $bearer ){

	$preciosx = precios( $tck, $de, $a, $bearer );
	$preciosa = json_decode( $preciosx, true );

	foreach( $preciosa as $key => $value ){
		echo $key . ":" . $value;
	}
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
/*
	$database = 'mkt';
	$dsn = "sqlite:$database.db";

	try {
		$pdo = new PDO( $dsn ); // sqlite
	} catch( PDOException $e ) {
		die ( 'Oops' . $e ); // Exit, displaying an error message
	}

	$tck = strtolower( $tck );
	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$pds = $pdo->query( "select max( fec ) fec from as_pr where tck = '" . $tck. "'" );
	$row = $pds->fetch();
	return $row[ "fec" ];

//	$price=20; $id=3;
//$sql="UPDATE products SET price=$price,modified=now() WHERE id=$id";
//$PDO->exec($sql); // or $rowcount=$PDO->exec($sql);
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
		die ( 'Oops' . $e ); // Exit, displaying an error message
	}

	$tck = strtolower( $tck );
	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$pds = $pdo->query( "select max( fec ) fec from as_pr where tck = '" . $tck. "'" );
	$row = $pds->fetch();
	return $row[ "fec" ];
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
		die ( 'Oops' . $e ); // Exit, displaying an error message
	}

	//pds es un objeto pdostatement , que define interfaz iterable, por eso funciona el foreach 
	$pds = $pdo->query( "select tck from asset" );
	foreach( $pds as $row ){
		echo "<div>" . $row[ "tck" ] . "</div>";
	}

//	$price=20; $id=3;
//$sql="UPDATE products SET price=$price,modified=now() WHERE id=$id";
//$PDO->exec($sql); // or $rowcount=$PDO->exec($sql);

}

//------------------------------------------------
// precios
//------------------------------------------------
function precios( $tck, $de, $a, $bearer ){
	//GET /api/{mercado}/Titulos/{simbolo}/Cotizacion/seriehistorica/{fechaDesde}/{fechaHasta}/{ajustada}
	//mercado: bCBA, nYSE, nASDAQ, aMEX, bCS, rOFX
	$url = "https://api.invertironline.com/api/bCBA/Titulos/$tck/Cotizacion/seriehistorica/$de/$a/ajustada";
	//echo "cotiz: $url";

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Bearer $bearer" ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$server_output = curl_exec( $ch );
	curl_close( $ch );
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
	<input type=submit name=last value=last>
	<input type=submit name=pers value=pers>
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

?>

</html>