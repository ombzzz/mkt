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
	$bearer = $_POST["bearer"];

	if( $login == "login" ){
		$json = login( $usr, $pas );
		$jsona = json_decode( $json, true );
		//var_dump( $jsona );
		$bearer = $jsona["access_token"];
	}
	if( $cotiz == "cotiz" ){
		$cotizx = cotiz( $bearer );
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
		$preciosx = precios( $bearer, $tck );
		$preciosa = json_decode( $preciosx, true );
		//$titulos = $a["titulos"];
		print_r( $preciosa );

		/*
	 {
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

	form( $usr, $pas, $bearer );
}


//------------------------------------------------
// precios
//------------------------------------------------
function precios( $bearer, $tck ){
	//GET /api/{mercado}/Titulos/{simbolo}/Cotizacion/seriehistorica/{fechaDesde}/{fechaHasta}/{ajustada}
	//mercado: bCBA, nYSE, nASDAQ, aMEX, bCS, rOFX
	$url = "https://api.invertironline.com/api/bCBA/Titulos/$tck/Cotizacion/seriehistorica/2018-02-05/2018-02-08/ajustada";
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
function cotiz( $bearer ){

	///api/{pais}/Titulos/Cotizacion/Paneles/{instrumento
	//pais: argentina, brasil, chile, colombia, estados_unidos, mexico, peru
	//instrumento: Acciones, Bonos, Opciones, Monedas, Cauciones, cHPD, Futuros
		
	//GET /api/Cotizaciones/{Instrumento}/{Panel}/{Pais}
	//instrumento: Acciones, Bonos, Opciones, Monedas, Cauciones, cHPD, Futuros
	//panel(argentina): Merval, Panel General, Merval 25, Merval Argentina, Burcap, CEDEARs

	//$url = "https://api.invertironline.com/api/argentina/Titulos/Cotizacion/Paneles/Acciones";
	$url = "https://api.invertironline.com/api/Cotizaciones/Acciones/Merval/argentina";
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
// form
//------------------------------------------------
function form( $usr, $pas, $bearer ){

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
	<input type=submit name=cotiz value=cotiz>
	<br>
	<label>tck
	<input name=tck value="$tck" type=text>
	<input type=submit name=precios value=precios>
	<br>
	<textarea cols=100 name=bearer>$bearer</textarea>
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