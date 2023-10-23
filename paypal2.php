<?php include_once '../../config.php';

$token     = $_REQUEST['token'];

if(isset($_GET['user_id']))
{
    $_SESSION['user']['id']=$_GET['user_id'];
}


if(isset($token))
{
    require '../../lib/paypal/autoload.php';
    var_dump("File autoload.php loaded successfully!"); 
    $apiContext = new \PayPal\Rest\ApiContext(
        new \PayPal\Auth\OAuthTokenCredential(
            'ASN_fpj7GC2Ck_mBe9fs65Z_5u2uQikR__wVEda0H26h4wQK8aQv2doPstG2kU6KN37Q6XzZ1xZnWWEX',     // ClientID
            'EL5M8fjxcI4nLCc5biHQ85PbT25AEvDNRWq3aKFXsSjgO0kyJ9ksIrxTdOay1wdx6t_wocldBTQmlJ67'      // ClientSecret
        )
    );
    $apiContext->setConfig(
        array(
            'mode' => 'sandbox',
            'log.LogEnabled' => true,
            'log.FileName' => 'PayPal.log',
            'log.LogLevel' => 'DEBUG'
        )
    );

//print '<pre>';
//var_dump($apiContext);
//https://github.com/paypal/PayPal-PHP-SDK/wiki/Installation-Direct-Download
//https://www.formget.com/paypal-transaction-details/
//$paymentID = $_POST['paymentID'];


    $info      = array();

    $payment = new \PayPal\Api\Payment();
    $all = $payment->get($token, $apiContext);
    $obj = json_decode($all);




    $info['recipient']  = (empty($obj->payer->payer_info->shipping_address->recipient_name)  ? ' ' : $obj->payer->payer_info->shipping_address->recipient_name);
    $info['line1']      = (empty($obj->payer->payer_info->shipping_address->line1)           ? ' ' : $obj->payer->payer_info->shipping_address->line1);
    $info['line2']      = (empty($obj->payer->payer_info->shipping_address->line2)           ? ' ' : $obj->payer->payer_info->shipping_address->line2);
    $info['city']       = (empty($obj->payer->payer_info->shipping_address->city)            ? ' ' : $obj->payer->payer_info->shipping_address->city);
    $info['state']      = (empty($obj->payer->payer_info->shipping_address->state)           ? ' ' : $obj->payer->payer_info->shipping_address->state);
    $info['zip']        = (empty($obj->payer->payer_info->shipping_address->postal_code)     ? ' ' : $obj->payer->payer_info->shipping_address->postal_code);
    $info['country']    = (empty($obj->payer->payer_info->shipping_address->country_code)    ? ' ' : $obj->payer->payer_info->shipping_address->country_code);
    $info['payment_id'] = (empty($obj->payer->payer_info->payer_id)                          ? ' ' : $obj->payer->payer_info->payer_id  );
    $info['amount']     = (empty($obj->transactions[0]->amount->total)                       ? ' ' : $obj->transactions[0]->amount->total);
    $info['user_id']    = (empty($_POST['user_id'])                                          ? ' ' : $_POST['user_id']  );
    $info['email']      = (empty($obj->payer->payer_info->email)                             ? ' ' : $obj->payer->payer_info->email);
    $info['shipping']   = (empty($obj->transactions[0]->amount->details->shipping)           ? ' ' : $obj->transactions[0]->amount->details->shipping);
    $info['cart_id']    = (empty($obj->cart)                                                 ? ' ' : $obj->cart  );
    $info['token_id']   = (empty($token)                                                     ? ' ' : $token  );
    $info['avs']        = (empty($obj->payer->status)                                        ? ' ' : $obj->payer->status);

    $status      =($obj->transactions[0]->related_resources[0]->sale->state == 'completed'  ? 'succeeded' : $obj->transactions[0]->related_resources[0]->sale->state );



    $qty        = 1;
    $note       = '';
    $note       = (isset($_SESSION['source']) ?  $_SESSION['source'] : '' );
    $added_date = date('Y-m-d H:i:s');
    addemail($email);

    $pid = array();
    $statement = $db->prepare("SELECT  id FROM basket WHERE user_id='{$_SESSION['user']['id']}' and deleted='0' ");
    $statement->execute();
    while( $row = $statement->fetch() ){
        array_push($pid, $row['id']);
    }

    $device         = getDevice();
    $domain_url     = getCurrentURL();


    $sql = 'INSERT INTO orders (
fname, lname, email, country, address1, address2, city, state, zipcode, same_as, billing_fname, billing_lname, billing_country, billing_address1, billing_city, billing_state, billing_zipcode, phone, card_number, cvv, month, year, package_id, shipping_id ,token, qty, note ,added_date, avs, product_type, device, domain_url
)value(
:fname, :lname, :email, :country, :address1, :address2, :city, :state, :zipcode,:same_as, :billing_fname, :billing_lname, :billing_country, :billing_address1, :billing_city, :billing_state,:billing_zipcode,  :phone, :card_number, :cvv, :month, :year, :package_id, :shipping_id ,:token, :qty, :note ,:added_date, :avs, :product_type, :device, :domain_url
)';
    $GLOBALS['db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':fname'              => $info['recipient'],
        ':lname'              => ' ',
        ':email'              => $info['email'],
        ':country'            => $info['country'],
        ':address1'           => $info['line1'] ,
        ':address2'           => $info['line2'] ,
        ':city'               => $info['city'],
        ':state'              => $info['state'],
        ':zipcode'            => $info['zip'],
        ':same_as'            => '1',
        ':billing_fname'      => $info['recipient'],
        ':billing_lname'      => ' ',
        ':billing_country'    => $info['country'],
        ':billing_address1'   => $info['line1'] ,
        ':billing_city'       => $info['city'],
        ':billing_state'      => $info['state'],
        ':billing_zipcode'    => $info['zip'],
        ':phone'              => '',
        ':card_number'        => 'paypal',
        ':cvv'                => '',
        ':month'              => '',
        ':year'               => '',
        ':package_id'         => implode(',', $pid),
        ':shipping_id'        => $info['shipping'],
        ':qty'                => $qty,
        ':note'               => $note.' '.(isset($_SESSION['promotion_code']) ?  $_SESSION['promotion_code'] : '' ),
        ':added_date'         => $added_date,
        ':avs'                => $info['avs'],
        ':token'              => $info['token_id'],
        ':product_type'       => 'cutiebeauti',
        ':device'             => $device,
        ':domain_url'         => $domain_url
    ));

    $insertedId = $GLOBALS['db']->lastInsertId();
    $logFile = '/home/customer/www/cutiebeauti.com/public_html/cutiebeauti.com-log';
    error_log($added_date."\n" , 3, $logFile);
    error_log('Error : '.implode($GLOBALS['db']->errorInfo())."\n" , 3, $logFile);
    error_log('Order ID : '.$insertedId."\n" , 3, $logFile);
    error_log('Email : '.$info['email']."\n" , 3, $logFile);
    error_log('Package ID : '.implode(',', $pid)."\n" , 3, $logFile);



    $sql = "UPDATE orders SET customer_id= :customer_id , payment_id=:payment_id, price=:price, currency='usd', status=:status, fellow=:fellow where id=:id ";
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':customer_id'    => 'EC-'.$info['cart_id'],
        ':payment_id'     => $info['payment_id'],
        ':price'          => ($info['amount']*100),
        ':status'         => $status,
        ':fellow'         =>($_SESSION['facebook'] ==1 ? ' -- comefromfacebook' : ''),
        ':id'             => $insertedId
    ));


    $sql = "UPDATE basket SET deleted = :deleted  WHERE user_id =:user_id ";
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':deleted' => 2,
        ':user_id' => $_SESSION['user']['id']
    ));

    unset($_COOKIE['user_id']);
    setcookie('user_id', null, -1, '/');
    unset($_SESSION['user']['id']);
    echo $insertedId;
}
?>
