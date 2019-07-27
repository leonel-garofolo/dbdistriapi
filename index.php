<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('mssql.charset', 'utf-8');
ini_set('memory_limit', '1024M');
error_reporting(E_ALL);
	
header("Access-Control-Allddow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
header("Access-Control-Allow-Headers: X-Requested-With");
header('Content-Type: text/html; charset=utf-8');
header('P3P: CP="IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA"');

include_once 'include/Config.php';
include_once 'include/DbHandler.php';
include_once 'include/Model.php';

require 'libs/PhpMailer/class.phpmailer.php';
require 'libs/PhpMailer/class.smtp.php';
require 'libs/PhpMailer/PHPMailerAutoload.php';


require 'libs/Slim/vendor/autoload.php';
require 'libs/Slim/vendor/slim/slim/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
  
$app = new \Slim\Slim(); 

$app->get('/auto', function () 
{

$response = array();
//$db = new DbHandler();


$autos = array(
array('make'=>'Toyota', 'model'=>'Corolla', 'year'=>'2006', 'MSRP'=>'18,000'),
array('make'=>'Nissan', 'model'=>'Sentra', 'year'=>'2010', 'MSRP'=>'22,000')
);

$response["error"] = false;
$response["message"] = "Autos cargados: " . count($autos); //podemos usar count() para conocer el total de valores de un array
$response["autos"] = $autos;

echoResponse(200, $response);
});

// install sudo apt-get install php5.6-curl
$app->get('/TestNotification', function()
{
	// token user request
	$to = getToken('admin');
	$obj = json_decode('{id=1}');
	$result = pushNotification($to, "Autorización", "Se ha aprovado la autorización de leonel.", $obj);	
	echo $result;
});

$app->post('/AutorizationPending/token/:userName/:userPass/:tokenApi', function($userName, $userPass, $tokenApi) use ($app)
{
	try 
	{
		$response = array();
		$entityBody = file_get_contents('php://input');
		
		//Almacenamos en base de datos
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('mssql.charset', 'utf-8');
		ini_set('memory_limit', '1024M');
		error_reporting(E_ALL);

		$exist = 0;
		$query = mssql_query(sprintf("SELECT count(username) as total FROM APP_USER_PROFILE where username = '%s'",$userName));
		$clientes = array();
		if (!is_null($query))
		{
			while ($row = mssql_fetch_assoc($query)) 
			{
				$exist =$row['total'];					
			}			
		}
		
		$query_header = sprintf("insert into APP_USER_PROFILE (username, userpass, token, update_token) values('%s', '%s', '%s', GETDATE())",
			$userName, //id user
			$userPass,
			$tokenApi
		);
		if($exist > 0){
			$query_header = sprintf("update APP_USER_PROFILE set token = '%s', update_token = GETDATE() where username = '%s'",				
			$tokenApi,
			$userName //id userd
			);
		}
		mssql_query("BEGIN TRAN");
			mssql_query($query_header) or die(mssql_get_last_message());			
		mssql_query("COMMIT");		
		mssql_close($link);	
	} 
	catch (Exception $e) 
	{
		return echoResponse(201, null);
	} 
	finally 
	{
		return echoResponse(200, null);
	}
});

function getToken($userName){
	$token = '';

	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD) or die("Couldn't connect to SQL Server on $myServer. Error: " . mssql_get_last_message());;
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query("SELECT token FROM APP_USER_PROFILE where USERNAME = '".$userName."'");	
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{

			$token = $row['token'];				
		}					
		mssql_free_result($query);
		mssql_close($link);
	}

	return trim($token);
}

function pushNotification($token, $title, $message, $data){
	$url = 'https://fcm.googleapis.com/fcm/send';
	$registrationIds = '';

	// prepare the message
	$message = array( 
		"title" => $title,
		"body" => $message,
		"vibrate" => 1,
		"sound" => 1	
	);
	
	$root = array(
		'to'             => $token,
		'notification'      => $message,
		'data'      => $data
	);
	
	//Clave del servidor
	$headers = array( 
		//'Authorization: Bearer 64a2fab43c8492f3ab44874d019ec7fd18aa1eb1', 
		'Authorization:key=AAAADxg6luo:APA91bF3vV_r-MaQ7JLHhsUu178wPcaTGDF5gRUozyuHSOc6GKDyyE_zbK4qlrpqaFQQ65jYLbMjJfe5FrL9B7wDhXIq6xLm7zm5OyG_oZOf37ovXMDvBYiM4ACVf43Db59Q_VGzlMH4',
		'Content-Type: application/json'
	);

	$ch = curl_init();
	curl_setopt( $ch,CURLOPT_URL,$url);
	curl_setopt( $ch,CURLOPT_POST,true);
	curl_setopt( $ch,CURLOPT_HTTPHEADER,$headers);
	curl_setopt( $ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER,false);
	curl_setopt( $ch,CURLOPT_POSTFIELDS,json_encode($root));
	$result = curl_exec($ch);
	curl_close($ch);
	echo $result;
}

$app->get('/Clients', 'authenticate', function ()  use ($app) 
{	
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD) or die("Couldn't connect to SQL Server on $myServer. Error: " . mssql_get_last_message());;
	mssql_select_db(DB_NAME, $link);
	$queryString = sprintf("SELECT c.*, 
		cast(STUFF(( 
		SELECT distinct '|' + gv03.COD_ARTICU + '#' + prod.DESCRIPCION COLLATE Modern_Spanish_CI_AS			
			from GVA03 gv03 
			inner join GVA21 gv21 on gv21.NRO_PEDIDO = gv03.NRO_PEDIDO
			inner join APP_PRODUCTOS prod on prod.COD_ARTICULO = gv03.COD_ARTICU
			where gv21.COD_CLIENT = c.COD_CLIENT and gv21.FECHA_PEDI >=  DateAdd(MM, -6, GetDate()) 
			FOR XML PATH('') ) , 1, 1, '') as varchar(max)
			) as hist 
	FROM APP_CLIENTES c");

	$query = mssql_query($queryString);
	$clientes = array();
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{
			try{
				$cliente = new Client;
				$cliente->nom_com  = utf8_encode($row['nom_com']);
				$cliente->cod_client  = utf8_encode($row['cod_client']);
				$cliente->cod_vended  = utf8_encode($row['cod_vended']);
				$cliente->nro_lista  = utf8_encode($row['nro_lista']);
				$cliente->cuit  = utf8_encode($row['cuit']);
				$cliente->dir_com  = utf8_encode($row['dir_com']);
				$cliente->e_mail  = utf8_encode($row['e_mail']);
				$cliente->localidad  = utf8_encode($row['localidad']);
				$cliente->razon_soci  = utf8_encode($row['razon_soci']);
				$cliente->telefono_1  = utf8_encode($row['telefono_1']);
				$cliente->cupo_credi  = utf8_encode($row['cupo_credi']);
				$cliente->saldo_cc  = utf8_encode($row['saldo_cc']);
				$cliente->cod_transp  = utf8_encode($row['cod_transp']);
				$cliente->cond_vta   = $row['cond_vta'];
				$cliente->id_direccion_entrega = $row['id_direccion_entrega'];
				$cliente->talonario = $row['talonario'];
			
				if($row['hist'] != null){
					$historialProd = array();
					$splitArray = explode('|', $row['hist']);				
					foreach($splitArray as &$sProd){
						$splitProduct = explode('#', $sProd);

						$hist = new HistorialProducto;					
						$hist->cod_articulo = $splitProduct[0];
						$hist->descripcion = utf8_encode($splitProduct[1]);
						array_push($historialProd, $hist);
					}				
					$cliente->historial_productos = $historialProd;
				}	
			} 
			catch (Exception $e) 
			{
				mssql_free_result($query);
				mssql_close($link);
				return echoResponse(200, $e);
			} 						
			$clientes[] = $cliente;		
		}
		mssql_free_result($query);			
	}
	mssql_close($link);
	return echoResponse(200, $clientes);
});

