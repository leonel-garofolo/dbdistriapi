<?php 
function getUserData($userName){
    $user = new User;
    $link = mssql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD) or die("Couldn't connect to SQL Server on $myServer. Error: " . mssql_get_last_message());;
	mssql_select_db(DB_NAME, $link);
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
        if ($userName == 'admin' || $userName == 'admin2' || $userName == 'admin3')
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
	return echoResponse(200, $user);
}