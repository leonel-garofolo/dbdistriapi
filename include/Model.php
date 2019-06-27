<?php 
class User
{ 
    public $id;
    public $name;
	public $user_email;
	public $rol;
    public $rolName; 
	public $typeConection; 
	public $lastConnection;
	public $nro_lista_user;	
} 

class Client extends User {
    public $nom_com ;
    public $cod_client;
    public $cod_vended ;
    public $nro_lista;
    public $cuit;
    public $dir_com;
    public $e_mail ;
    public $localidad ;
    public $razon_soci ;
    public $telefono_1 ;
    public $cupo_credi ;
    public $saldo_cc;
    public $textToFilter;
	public $id_direccion_entrega;
	public $talonario;
    public $cod_transp;
    public $historial_productos;
	
	function __construct() 
	{
		
		$this->nom_com = "";
		$this->cod_client = "";
		$this->cod_vended = "";
		$this->cuit = "";
		$this->nro_lista = 0;
		$this->dir_com = "";
		$this->e_mail = "";
		$this->localidad = "";
		$this->razon_soci = "";
		$this->telefono_1 = "";
		$this->cupo_credi = 0.0;
		$this->saldo_cc = 0.0;
		$this->textToFilter = "";
		$this->id_direccion_entrega = "";
		$this->talonario = 0;
        $this->cod_transp = "";
	}
}

class HistorialProducto 
{
    public $cod_articulo;
    public $descripcion;
}

class Product 
{
    public $COD_ARTICULO;
    public $DESCRIPCION;
    public $SINONIMO;
    public $DESCRIPCION_AD;
    public $PRECIO;
    public $NRO_LISTA;
	public $STOCK;
    public $STOCK_COMPROMETIDO;
    public $EMPAQUE;
    public $STOCK_A_RECEPCIONAR;
    public $SIGLA_MEDIDA;
    public $CANT_DECIMAL_MEDIDA;
    public $CLIENTE;
	
	function __construct() 
	{
		$this->COD_ARTICULO = "";
		$this->DESCRIPCION = "";
		$this->SINONIMO = "";
		$this->DESCRIPCION_AD = "";
		$this->PRECIO = 0.0;
		$this->STOCK = 0.0;
		$this->STOCK_COMPROMETIDO = 0.0;
		$this->EMPAQUE = 0.0;
		$this->STOCK_A_RECEPCIONAR = 0.0;
		$this->SIGLA_MEDIDA = "";
		$this->CANT_DECIMAL_MEDIDA = 0;
		$this->CLIENTE = "";
	}
}

class ShoppingProduct extends Product
{
	public $quantity;
    public $totalPrice;
    public $textToFilter;
	
	function __construct() 
	{
		$this->quantity = 0.0;
		$this->totalPrice = 0.0;
		$this->textToFilter = "";
	}
}


//Order

class ShoppingCart
{
    public $shoppingProducts;
    public $client;
    public $user;
    public $comment;
    public $specialShipping;
	
	function __construct() {
       $this->shoppingProducts = array();
	   $this->client = new Client;
	   $this->user = new User;
	   $this->comment = "";
	   $this->specialShipping = false;
   }
}
	
class Order
{
    public $id;
    public $shoppingCart;
    public $state;
    public $date;
    public $deliveryDate;
	
	function __construct() {
       $this->shoppingCart = new ShoppingCart;
	   $this->id = "";
	   $this->state = "";
	   $this->date = "";
	   $this->deliveryDate = "";
   }
}

class Budget
{
    public $id;
    public $shoppingCart;
    public $state;
    public $date;
    
	function __construct() {
       $this->shoppingCart = new ShoppingCart;
	   $this->id = "";
	   $this->state = "";
	   $this->date = "";
   }
}

class Config
{
	public $id;
	public $name;
	public $value;
}

class Category
{
	public $id;
	public $name;
	public $hierarchy;
	public $inactive;
}

class Transport
{
	public $id;
	public $categ_tran;
	public $dom_trans;
	public $nombre_tra;
	public $localidad;
	
}

class Autorization
{
    public $id;
    public $shoppingCart;
    public $state;
    public $date;
	public $deliveryDate;
	public $type;
	
	function __construct() {
       $this->shoppingCart = new ShoppingCart;
	   $this->id = "";
	   $this->state = "";
	   $this->date = "";
	   $this->deliveryDate = "";
	   $this->type = 6;
   }
}

class Push {
 
    // push message title
    private $title;
    private $message;
    private $image;
    // push message payload
    private $data;
    // flag indicating whether to show the push
    // notification or not
    // this flag will be useful when perform some opertation
    // in background when push is recevied
    private $is_background;
 
    function __construct() {
         
    }
 
    public function setTitle($title) {
        $this->title = $title;
    }
 
    public function setMessage($message) {
        $this->message = $message;
    }
 
    public function setImage($imageUrl) {
        $this->image = $imageUrl;
    }
 
    public function setPayload($data) {
        $this->data = $data;
    }
 
    public function setIsBackground($is_background) {
        $this->is_background = $is_background;
    }
 
    public function getPush() {
        $res = array();
        $res['data']['title'] = $this->title;
        $res['data']['is_background'] = $this->is_background;
        $res['data']['message'] = $this->message;
        $res['data']['image'] = $this->image;
        $res['data']['payload'] = $this->data;
        $res['data']['timestamp'] = date('Y-m-d G:i:s');
        return $res;
    }
 
}