$app->get('/Products', 'authenticate',   function () use ($app) 
{
	
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query('SELECT * FROM APP_PRODUCTOS');
	$products = array();
	
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{
			$product =new ShoppingProduct;
				$product->COD_ARTICULO  = utf8_encode($row['COD_ARTICULO']);
				$product->NRO_LISTA  = utf8_encode($row['NRO_LISTA']);
				$product->DESCRIPCION  = utf8_encode($row['DESCRIPCION']);
				$product->DESCRIPCION_AD  = utf8_encode($row['DESCRIPCION_AD']);
				$product->SINONIMO  = utf8_encode($row['SINONIMO']);
				$product->SIGLA_MEDIDA  = utf8_encode($row['SIGLA_MEDIDA']);
				$product->CLIENTE  = utf8_encode($row['CLIENTE']);
				$product->PRECIO  = $row['PRECIO'];
				//$product->PRECIO_MIN  = $row['PRECIO_MIN'];				
				$product->STOCK = $row['STOCK'];
				$product->STOCK_COMPROMETIDO  = $row['STOCK_COMPROMETIDO'];
				$product->STOCK_A_RECEPCIONAR  = $row['STOCK_A_RECEPCIONAR'];
				$product->EMPAQUE  = $row['EMPAQUE'];
				$product->CANT_DECIMAL_MEDIDA  = $row['CANT_DECIMAL_MEDIDA'];
			$products[] = $product;		
		}	
		
		mssql_free_result($query);		
	}	
	mssql_close($link);		
	return echoResponse(200, $products);
});

$app->get('/Products/:idClient', 'authenticate', function ($idClient) use ($app) 
{
	
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query('SELECT * FROM APP_PRODUCTOS');
	$products = array();
	
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{
			$products[] = array_map('utf8_encode',$row);		
		}
	
		/*$response["error"] = false;
		$response["quantity"] = count($products);
		$response["products"] = $products;*/
		
		mssql_free_result($query);
		mssql_close($link);
		
		echoResponse(200, $products);

	}	
});

$app->post('/Order', 'authenticate', function() use ($app) 
{
	$response = array();
	$entityBody = file_get_contents('php://input');
	$obj = new Order;
	$obj = json_decode($entityBody);
	return saveOrder($obj);	
});

