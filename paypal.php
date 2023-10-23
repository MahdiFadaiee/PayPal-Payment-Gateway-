<?php include_once 'config.php';

$recipient  = (empty($_POST['recipient'])       ? ' ' : $_POST['recipient']  );
$line1      = (empty($_POST['line1'])           ? ' ' : $_POST['line1']  );
$line2      = (empty($_POST['line2'])           ? '' : $_POST['line2']  );
$city       = (empty($_POST['city'])            ? ' ' : $_POST['city']  );
$state      = (empty($_POST['state'])           ? ' ' : $_POST['state']  );
$zip        = (empty($_POST['zip'])             ? ' ' : $_POST['zip']  );
$country    = (empty($_POST['country'])         ? ' ' : $_POST['country']  );
$payment_id = (empty($_POST['payment_id'])      ? ' ' : $_POST['payment_id']  );
$amount     = (empty($_POST['amount'])          ? ' ' : $_POST['amount']  );
$status     = (empty($_POST['status'])          ? ' ' : $_POST['status']  );
$user_id    = (empty($_POST['user_id'])         ? ' ' : $_POST['user_id']  );
$email      = (empty($_POST['email'])           ? ' ' : $_POST['email']  );
$shipping   = (empty($_POST['shipping'])        ? ' ' : $_POST['shipping']  );
$cart_id    = (empty($_POST['cart_id'])         ? ' ' : $_POST['cart_id']  );
$token_id   = (empty($_POST['token_id'])        ? ' ' : $_POST['token_id']  );
$avs        = (empty($_POST['avs'])             ? ' ' : $_POST['avs']  );

$status      =($_POST['status'] == 'completed'  ? 'succeeded' : ' ' );

$qty        = 1;
$note       = '';
$added_date = date('Y-m-d H:i:s');
addemail($email);

$logFile = '/home/customer/www/boostlash.com/public_html/boostlash.com-log';
error_log($added_date."\n" , 3, $logFile);
error_log('recipient:'.$recipient."\n" , 3, $logFile);
error_log('line1:'.$line1."\n" , 3, $logFile);
error_log('line2:'.$line2."\n" , 3, $logFile);
error_log('city:'.$city."\n" , 3, $logFile);
error_log('state:'.$state."\n" , 3, $logFile);
error_log('zip:'.$zip."\n" , 3, $logFile);
error_log('country:'.$country."\n" , 3, $logFile);
error_log('payment_id:'.$payment_id."\n" , 3, $logFile);
error_log('amount:'.$amount."\n" , 3, $logFile);
error_log('status:'.$status."\n" , 3, $logFile);
error_log('user_id:'.$user_id."\n" , 3, $logFile);
error_log('email:'.$email."\n" , 3, $logFile);
error_log('shipping:'.$shipping."\n" , 3, $logFile);
error_log('cart_id:'.$cart_id."\n" , 3, $logFile);
error_log('token_id:'.$token_id."\n" , 3, $logFile);
error_log('avs:'.$avs."\n" , 3, $logFile);
error_log('status:'.$status."\n" , 3, $logFile);


$pid = array();
            $statement = $db->prepare("SELECT  id FROM basket WHERE user_id='{$_SESSION['user']['id']}' and deleted='0' ");
            $statement->execute();
            while( $row = $statement->fetch() ){
                array_push($pid, $row['id']);
            }

            $device         = getDevice();
            $domain_url     = getCurrentURL();

$sql = 'INSERT INTO orders (
             fname, lname, email, country, address1, address2, city, state, zipcode, same_as, billing_fname, billing_lname, billing_country, billing_address1, billing_city, billing_state, billing_zipcode, phone, card_number, cvv, month, year, package_id, shipping_id ,token, qty, note ,added_date, avs, device, domain_url
            )value(
            :fname, :lname, :email, :country, :address1, :address2, :city, :state, :zipcode,:same_as, :billing_fname, :billing_lname, :billing_country, :billing_address1, :billing_city, :billing_state,:billing_zipcode,  :phone, :card_number, :cvv, :month, :year, :package_id, :shipping_id ,:token, :qty, :note ,:added_date, :avs, :device, :domain_url
            )';
$GLOBALS['db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$statement = $GLOBALS['db']->prepare($sql);
$statement->execute(array(':fname'              => $recipient,
    ':lname'              => ' ',
    ':email'              => $email,
    ':country'            => $country,
    ':address1'           => $line1,
    ':address2'           => $line2,
    ':city'               => $city,
    ':state'              => $state,
    ':zipcode'            => $zip,
    ':same_as'            => '1',
    ':billing_fname'      => $recipient,
    ':billing_lname'      => ' ',
    ':billing_country'    => $country,
    ':billing_address1'   => $line1,
    ':billing_city'       => $city,
    ':billing_state'      => $state,
    ':billing_zipcode'    => $zip,
    ':phone'              => '',
    ':card_number'        => 'paypal',
    ':cvv'                => '',
    ':month'              => '',
    ':year'               => '',
    ':package_id'         => implode(',', $pid),
    ':shipping_id'        => $shipping,
    ':qty'                => $qty,
    ':note'               => $note.' '.(isset($_SESSION['promotion_code']) ?  $_SESSION['promotion_code'] : '' ),
    ':added_date'         => $added_date,
    ':avs'                => $avs,
    ':token'              => $payment_id,
    ':device'             => $device,
    ':domain_url'         => $domain_url
));

$insertedId = $GLOBALS['db']->lastInsertId();
error_log('error : '.implode($GLOBALS['db']->errorInfo())."\n" , 3, $logFile);
error_log('order added:'.$insertedId."\n" , 3, $logFile);



$sql = "UPDATE orders SET customer_id= :customer_id , payment_id=:payment_id, price=:price, currency='usd', status=:status, fellow=:fellow where id=:id ";
$statement = $GLOBALS['db']->prepare($sql);
$statement->execute(array(':customer_id'    => $token_id,
    ':payment_id'     => $cart_id,
    ':price'          => ($amount*100),
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


?>