function saveOrder($obj){
	try 
	{
		//Almacenamos en base de datos
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('mssql.charset', 'utf-8');
		ini_set('memory_limit', '1024M');
		error_reporting(E_ALL);


		//Obtenemos el �ltimo n�mero de pedido
		$lastOrderNumber = getNextOrderNumber();
		$dolar = getCotizacion();
		
		//LEYENDA
		$leyenda_1 = "";
		$leyenda_2 = "";
		$leyenda_3 = "";
		$leyenda_4 = "";
		$leyenda_5 = "";
		
		$resultado = 0;
		$array = str_split($obj->shoppingCart->comment, 60);
		$resultado = count($array);
		
		switch($resultado)
		{
			case 0:
				break;
			case 1:
				$leyenda_1 = $array[0];
				break;
			case 2:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				break;
			case 3:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				break;
			case 4:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				$leyenda_4 = $array[3];
				break;
			case 5:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				$leyenda_4 = $array[3];
				$leyenda_5 = $array[4];
				break;
			default:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				$leyenda_4 = $array[3];
				$leyenda_5 = $array[4];
				break;
		}

		// ************
		// CABECERA DEL PEDIDO
		// ************
		$query_header = sprintf("insert into GVA21 (cod_client, cod_sucurs, cod_vended, cond_vta, estado, mon_cte, n_remito, nro_pedido, fecha_entr, fecha_pedi, nro_sucurs, talon_ped, id_asiento_modelo_gv, terminal_ingreso, leyenda_1, leyenda_2, leyenda_3, leyenda_4, leyenda_5, n_lista, cod_transp, usuario_ingreso,total_pedi, comp_stk, origen, id_direccion_entrega, motivo, cotiz, talonario) values ('%s', '07', '%s', %d, 2, 1, '000000000000', '%s', CONVERT( DATE, '%s', 103 ) , CONVERT( DATE, '%s', 127 ),0, 14, 10, 'APP','%s','%s','%s','%s','%s', %d,'%s','%s',%f,1,'T','%d', '', %f, %d)",
		 $obj->shoppingCart->client->cod_client, //COD_CLIENT
		 (!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
		 $obj->shoppingCart->client->cond_vta, //COND_VTA
		 
		 $lastOrderNumber, //NRO_PEDIDO
		 $obj->deliveryDate, //FECHA_ENTR
		 $obj->date, //FECHA_PEDI

		 $leyenda_1, //LEYENDA_1
		 $leyenda_2, //LEYENDA_2
		 $leyenda_3, //LEYENDA_3
		 $leyenda_4, //LEYENDA_4
         $leyenda_5, //LEYENDA_5
		 
		 $obj->shoppingCart->client->nro_lista, //N_LISTA
		 (!isset($obj->shoppingCart->client->cod_transp) || is_null($obj->shoppingCart->client->cod_transp))?"":$obj->shoppingCart->client->cod_transp, //COD_TRANSP
		 $obj->shoppingCart->user->name, //USUARIO_INGRESO
		 $obj->total,//TOTAL_PEDI
		 
		 (!isset($obj->shoppingCart->client->id_direccion_entrega) || is_null($obj->shoppingCart->client->id_direccion_entrega))?"114":$obj->shoppingCart->client->id_direccion_entrega, //ID_DIRECCION_ENTREGA
		 $dolar, //COtizacion dolar
		 $obj->shoppingCart->client->talonario //TALONARIO
		 );
		
		// ************
		// DETALLE DEL PEDIDO
		// ************
		$nroRenglon = 1;
		$query_detalle = "";
		$query_stock = "";
		foreach ($obj->shoppingCart->shoppingProducts as $producto) 
		{
			$query_detalle .= sprintf("insert into gva03 (CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, NRO_PEDIDO, PRECIO, TALON_PED, CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2,N_RENGLON, UNIDAD_MEDIDA_SELECCIONADA) VALUES (1.0, %f, %f, %f, %f, %f, '%s', '%s', %f, 14,0,0,0,0,0,%d, 'P'); ", $producto->quantity,$producto->quantity,$producto->quantity,$producto->quantity,$producto->quantity, $producto->COD_ARTICULO, $lastOrderNumber, $producto->PRECIO, $nroRenglon);
			$nroRenglon = $nroRenglon+1;
			
			$query_stock .= sprintf("UPDATE STA19 SET CANT_COMP = CANT_COMP+%f WHERE COD_DEPOSI = '07' and COD_ARTICU = '%s'",
			$producto->quantity, $producto->COD_ARTICULO);

		}

		mssql_query("BEGIN TRAN");		
			mssql_query($query_header) or die(mssql_get_last_message());
			mssql_query($query_detalle) or die(mssql_get_last_message());
			mssql_query($query_stock) or die(mssql_get_last_message());			
		mssql_query("COMMIT");

		//mssql_free_result($res);
		mssql_close($link);
		$obj->state = "enviado";
		//sendMails($obj);
	} 
	catch (Exception $e) 
	{
		$obj->state = "no enviado";
	} 
	finally 
	{
		return echoResponse(201,$obj);
	}
}


$app->post('/Budget', 'authenticate', function() use ($app) 
{
	try 
	{
		$response = array();
		$entityBody = file_get_contents('php://input');
		$obj = new Budget;
		$obj = json_decode($entityBody);
		
		//Almacenamos en base de datos
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('mssql.charset', 'utf-8');
		ini_set('memory_limit', '1024M');
		error_reporting(E_ALL);

		$lastBudgetNumber = getNextBudgetNumber();		
		// ************
		// CABECERA DEL PEDIDO
		// ************
		$query_header = sprintf("insert into APP_ORDER
		(id, id_user, id_client, filter, state, json, hash) 
		values 
		('%s', '%s', '%s','%s', '%s', '%s', '%s')",
		 $lastBudgetNumber, //id de presupuesto
		 (!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
		 (!isset($obj->shoppingCart->client->cod_client) || is_null($obj->shoppingCart->client->cod_client))?"":$obj->shoppingCart->client->cod_client, //cliente		 
		 "", //filter
		 $obj->state, //state
		 $entityBody, //json
		 "" //hash
		 );

		$type = 0;
		if($obj->orderType = 'BUDGET'){
			$type = 1;
		}else{
			$type = 2;
		}


		if($obj->state == 'en autorización'){
			//Insert autorization
			$query_autorization = sprintf("insert into APP_AUTORIZATION
			(id, state, id_user, id_client, cod_vended, order_type, id_order,  json, type) 
			values 
			('%s', '%s', '%s','%s','%s','%s','%s', '%s', '%d')",
			$lastBudgetNumber,
			$obj->state, //state
			(!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
			(!isset($obj->shoppingCart->client->cod_client) || is_null($obj->shoppingCart->client->cod_client))?"":$obj->shoppingCart->client->cod_client, //cliente		 
			(!isset($obj->shoppingCart->client->cod_vended) || is_null($obj->shoppingCart->client->cod_vended))?"":$obj->shoppingCart->client->cod_vended, //cod_vended		 		 
			$obj->orderType,
			$obj->id,
			$entityBody, //json
			"", //hash
			$type
			);

			mssql_query("BEGIN TRAN");
				mssql_query($query_header) or die(mssql_get_last_message());
				mssql_query($query_autorization) or die(mssql_get_last_message());
			mssql_query("COMMIT");		

			//mssql_free_result($res);
			mssql_close($link);
			$obj->state = "EN AUTORIZACIÓN";
		}else{
			mssql_query("BEGIN TRAN");
				mssql_query($query_header) or die(mssql_get_last_message());				
			mssql_query("COMMIT");
			mssql_close($link);
						
			savePresupuesto($obj, $lastBudgetNumber);
			sendMailsPresupuesto($obj);
			$obj->state = "ENVIADO";			
		}						
	} 
	catch (Exception $e) 
	{
		throw $e;
		//$obj->state = $e->getMessage();
		//return echoResponse(202, $obj);
	} 
	finally 
	{
		return echoResponse(201,$obj);
	}
});

$app->post('/Price', 'authenticate', function() use ($app) 
{
	try 
	{
		$response = array();
		$entityBody = file_get_contents('php://input');
		$obj = new Budget;
		$obj = json_decode($entityBody);
		
		//Almacenamos en base de datos
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('mssql.charset', 'utf-8');
		ini_set('memory_limit', '1024M');
		error_reporting(E_ALL);

		$lastPriceNumber = getNextOrderNumber();
		//$obj->state = $lastPriceNumber;
		//return echoResponse(201, $obj);
		// ************
		// CABECERA DEL PEDIDO
		// ************
		$query_header = sprintf("insert into APP_ORDER
		(id, id_user, id_client, filter, state, json, hash) 
		values 
		('%s', '%s', '%s','%s', '%s', '%s', '%s')",
		 $lastPriceNumber, //id de presupuesto
		 (!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
		 (!isset($obj->shoppingCart->client->cod_client) || is_null($obj->shoppingCart->client->cod_client))?"":$obj->shoppingCart->client->cod_client, //cliente		 
		 "", //filter
		 $obj->state, //state
		 $entityBody, //json
		 "" //hash
		 );

		 
		//PRICE
		$type = 3;
		//Insert autorization
		$query_autorization = sprintf("insert into APP_AUTORIZATION
		(id, state, id_user, id_client, cod_vended, order_type, id_order,  json, type) 
		values 
		('%s', '%s', '%s','%s','%s','%s','%s', '%s', '%d')",
		$lastPriceNumber,
		$obj->state, //state
		(!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
			(!isset($obj->shoppingCart->client->cod_client) || is_null($obj->shoppingCart->client->cod_client))?"":$obj->shoppingCart->client->cod_client, //cliente		 
			(!isset($obj->shoppingCart->client->cod_vended) || is_null($obj->shoppingCart->client->cod_vended))?"":$obj->shoppingCart->client->cod_vended, //cod_vended		 		 
			$obj->orderType,
			$obj->id,
			$entityBody, //json
		"", //hash
		$type
		);

		mssql_query("BEGIN TRAN");
			mssql_query($query_header) or die(mssql_get_last_message());
			mssql_query($query_autorization) or die(mssql_get_last_message());
		mssql_query("COMMIT");		
		mssql_close($link);
		//mssql_free_result($res);
		$obj->state = "EN AUTORIZACIÓN";		
	} 
	catch (Exception $e) 
	{
		$obj->state = $e->getMessage();
		return echoResponse(201, $obj);
	} 
	finally 
	{
		return echoResponse(200,$obj);
	}
});

/* Admin actualiza autorizacion aprobado o no */
$app->post('/AutorizationPending', 'authenticate', function() use ($app) 
{
	try 
	{
		$response = array();
		$entityBody = file_get_contents('php://input');		
		
		$obj = new Autorization;
		$obj = json_decode($entityBody);

		//Almacenamos en base de datos
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('mssql.charset', 'utf-8');
		ini_set('memory_limit', '1024M');
		error_reporting(E_ALL);

		//Obtengo el status actual de la autorizacion, si es PRESUPUESTO o PRECIO

		//compruebo si es una confirmacion o alta de autorizacion
		//UPDATE autorization
		$query_header = sprintf("update APP_AUTORIZATION 
		set state = '%s',				
		type = '%d'
		where id_user = '%s' and id_client = '%s' and cod_vended = '%s' and order_type = '%s' and id_order = '%s'",
		$obj->state, //state		
		$obj->type,
		(!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
		 (!isset($obj->shoppingCart->client->cod_client) || is_null($obj->shoppingCart->client->cod_client))?"":$obj->shoppingCart->client->cod_client, //cliente		 
		 (!isset($obj->shoppingCart->client->cod_vended) || is_null($obj->shoppingCart->client->cod_vended))?"":$obj->shoppingCart->client->cod_vended, //cod_vended		 		 
		 $obj->orderType,
	     $obj->id
		);
		mssql_query("BEGIN TRAN");
			mssql_query($query_header) or die(mssql_get_last_message());
		mssql_query("COMMIT");

		$query_budget_id = sprintf("select id from APP_AUTORIZATION		
				where id_user = '%s' and id_client = '%s' and cod_vended = '%s' and order_type = '%s' and id_order = '%s'",		
		(!isset($obj->shoppingCart->user->id) || is_null($obj->shoppingCart->user->id))?"":$obj->shoppingCart->user->id, //COD_VENDED
		 (!isset($obj->shoppingCart->client->cod_client) || is_null($obj->shoppingCart->client->cod_client))?"":$obj->shoppingCart->client->cod_client, //cliente		 
		 (!isset($obj->shoppingCart->client->cod_vended) || is_null($obj->shoppingCart->client->cod_vended))?"":$obj->shoppingCart->client->cod_vended, //cod_vended		 		 
		 $obj->orderType,
	     $obj->id
		);


		$query_budget = mssql_query($query_budget_id);
		$maxNumber = mssql_fetch_array($query_budget);
		if ($maxNumber === NULL)
		{
			$budgetId = "U0000000000000";
		}
		else
		{
			$budgetId = $maxNumber[0];
		}

		//mssql_free_result($res);
		mssql_close($link);
		// envia notificacion de la situacion de la autorizacion al usuario
		$to = getToken($obj->shoppingCart->user->name);
		if($to){
			$message = "Se ha ".$obj->state." el ";
			if($obj->orderType == 'BUDGET'){
				$message = $message ."PRESUPUESTO";
			}else{			
				$message = $message ."PRECIO ESPECIAL";
			}
			
			pushNotification($to, "Autorización de ".$obj->shoppingCart->client->nom_com, $message, $obj);			
		}
		
		// realizar proceso de presupuesto o Orden
		if($obj->state == 'AUTORIZADO'){
			if($obj->orderType == 'BUDGET'){				
				savePresupuesto($obj, $budgetId);
				sendMailsPresupuesto($obj);
			}else {
				saveOrder($obj);
				sendMails($obj);
			}
		}
		
	} 
	catch (Exception $e) 
	{
		$obj->state = $e->getMessage();
		return echoResponse(202, $obj);
	} 
	finally 
	{		
		return echoResponse(201,$obj);
	}
});


$app->get('/AutorizationPending/', 'authenticate', function () use ($app)
{
	
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD) or die("Couldn't connect to SQL Server on $myServer. Error: " . mssql_get_last_message());;
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query('SELECT cast(json as varchar(MAX)) as jsonObject FROM APP_AUTORIZATION where type <> 7');
	
	$autorizations = array();
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{
			//$autorization = new Autorization;
			$autorization= json_decode($row['jsonObject']);			
			$autorizations[] = $autorization;
		}
	
		mssql_free_result($query);
		mssql_close($link);
		
		echoResponse(200, $autorizations);
	}	
});

$app->get('/Setup', 'authenticate', function ()  use ($app)
{
	$configurations = array();
	$dolar = getCotizacion();
	$config_dolar = new Config;
	$config_dolar->id = "DOL";
	$config_dolar->name = "dolar";
	$config_dolar->value = $dolar;
	
	$configurations[] = $config_dolar;
	echoResponse(200, $configurations);
});

$app->get('/Category/:hierarchy', 'authenticate', function ($hierarchy) use ($app)
{
	
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	
	if ($hierarchy != 'ALL')
	{
		$query = mssql_query(sprintf("SELECT id, name, hierarchy, inactive  FROM APP_CATEGORIA  WHERE hierarchy = '%s'", $hierarchy));
	}
	else
	{
		$query = mssql_query(sprintf("SELECT id, name, hierarchy, inactive  FROM APP_CATEGORIA", $hierarchy));
	}

	$categories = array();
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{

			$category = new Category;
			$category->id  = utf8_encode($row['id']);
			$category->name  = utf8_encode($row['name']);
			$category->hierarchy  = utf8_encode($row['hierarchy']);
			$category->inactive  = $row['inactive'];
			
			$categories[] = $category;
		}
	
		mssql_free_result($query);
		mssql_close($link);
		
		echoResponse(200, $categories);
	}	
});


$app->get('/Transport/', 'authenticate', function () use ($app)
{
	
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	
	$query = mssql_query(sprintf("select CATEG_TRAN, COD_TRANSP, DOM_TRANS, NOMBRE_TRA, LOCALIDAD from Gva24"));
	
	$transports = array();
	if (!is_null($query))
	{
		while ($row = mssql_fetch_assoc($query)) 
		{

			$trasport = new Transport;
			$trasport->id  = utf8_encode($row['COD_TRANSP']);
			$trasport->categ_tran  = utf8_encode($row['CATEG_TRAN']);
			$trasport->dom_trans  = utf8_encode($row['DOM_TRANS']);
			$trasport->nombre_tra  = utf8_encode($row['NOMBRE_TRA']);
			$trasport->localidad= utf8_encode($row['LOCALIDAD']);
			
			$transports[] = $trasport;
		}
	
		mssql_free_result($query);
		mssql_close($link);
		
		echoResponse(200, $transports);
	}	
});

$app->post('/Mail', 'authenticate', function() use ($app) 
{
	try 
	{
		$entityBody = file_get_contents('php://input');
		$obj = new Order;
		$obj = json_decode($entityBody);
		sendMails($obj);
	}
	catch (Exception $e) 
	{
		return echoResponse(400,$e);
	}
	
	return echoResponse(200,$obj);
});

$app->get('/TestServer', function()
{
	echo "Server OK";
});

$app->get('/TestDB', function()
{
	$lastOrderNumber = getNextOrderNumber() .' '.getCotizacion();	
	return echoResponse(200, $lastOrderNumber);
});

$app->get('/TestBudget', function()
{
	$lastBudgetNumber = getNextBudgetNumber();	
	return echoResponse(200, $lastBudgetNumber);
});

$app->post('/User/changepassword/:userName/:password', 'authenticate',   function($userName, $password) use ($app)
{
	try 
	{
		
		//Almacenamos en base de datos
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		$query_insertOrUpdate = "";
		$response = "";
		
		mssql_select_db(DB_NAME, $link);
		$query = mssql_query(sprintf("SELECT username FROM APP_USER_PROFILE WHERE username = '%s'", $userName));
		
		$userNameFromDb = mssql_fetch_array($query);
		
		
		if (!$userNameFromDb)
		{
			//INSERT
			$query_insertOrUpdate = sprintf("INSERT INTO APP_USER_PROFILE (username, userpass) VALUES ('%s', '%s')", $userName, $password);
		}
		else
		{
			//UPDATE
			$query_insertOrUpdate = sprintf("UPDATE APP_USER_PROFILE SET userpass = '%s' WHERE username = '%s'",$password, $userName);
		}
		
		mssql_query("BEGIN TRAN");
			mssql_query($query_insertOrUpdate) or die(mssql_get_last_message());
			$response = $query;
		mssql_query("COMMIT");
			
		//mssql_free_result($res);
		mssql_close($link);
	}
	catch (Exception $e) 
	{
		$response = "error";
		return echoResponse(201, null);
	} 
	
	return echoResponse(200, null);
});

$app->get('/User/:userName/:password', 'authenticate', function($userName, $password) use ($app)
{
	$user = new User;
	
	//Almacenamos en base de datos
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	
	//Chequeamos usuario y pass
	$query = mssql_query(sprintf("SELECT USER_NAME FROM APP_VIEW_USER_PROFILE WHERE USER_NAME = '%s' AND USER_PASSWORD = '%s'", $userName, $password));
	$userNameFromDb = mssql_fetch_array($query);

	
	
	if (!$userNameFromDb)
	{
		//No existe
		return echoResponse(200, null);
	}
	else
	{
		//Buscamos usuario como cliente
		$query = mssql_query(sprintf("SELECT cod_client, nom_com, nro_lista, e_mail FROM APP_CLIENTES WHERE REPLACE(LOWER(cuit), '-', '') = LOWER('%s') ",$userName));
		$user_client = mssql_fetch_assoc($query);

		if (!mssql_num_rows($query))
		{
			//Buscamos usuario como vendedor
			mssql_free_result($query);
			$query = mssql_query(sprintf("SELECT COD_VENDED, NOMBRE_VEN,E_MAIL FROM GVA23 WHERE REPLACE(LOWER(NOMBRE_VEN), ' ', '.') = LOWER('%s') ",$userName));
			$user_seller = mssql_fetch_assoc($query);
			
			if (mssql_num_rows($query))
			{
				$user->id = $user_seller['COD_VENDED'];
				$user->name = $user_seller['NOMBRE_VEN'];
				$user->rol = "2";
				$user->user_email = $user_seller['E_MAIL'];
				$user->rolName = "Vendedor";
			}
			else
			{
				//Buscamos usuario como administrador
				if ($userName == 'admin')
				{
					$user->id = "00";
					$user->name = "admin";
					$user->rol = "3";
					$user->user_email = '';
					$user->rolName = "Administrador";
				}
				else
				{
					mssql_close($link);
					return echoResponse(200, null);
				}
				
			}
		}
		else
		{
			$user->id = $user_client['cod_client'];
			$user->name = $user_client['nom_com'];
			$user->nro_lista_user = $user_client['nro_lista'];
			$user->user_email = $user_client['e_mail'];
			$user->rol = "1";
			$user->rolName = "Cliente";
		}
	}
	mssql_close($link);
	return echoResponse(200, $user);
});

/********************************************************/
/* HELPERES
/********************************************************/
function getNextOrderNumber()
{
	$nextNumber = "";
	
	//Almacenamos en base de datos
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query('SELECT MAX(NRO_PEDIDO) FROM GVA21 WHERE TALON_PED = 14');
	$maxNumber = mssql_fetch_array($query);

	if ($maxNumber === NULL)
	{
		$lastNumber = " 000500000000";
	}
	else
	{
		$lastNumber = $maxNumber[0];
	}

	$calcNextNumber = substr($lastNumber, -5, 8); // Sacamos sucursal y espacio en blanco
	$calcNextNumber = $calcNextNumber+1;
	$suffix = str_pad($calcNextNumber,8,"0",STR_PAD_LEFT);
	$nextNumber = sprintf(" 0005%s", $suffix);		
	mssql_free_result($query);
	return $nextNumber;
}

function getNextBudgetNumber()
{
	$nextNumber = "";
	
	//Almacenamos en base de datos
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query('select max(n_cotiz) from GVA08');
	$maxNumber = mssql_fetch_array($query);

	if ($maxNumber === NULL)
	{
		$lastNumber = "U0000000000000";
	}
	else
	{
		$lastNumber = $maxNumber[0];
	}

	$calcNextNumber = substr($lastNumber, -5, 8); // Sacamos sucursal y espacio en blanco
	$calcNextNumber = $calcNextNumber+1;
	$suffix = str_pad($calcNextNumber,8,"0",STR_PAD_LEFT);
	$nextNumber = sprintf("U00002%s", $suffix);
		
	mssql_free_result($query);
	return $nextNumber;
}

function getCotizacion()
{
	$dolar = 0.0;
	
	//Almacenamos en base de datos
	$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mssql_select_db(DB_NAME, $link);
	$query = mssql_query('SELECT COTIZACION FROM COTIZACION WHERE ID_COTIZACION = (SELECT MAX(ID_COTIZACION) FROM COTIZACION WHERE ID_MONEDA = 2 AND ID_TIPO_COTIZACION = 1)');
	$lastValueDolar = mssql_fetch_array($query);

	if ($lastValueDolar === NULL)
	{
		$dolar = 16.47;
	}
	else
	{
		$dolar = $lastValueDolar[0];
	}

	mssql_free_result($query);
	return $dolar;
}

function echoResponse($status_code, $response) {
	$app = \Slim\Slim::getInstance();
	// Http response code
	$app->status($status_code);

	// setting response content type to json
	$app->contentType('application/json');

	echo json_encode($response);
}

function authenticate(\Slim\Route $route) {
	// Getting request headers
	$headers = apache_request_headers();
	$response = array();
	$app = \Slim\Slim::getInstance();

	// Verifying Authorization Header
	if (isset($headers['Authorization'])) {
	//$db = new DbHandler(); //utilizar para manejar autenticacion contra base de datos

	// get the api key
	$token = $headers['Authorization'];

	// validating api key
	if (!($token == API_KEY)) { //API_KEY declarada en Config.php

	// api key is not present in users table
	$response["error"] = true;
	$response["message"] = "Acceso denegado. Token inv�lido";
	echoResponse(401, $response);

	$app->stop(); //Detenemos la ejecuci�n del programa al no validar

	} else {
	//procede utilizar el recurso o metodo del llamado
	}
	} else {
	// api key is missing in header
	$response["error"] = true;
	$response["message"] = "Falta token de autorizaci�n";
	echoResponse(400, $response);

	$app->stop();
	}
}

function verifyRequiredParams($required_fields) 
{
	$error = false;
	$error_fields = "";
	$request_params = array();
	$request_params = $_REQUEST;
	// Handling PUT request params
	if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
	$app = \Slim\Slim::getInstance();
	parse_str($app->request()->getBody(), $request_params);
	}
	foreach ($required_fields as $field) {
	if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
	$error = true;
	$error_fields .= $field . ', ';
	}
	}

	if ($error) {
	// Required field(s) are missing or empty
	// echo error json and stop the app
	$response = array();
	$app = \Slim\Slim::getInstance();
	$response["error"] = true;
	$response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
	echoResponse(400, $response);

	$app->stop();
	}
}
function GetValue($input)
{
	return (!isset($input) || is_null($input))?"":$input;
	
}

function sendMails($order)
{
	$dolar = getCotizacion();
	$mail = new PHPMailer;
	$mail->IsSMTP(); // telling the class to use SMTP
	$mail->SMTPDebug  =4;                     // enables SMTP debug information (for testing)
											   // 1 = errors and messages
											   // 2 = messages only
	$mail->Mailer = "smtp"; 
	$mail->SMTPAutoTLS = false;
	$mail->SMTPAuth   = true;                  // enable SMTP authentication
	$mail->SMTPSecure = "tls";  
	$mail->Host       = "smtp.gmail.com";//"mail.dbdistribuidora.com"; //;      // SMTP server
	$mail->Port       = 587;                   // SMTP port	
	$mail->Username   = "garofolo.leonel@gmail.com";//"ventasapp@dbdistribuidora.com";username
	$mail->Password   = "30121Daddy";//"VentasApp"; //; // password
	$mail->SetFrom('garofolo.leonel@gmail.com', 'Ventas APP');

	//Destinatarios
	/*
	$toAddressLimit = "fernando.ariel.tello@gmail.com";
    $toAddressStock = "fernando.ariel.tello@gmail.com";
    $toAddressSpecial = "fernando.ariel.tello@gmail.com";
	*/
	$sendSotck = false;
	$sendLimit = false;
	$total = 0.00;
	$styleRow = "style=\"border: 1px solid #dddddd;text-align: left;padding: 8px;\"";
	$styleRowSinStock = "style=\"border: 1px solid #dddddd;text-align: left;padding: 8px;background-color:#F79F81;\"";
	$styleBackgroundHeader = "style=\"background-color: #3F51B5;text-align: center;color:#FFFFFF\"";
	$subject = "";
	$body = "";
	$intro = "";
	$sign = "";
	$products = "";
	$total = 0.0;
	
	//*************************************************
	//*************************************************
	// TABLA PEDIDOS 
	//*************************************************
	//*************************************************
	
	$products = "<table style=\"font-family: arial, sans-serif;border-collapse: collapse;width: 100%;\">";
	//Cliente y Vendedro
	$products .= "<tr " . $styleRow . "><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del cliente</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del Vendedor</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Despacho</td></tr>";
	$products .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\" ><b>Fecha Creaci�n:</b> %s</td></tr>", $order->shoppingCart->client->nom_com, $order->shoppingCart->user->name, $order->date);
	$products .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Direcci�n:</b> %s</td><td colspan=\"2\"><b>Tipo:</b> %s</td><td colspan=\"2\" ><b>Fecha Entrega:</b> %s</td></tr>", $order->shoppingCart->client->dir_com, $order->shoppingCart->user->rolName, $order->deliveryDate);
	
	//Productos
	
	$products .= "<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Productos del pedido</td></tr>";
	$products .= "<tr " . $styleRow . "><th>Nombre</th><th>C�digo</th><th>Cantidad</th><th></th><th>Precio unidad (U\$S)</th><th>Precio Total (U\$S)</th></tr>";
	
	foreach ($order->shoppingCart->shoppingProducts as $product)
	{
		$total = $total + ($product->quantity * $product->PRECIO);
		$products .= sprintf(
			"<tr " . $styleRow . "><td>%s</td><td>%s</td><td>%.2f</td><td></td><td>%.2f %s</td><td>%.2f</td></tr>"
			, $product->DESCRIPCION, $product->COD_ARTICULO, $product->quantity, $product->PRECIO, ($product->SIGLA_MEDIDA === NULL)?"":$product->SIGLA_MEDIDA, ($product->quantity * $product->PRECIO));
	}
	
		
	// Total
	// Agregamos IVA
	$products .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>SUBTOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total);
	$products .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>IVA (U\$S)</b></td><td><b>%.2f</b></td></tr>",$total*0.21);
	$products .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>TOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total*1.21);

	// Observaciones
	$products .= sprintf("<tr " . $styleRow . "><td colspan=\"6\" ".$styleBackgroundHeader.">Observaciones</td></tr><tr " . $styleRow . "><td colspan=\"6\">%s</td></tr>", ($order->shoppingCart->comment === NULL) ? "Sin observaciones" : $order->shoppingCart->comment);

	$products .= "</table>";

	//*************************************************
	//*************************************************
	// PEDIDOS SIN STOCK
	//*************************************************
	//*************************************************
	#region Tabla pedido Sin Stock
	$total = 0.0;
	$productsSinStock = "<table style=\"font-family: arial, sans-serif;border-collapse: collapse;width: 100%;\">";
	//Cliente y Vendedro
	$productsSinStock .= "<tr " . $styleRow . "><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del cliente</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del Vendedor</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Despacho</td></tr>";
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Fecha Creaci�n:</b> %s</td></tr>", $order->shoppingCart->client->nom_com, $order->shoppingCart->user->name, $order->date);
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Direcci�n:</b> %s</td><td colspan=\"2\"><b>Tipo:</b> %s</td><td colspan=\"2\"><b>Fecha Entrega:</b> %s</td></tr>", $order->shoppingCart->client->dir_com, $order->shoppingCart->user->rolName, $order->deliveryDate);

	//Productos
	$productsSinStock .= "<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Productos sin stock</td></tr>";
	$productsSinStock .= "<tr " . $styleRow . "><th>Nombre</th><th>C�digo</th><th>Cantidad Solicitada</th><th>Cantidad Faltante</th><th>Precio unidad (U\$S)</th><th>Precio Total (U\$S)</th></tr>";
	$style = "";
	$stockFaltante = 0.0;
	foreach ($order->shoppingCart->shoppingProducts as $product)
	{

		if (($product->STOCK - $product->quantity) < 0)
		{
			$style = $styleRowSinStock;
			$stockFaltante = ($product->quantity - $product->STOCK);
			$sendSotck = true;
		}
		else
		{
			$style = $styleRow;
			$stockFaltante = 0;
		}

		$total = $total + ($product->quantity * $product->PRECIO);
		$productsSinStock .= sprintf(
			"<tr " . $style . "><td>%s</td><td>%s</td><td>%.2f</td><td>%.2f</td><td>%.2f %s</td><td>%.2f</td></tr>"
			, $product->DESCRIPCION, $product->COD_ARTICULO, $product->quantity, $stockFaltante, $product->PRECIO, ($product->SIGLA_MEDIDA === NULL)?"":$product->SIGLA_MEDIDA, ($product->quantity * $product->PRECIO));
	}

	// Total
	// Agregamos IVA
	//total = total * 1.21;
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>SUBTOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total);
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>IVA (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total*0.21);
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>TOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total*1.21);

	// Observaciones
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Observaciones</td></tr><tr " . $styleRow . "><td colspan=\"6\">%s</td></tr>", ($order->shoppingCart->comment === NULL) ? "Sin observaciones" : $order->shoppingCart->comment);

	$productsSinStock .= "</table>";
	//endregion
	
	//*************************************************
	//*************************************************
	// #region Tabla pedido l�mite superado
	//*************************************************
	//*************************************************
	
	$total = 0.0;
	$productsLimite = "<table style=\"font-family: arial, sans-serif;border-collapse: collapse;width: 100%;\">";
	//Cliente y Vendedro
	
	$productsLimite .= "<tr " . $styleRow . "><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del cliente</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del Vendedor</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Despacho</td></tr>";
	
	$productsLimite .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\" ><b>Fecha Creaci�n:</b> %s</td></tr>", $order->shoppingCart->client->nom_com, $order->shoppingCart->user->name, $order->date);
	
	$productsLimite .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Direcci�n:</b> %s</td><td colspan=\"2\"><b>Tipo:</b> %s</td><td colspan=\"2\" ><b>Fecha Entrega:</b> %s</td></tr>", $order->shoppingCart->client->dir_com, $order->shoppingCart->user->rolName, $order->deliveryDate);

	
	//Productos
	$productsLimite .= "<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Productos del pedido</td></tr>";
	$productsLimite .= "<tr " . $styleRow . "><th>Nombre</th><th>C�digo</th><th>Cantidad</th><th></th><th>Precio unidad (U\$S)</th><th>Precio Total (U\$S)</th></tr>";
	foreach ($order->shoppingCart->shoppingProducts as $product)
	{
		$total = $total + ($product->quantity * $product->PRECIO);
		$productsLimite .= sprintf(
			"<tr " . $styleRow . "><td>%s</td><td>%s</td><td>%.2f</td><td></td><td>%.2f %s</td><td>%.2f</td></tr>"
			, $product->DESCRIPCION, $product->COD_ARTICULO, $product->quantity, $product->PRECIO, $product->SIGLA_MEDIDA, $product->totalPrice);
	}
	
	// Total
	// Agregamos IVA
	$sendLimit = false;
	$limite = ((($order->shoppingCart->client->cupo_credi/ $dolar)) - ($order->shoppingCart->client->saldo_cc/ $dolar));
	if (($total*1.21) > $limite)
	{
		$sendLimit = true;
	}
	$productsLimite .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>TOTAL (U\$S)</b></td><td><b>%.2f + IVA (%.2f)</b></td></tr>", $total, $total*0.21);
	$productsLimite .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>L�MITE (U\$S)</b></td><td><b>%.2f</b></td></tr>", $limite);
	$productsLimite .= sprintf("<tr " . $styleRowSinStock . "><td></td><td></td><td></td><td></td><td><b>EXCESO (U\$S)</b></td><td><b>%.2f</b></td></tr>", ($total*1.21)-$limite);

	 // Observaciones
	$productsLimite .= sprintf("<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Observaciones</td></tr><tr " . $styleRow . "><td colspan=\"6\">%s</td></tr>", ($order->shoppingCart->comment=== NULL) ? "Sin observaciones" : $order->shoppingCart->comment);


	$productsLimite .= "</table>";
	
	
	//Alerta por facturas vencidas y l�mite de cr�dito.Posibilidad de mail autom�tico a cr�ditos.  
	//********************************************************
	
	if ($sendLimit)
	{
		if ($order->shoppingCart->user->name === $order->shoppingCart->client->nom_com)
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido. El saldo de dicho cliente ha sido superado. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName, $order->shoppingCart->client->nom_com);
		}
		else
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido para el cliente <b>%s</b>. El saldo de dicho cliente ha sido superado. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName, $order->shoppingCart->user->name, $order->shoppingCart->client->nom_com);
		}
		
		$sign = "<br/>DB Distribuidora Argentina S.A.<br/>Francisco N. Laprida 5052, <br/>Villa Martelli, Gran Buenos Aires, Argentina";
		$subject = "DB Distribuidora - Pedido - L�mite de saldo superado";
		$body = $intro . $productsLimite . $sign;

		$mail->CharSet = 'UTF-8';
		$mail->Subject = $subject;
		$mail->MsgHTML($body);
		$mail->ClearAddresses(); 
		$mail->ClearCCs();
		$mail->ClearBCCs();
		$mail->AddAddress("garofolo.leonel@gmail.com", "Leonel Garofolo");
		//$mail->AddAddress('gmandado@dbdistribuidora.com', 'G Mandado');
		//$mail->AddCC('ventasapp@dbdistribuidora.com', 'Pedidos');		
		$mail->Send();
		$mail->ClearAddresses();
		$mail->ClearCCs();
		$mail->ClearBCCs();		
	}
	
	
	//Alerta de entrega especial.Posibilidad de mail a log�stica
	//********************************************************
	//El metodo que envia debe contener lo siguiente
	if ($order->shoppingCart->specialShipping)
	{
		if ($order->shoppingCart->user->name === $order->shoppingCart->client->nom_com)
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido con entrega especial. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName,  $order->shoppingCart->client->nom_com);
		}
		else
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido con entrega especial para el cliente <b>%s</b>. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName, $order->shoppingCart->user->name, $order->shoppingCart->client->nom_com);
		}
		
		
		$sign = "<br/>DB Distribuidora Argentina S.A.<br/>Francisco N. Laprida 5052, <br/>Villa Martelli, Gran Buenos Aires, Argentina";
		$subject = "DB Distribuidora - Pedido - Entrega Especial";
		$body = $intro.$products.$sign; 
		
		$mail->CharSet = 'UTF-8';
		$mail->Subject = $subject;
		$mail->MsgHTML($body);
		$mail->ClearAddresses(); 
		$mail->ClearCCs();
		$mail->ClearBCCs();
		$mail->AddAddress("garofolo.leonel@gmail.com", "Leonel Garofolo");
		$mail->AddAddress('dcolla@dbdistribuidora.com', 'Daniel Colla');
		//$mail->AddAddress('dsandez@dbdistribuidora.com', 'D Sandez');
		//$mail->AddCC('ventasapp@dbdistribuidora.com', 'Pedidos');		
		$mail->Send();
		$mail->ClearAddresses(); 
		$mail->ClearCCs();
		$mail->ClearBCCs();
	}
	
	//Alerta por falta de stock. Posibilidad de mail autom�tico a compras.
	//********************************************************
	if ($sendSotck)
	{
		if ($order->shoppingCart->user->name === $order->shoppingCart->client->nom_com)
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido. No hay stock suficiente para alguno de los productos solicitados. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName, $order->shoppingCart->client->nom_com);
		}
		else
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido para el cliente <b>%s</b>. No hay stock suficiente para alguno de los productos solicitados. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName, $order->shoppingCart->user->name, $order->shoppingCart->client->nom_com);
		}
		
		$sign = "<br/>DB Distribuidora Argentina S.A.<br/>Francisco N. Laprida 5052, <br/>Villa Martelli, Gran Buenos Aires, Argentina";
		$subject = "DB Distribuidora - Pedido - Falta de stock";
		$body = $intro . $productsSinStock . $sign;

		$mail->CharSet = 'UTF-8';

		$mail->Subject = $subject;
		$mail->MsgHTML($body);
		$mail->ClearAddresses(); 
		$mail->ClearCCs();
		$mail->ClearBCCs();
		$mail->AddAddress("garofolo.leonel@gmail.com", "Leonel Garofolo");
		$mail->AddAddress('dcolla@dbdistribuidora.com', 'Daniel Colla');
		/*
		$mail->AddAddress('imartinez@dbdistribuidora.com', 'I Martinez');
		$mail->AddAddress('rabdala@dbdistribuidora.com', 'R Abdala');
		$mail->AddCC('ventasapp@dbdistribuidora.com', 'Pedidos');
		$mail->AddCC('afigueroa@dbdistribuidora.com', 'Pedidos');
		$mail->AddCC('lorena@dbdistribuidora.com', 'Pedidos');
		*/		
		$mail->Send();
		$mail->ClearAddresses(); 
		$mail->ClearCCs();
		$mail->ClearBCCs();
	}
	
	//Alerta de entrega especial.Posibilidad de mail a log�stica
	//********************************************************
	//El metodo que envia debe contener lo siguiente
	if (true)
	{
		if ($order->shoppingCart->user->name === $order->shoppingCart->client->nom_com)
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName,  $order->shoppingCart->client->nom_com);
		}
		else
		{
			$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un pedido para el cliente <b>%s</b>. A continuaci�n se describen los detalles del mismo:<br/><br/>", $order->shoppingCart->user->rolName, $order->shoppingCart->user->name, $order->shoppingCart->client->nom_com);
		}
		
		
		$sign = "<br/>DB Distribuidora Argentina S.A.<br/>Francisco N. Laprida 5052, <br/>Villa Martelli, Gran Buenos Aires, Argentina";
		$subject = "DB Distribuidora - Pedido Realizado (QA - Desestimar)";
		$body = $intro.$products.$sign; 
		
		$mail->CharSet = 'UTF-8';
		$mail->Subject = $subject;
		$mail->MsgHTML($body);
		$mail->ClearAddresses();
		$mail->ClearCCs();
		$mail->ClearBCCs();
		$mail->AddAddress('garofolo.leonel@gmail.com', 'Leonel Garofolo');
		$mail->AddAddress('dcolla@dbdistribuidora.com', 'Daniel Colla');		
		if (!is_null($order->shoppingCart->user->user_email) && isset($order->shoppingCart->user->user_email))
		/* DESCOMENTAR
		$mail->AddAddress('dcolla@dbdistribuidora.com', 'Daniel Colla');		
		if (!is_null($order->shoppingCart->user->user_email) && isset($order->shoppingCart->user->user_email))
		{
			$mail->AddCC($order->shoppingCart->user->user_email, $order->shoppingCart->user->name);
		}
		*/
		$mail->Send();
		$mail->ClearAddresses();
		$mail->ClearCCs();
		$mail->ClearBCCs();		
	}
	//#endregion
}

function savePresupuesto($presupuesto,  $lastBudgetNumber){
	$response = array();		
		//Almacenamos en base de datos		
		$link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mssql_select_db(DB_NAME, $link);
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('mssql.charset', 'utf-8');
		ini_set('memory_limit', '1024M');
		error_reporting(E_ALL);		

		//Obtenemos el �ltimo n�mero de pedido
		$lastOrderNumber = getNextOrderNumber();
		$dolar = getCotizacion();
		
		//LEYENDA
		$leyenda_1 = "";
		$leyenda_2 = "";
		$leyenda_3 = "";
		$leyenda_4 = "";
		$leyenda_5 = "";
		
		$resultado = 0;
		$array = str_split($presupuesto->shoppingCart->comment, 60);
		$resultado = count($array);
		switch($resultado)
		{
			case 0:
				break;
			case 1:
				$leyenda_1 = $array[0];
				break;
			case 2:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				break;
			case 3:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				break;
			case 4:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				$leyenda_4 = $array[3];
				break;
			case 5:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				$leyenda_4 = $array[3];
				$leyenda_5 = $array[4];
				break;
			default:
				$leyenda_1 = $array[0];
				$leyenda_2 = $array[1];
				$leyenda_3 = $array[2];
				$leyenda_4 = $array[3];
				$leyenda_5 = $array[4];
				break;
		}

		// ************
		// CABECERA DEL PRESUPUESTO
		// ************
		$query_header = sprintf("insert into GVA08 (N_COTIZ, cod_client, cod_vended, cond_vta, estado, mon_cte, terminal_ingreso, leyenda_1, leyenda_2, leyenda_3, leyenda_4, leyenda_5, cod_transp, usuario_ingreso, importe ,id_direccion_entrega,cotiz, talonario) 
		values ('%s','%s', '%s', %d, 2, 1, 'APP','%s','%s','%s','%s','%s','%s','%s',%f,%d, %f, %d)",
		 $lastBudgetNumber,
		 $presupuesto->shoppingCart->client->cod_client, //COD_CLIENT
		 (!isset($presupuesto->shoppingCart->user->id) || is_null($presupuesto->shoppingCart->user->id))?"":$presupuesto->shoppingCart->user->id, //COD_VENDED
		 $presupuesto->shoppingCart->client->cond_vta, //COND_VTA		 		 
		 $leyenda_1, //LEYENDA_1
		 $leyenda_2, //LEYENDA_2
		 $leyenda_3, //LEYENDA_3
		 $leyenda_4, //LEYENDA_4
         $leyenda_5, //LEYENDA_5
		 
		 (!isset($presupuesto->shoppingCart->client->cod_transp) || is_null($presupuesto->shoppingCart->client->cod_transp))?"":$presupuesto->shoppingCart->client->cod_transp, //COD_TRANSP
		 $presupuesto->shoppingCart->user->name, //USUARIO_INGRESO
		 $presupuesto->total,//IMPORTE
		 
		 (!isset($presupuesto->shoppingCart->client->id_direccion_entrega) || is_null($presupuesto->shoppingCart->client->id_direccion_entrega))?114:$presupuesto->shoppingCart->client->id_direccion_entrega, //ID_DIRECCION_ENTREGA
		 $dolar, //COtizacion dolar
		 $presupuesto->shoppingCart->client->talonario //TALONARIO
		 );
		
		// ************
		// DETALLE DEL PRESUPUESTO
		// ************
		$nroRenglon = 1;
		$query_detalle = "";
		$query_stock = "";
		foreach ($presupuesto->shoppingCart->shoppingProducts as $producto) 
		{
			$query_detalle .= sprintf("insert into gva09 (N_COTIZ, COD_ARTICU, CANT_TOTAL, ESTADO, PRECIO, ID_MEDIDA_VENTAS, UNIDAD_MEDIDA_SELECCIONADA, NRO_RENGL, TALONARIO) 
				VALUES ('%s', '%s', %d, 2, %f, 14, 'P', %d, %d)", 
				$lastBudgetNumber,				
				$producto->COD_ARTICULO, 
				$producto->quantity,
				$producto->PRECIO, 
				$nroRenglon,
				$presupuesto->shoppingCart->client->talonario
			);
			$nroRenglon = $nroRenglon+1;			

		}
		
		mssql_query("BEGIN TRAN");
			mssql_query($query_header) or die(mssql_get_last_message());
			mssql_query($query_detalle) or die(mssql_get_last_message());
		mssql_query("COMMIT");
		//mssql_free_result($res);
		mssql_close($link);
		/*
	try 
	{
		
	} 
	catch (Exception $e) 
	{
		throw $e;		
		$obj->state = "no enviado";
	}
	*/
}

function sendMailsPresupuesto($presupuesto)
{
	
	$dolar = getCotizacion();
	$mail = new PHPMailer;
	$mail->IsSMTP(); // telling the class to use SMTP
	$mail->SMTPDebug  =4;                     // enables SMTP debug information (for testing)
											   // 1 = errors and messages
											   // 2 = messages only
	$mail->Mailer = "smtp"; 
	$mail->SMTPAutoTLS = false;
	$mail->SMTPAuth   = true;                  // enable SMTP authentication
	$mail->SMTPSecure = "tls";  
	$mail->Host       = "smtp.gmail.com";//"mail.dbdistribuidora.com"; //;      // SMTP server
	$mail->Port       = 587;                   // SMTP port
	$mail->Username   = "garofolo.leonel@gmail.com";//"ventasapp@dbdistribuidora.com";//"fernando.ariel.tello@gmail.com";//;  // username
	$mail->Password   = "30121Daddy";//"Elefante01"; //; // password
	$mail->SetFrom('garofolo.leonel@gmail.com', 'Ventas APP');
	
	$sendSotck = false;
	$sendLimit = false;
	$total = 0.00;
	$styleRow = "style=\"border: 1px solid #dddddd;text-align: left;padding: 8px;\"";
	$styleRowSinStock = "style=\"border: 1px solid #dddddd;text-align: left;padding: 8px;background-color:#F79F81;\"";
	$styleBackgroundHeader = "style=\"background-color: #3F51B5;text-align: center;color:#FFFFFF\"";
	$subject = "";
	$body = "";
	$intro = "";
	$sign = "";
	$products = "";
	$total = 0.0;
	
	//*************************************************
	//*************************************************
	// TABLA PEDIDOS 
	//*************************************************
	//*************************************************
	
	$products = "<table style=\"font-family: arial, sans-serif;border-collapse: collapse;width: 100%;\">";
	//Cliente y Vendedro
	$products .= "<tr " . $styleRow . "><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del cliente</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del Vendedor</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Despacho</td></tr>";
	$products .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\" ><b>Fecha Creaci�n:</b> %s</td></tr>", $presupuesto->shoppingCart->client->nom_com, $presupuesto->shoppingCart->user->name, $presupuesto->date);
	
	// Productos
	
	$products .= "<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Productos del Presupuesto</td></tr>";
	$products .= "<tr " . $styleRow . "><th>Nombre</th><th>C�digo</th><th>Cantidad</th><th></th><th>Precio unidad (U\$S)</th><th>Precio Total (U\$S)</th></tr>";
	
	foreach ($presupuesto->shoppingCart->shoppingProducts as $product)
	{
		$total = $total + ($product->quantity * $product->PRECIO);
		$products .= sprintf(
			"<tr " . $styleRow . "><td>%s</td><td>%s</td><td>%.2f</td><td></td><td>%.2f %s</td><td>%.2f</td></tr>"
			, $product->DESCRIPCION, $product->COD_ARTICULO, $product->quantity, $product->PRECIO, ($product->SIGLA_MEDIDA === NULL)?"":$product->SIGLA_MEDIDA, ($product->quantity * $product->PRECIO));
	}
	
		
	// Total
	// Agregamos IVA
	$products .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>SUBTOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total);
	$products .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>IVA (U\$S)</b></td><td><b>%.2f</b></td></tr>",$total*0.21);
	$products .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>TOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total*1.21);

	// Observaciones
	$products .= sprintf("<tr " . $styleRow . "><td colspan=\"6\" ".$styleBackgroundHeader.">Observaciones</td></tr><tr " . $styleRow . "><td colspan=\"6\">%s</td></tr>", ($presupuesto->shoppingCart->comment === NULL) ? "Sin observaciones" : $presupuesto->shoppingCart->comment);

	$products .= "</table>";

	//*************************************************
	//*************************************************
	// PRESUPUESTO SIN STOCK
	//*************************************************
	//*************************************************
	#region Tabla presupuesto Sin Stock
	$total = 0.0;
	$productsSinStock = "<table style=\"font-family: arial, sans-serif;border-collapse: collapse;width: 100%;\">";
	//Cliente y Vendedro
	$productsSinStock .= "<tr " . $styleRow . "><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del cliente</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del Vendedor</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Despacho</td></tr>";
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Nombre:</b> %s</td><td colspan=\"2\"><b>Fecha Creaci�n:</b> %s</td></tr>", $presupuesto->shoppingCart->client->nom_com, $presupuesto->shoppingCart->user->name, $presupuesto->date);
	
	//Productos
	$productsSinStock .= "<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Productos sin stock</td></tr>";
	$productsSinStock .= "<tr " . $styleRow . "><th>Nombre</th><th>C�digo</th><th>Cantidad Solicitada</th><th>Cantidad Faltante</th><th>Precio unidad (U\$S)</th><th>Precio Total (U\$S)</th></tr>";
	$style = "";
	$stockFaltante = 0.0;
	foreach ($presupuesto->shoppingCart->shoppingProducts as $product)
	{

		if (($product->STOCK - $product->quantity) < 0)
		{
			$style = $styleRowSinStock;
			$stockFaltante = ($product->quantity - $product->STOCK);
			$sendSotck = true;
		}
		else
		{
			$style = $styleRow;
			$stockFaltante = 0;
		}

		$total = $total + ($product->quantity * $product->PRECIO);
		$productsSinStock .= sprintf(
			"<tr " . $style . "><td>%s</td><td>%s</td><td>%.2f</td><td>%.2f</td><td>%.2f %s</td><td>%.2f</td></tr>"
			, $product->DESCRIPCION, $product->COD_ARTICULO, $product->quantity, $stockFaltante, $product->PRECIO, ($product->SIGLA_MEDIDA === NULL)?"":$product->SIGLA_MEDIDA, ($product->quantity * $product->PRECIO));
	}

	// Total
	// Agregamos IVA
	//total = total * 1.21;
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>SUBTOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total);
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>IVA (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total*0.21);
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>TOTAL (U\$S)</b></td><td><b>%.2f</b></td></tr>", $total*1.21);

	// Observaciones
	$productsSinStock .= sprintf("<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Observaciones</td></tr><tr " . $styleRow . "><td colspan=\"6\">%s</td></tr>", ($presupuesto->shoppingCart->comment === NULL) ? "Sin observaciones" : $presupuesto->shoppingCart->comment);

	$productsSinStock .= "</table>";
	//endregion
	
	//*************************************************
	//*************************************************
	// #region Tabla presupuesto l�mite superado
	//*************************************************
	//*************************************************
	
	$total = 0.0;
	$productsLimite = "<table style=\"font-family: arial, sans-serif;border-collapse: collapse;width: 100%;\">";
	//Cliente y Vendedro
	
	$productsLimite .= "<tr " . $styleRow . "><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del cliente</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Datos del Vendedor</td><td colspan=\"2\" " . $styleBackgroundHeader . ">Despacho</td></tr>";		
	
	//Productos
	$productsLimite .= "<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Productos del Presupuesto</td></tr>";
	$productsLimite .= "<tr " . $styleRow . "><th>Nombre</th><th>C�digo</th><th>Cantidad</th><th></th><th>Precio unidad (U\$S)</th><th>Precio Total (U\$S)</th></tr>";
	foreach ($presupuesto->shoppingCart->shoppingProducts as $product)
	{
		$total = $total + ($product->quantity * $product->PRECIO);
		$productsLimite .= sprintf(
			"<tr " . $styleRow . "><td>%s</td><td>%s</td><td>%.2f</td><td></td><td>%.2f %s</td><td>%.2f</td></tr>"
			, $product->DESCRIPCION, $product->COD_ARTICULO, $product->quantity, $product->PRECIO, $product->SIGLA_MEDIDA, $product->totalPrice);
	}
	
	// Total
	// Agregamos IVA
	$sendLimit = false;
	$limite = ((($presupuesto->shoppingCart->client->cupo_credi/ $dolar)) - ($presupuesto->shoppingCart->client->saldo_cc/ $dolar));
	if (($total*1.21) > $limite)
	{
		$sendLimit = true;
	}
	$productsLimite .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>TOTAL (U\$S)</b></td><td><b>%.2f + IVA (%.2f)</b></td></tr>", $total, $total*0.21);
	$productsLimite .= sprintf("<tr " . $styleRow . "><td></td><td></td><td></td><td></td><td><b>L�MITE (U\$S)</b></td><td><b>%.2f</b></td></tr>", $limite);
	$productsLimite .= sprintf("<tr " . $styleRowSinStock . "><td></td><td></td><td></td><td></td><td><b>EXCESO (U\$S)</b></td><td><b>%.2f</b></td></tr>", ($total*1.21)-$limite);

	 // Observaciones
	$productsLimite .= sprintf("<tr " . $styleRow . "><td colspan=\"6\" " . $styleBackgroundHeader . ">Observaciones</td></tr><tr " . $styleRow . "><td colspan=\"6\">%s</td></tr>", ($presupuesto->shoppingCart->comment=== NULL) ? "Sin observaciones" : $presupuesto->shoppingCart->comment);


	$productsLimite .= "</table>";
	
	//Alerta de entrega especial.Posibilidad de mail a log�stica
	//********************************************************
	//El metodo que envia debe contener lo siguiente
	if ($presupuesto->shoppingCart->user->name === $presupuesto->shoppingCart->client->nom_com)
	{
		$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un presupuesto. A continuaci�n se describen los detalles del mismo:<br/><br/>", $presupuesto->shoppingCart->user->rolName,  $presupuesto->shoppingCart->client->nom_com);
	}
	else
	{
		$intro = sprintf("<br/>    El %s <b>%s</b> ha realizado un presupuesto para el cliente <b>%s</b>. A continuaci�n se describen los detalles del mismo:<br/><br/>", $presupuesto->shoppingCart->user->rolName, $presupuesto->shoppingCart->user->name, $presupuesto->shoppingCart->client->nom_com);
	}
	
	
	$sign = "<br/>DB Distribuidora Argentina S.A.<br/>Francisco N. Laprida 5052, <br/>Villa Martelli, Gran Buenos Aires, Argentina";
	$subject = "DB Distribuidora - Presupuesto Realizado";
	$body = $intro.$products.$sign; 
	
	$mail->CharSet = 'UTF-8';
	$mail->Subject = $subject;
	$mail->MsgHTML($body);
	$mail->ClearAddresses();
	$mail->ClearCCs();
	$mail->ClearBCCs();
	$mail->AddAddress('garofolo.leonel@gmail.com', 'Leonel Garofolo');
	/* DESCOMENTAR
	$mail->AddAddress('dcolla@dbdistribuidora.com', 'Daniel Colla');		
	if (!is_null($presupuesto->shoppingCart->user->user_email) && isset($presupuesto->shoppingCart->user->user_email))
	{
		$mail->AddCC($presupuesto->shoppingCart->user->user_email, $presupuesto->shoppingCart->user->name);
	}
	*/
	$mail->Send();
	$mail->ClearAddresses();
	$mail->ClearCCs();
	$mail->ClearBCCs();	
	//#endregion
}
function getStringValue($input)
{
	return ($input === NULL)?"":$input;
}

function getIntValue($input)
{
	return ($input === NULL)?0:$input;
}

$app->run();