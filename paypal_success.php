<?php  include_once("../../config.php");
require '../../lib/authorize-api/vendor/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

define("AUTHORIZENET_LOG_FILE", "phplog");

if(!isset($_GET['id']) || empty($_GET['id']))
{
    // header("Location: http://www.boostlashenhancer.com/order.php");
    // exit();
}
$id = $_GET['id'];

$sql = "SELECT 	transaction_id, come_back, package_id, price, shipping, basket_id  from paypal WHERE id=:id  ";
$statement = $db->prepare($sql);
$statement->execute(array( ':id' => $id));
$statement->execute();
list($transaction_id, $come_back, $package_id, $price, $shipping, $basket_id) = $statement->fetch();

$qty   = 1;

if($come_back == 1)
{
    // header("Location: http://www.boostlashenhancer.com/order.php");
    // exit();
}


function payPalAuthorizeCaptureContinue($refTransId, $payerID, $id, $token, $pid, $price, $qty, $shipping, $basket_id)
{


    /* Create a merchantAuthenticationType object with authentication details
       retrieved from the constants file */
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName("2d9V7SES2kXz");
    $merchantAuthentication->setTransactionKey("838Mqc4TtZDw2B3D");

    // Set the transaction's refId
    $refId = 'ref' . time();

    // Set PayPal compatible merchant credentials
    $payPalType=new AnetAPI\PayPalType();
    $payPalType->setPayerID($payerID);

    $paymentOne = new AnetAPI\PaymentType();
    $paymentOne->setPayPal($payPalType);

    // Create an authorize and capture continue transaction
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType( "authCaptureContinueTransaction");
    $transactionRequestType->setPayment($paymentOne);
    $transactionRequestType->setRefTransId($refTransId);

    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest( $transactionRequestType);
    $controller = new AnetController\CreateTransactionController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

    if ($response != null)
    {
        if($response->getMessages()->getResultCode() =='Ok')
        {
            $tresponse = $response->getTransactionResponse();

            if ($tresponse != null && $tresponse->getMessages() != null)
            {


                $sql = "UPDATE paypal SET final_code=:final_code, final_description_txt=:final_description_txt, payerid=:payerid, token=:token WHERE id=:id ";
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':final_code'            => $tresponse->getResponseCode(),
                    ':final_description_txt' => $tresponse->getMessages()[0]->getDescription(),
                    ':payerid'               => $payerID,
                    ':token'                 => $token,
                    ':id'                    => $id
                ));
                //die('hereeee');
                $address = payPalGetDetails($refTransId);
                // print_r($address);

                if(empty($address['lname'])) $address['lname'] = ' ';

                $added_date = date('Y-m-d H:i:s');

                $note = '';
                // if(in_array($pid, array(8,9,11))) $note = 'Special Offer Free EYELASH VOLUMIZER';

                addemail($address['email']);

                $sql = 'INSERT INTO orders (
             fname, lname, email, country, address1, address2, city, state, zipcode, same_as, billing_fname, billing_lname, billing_country, billing_address1, billing_city, billing_state, billing_zipcode, phone, card_number, cvv, month, year, package_id, shipping_id ,token, qty, note ,added_date
            )value(
            :fname, :lname, :email, :country, :address1, :address2, :city, :state, :zipcode,:same_as, :billing_fname, :billing_lname, :billing_country, :billing_address1, :billing_city, :billing_state,:billing_zipcode,  :phone, :card_number, :cvv, :month, :year, :package_id, :shipping_id ,:token, :qty, :note ,:added_date
            )';
                $GLOBALS['db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':fname'              => $address['fname'],
                    ':lname'              => $address['lname'],
                    ':email'              => $address['email'],
                    ':country'            => $address['country'],
                    ':address1'           => $address['address1'],
                    ':address2'           => '',
                    ':city'               => $address['city'],
                    ':state'              => $address['state'],
                    ':zipcode'            => $address['zipcode'],
                    ':same_as'            => '1',
                    ':billing_fname'      => $address['fname'],
                    ':billing_lname'      => $address['lname'],
                    ':billing_country'    => $address['country'],
                    ':billing_address1'   => $address['address1'],
                    ':billing_city'       => $address['city'],
                    ':billing_state'      => $address['state'],
                    ':billing_zipcode'    => $address['zipcode'],
                    ':phone'              => '',
                    ':card_number'        => 'paypal',
                    ':cvv'                => '',
                    ':month'              => '',
                    ':year'               => '',
                    ':package_id'         => $pid,
                    ':shipping_id'        => $shipping,
                    ':qty'                => $qty,
                    ':note'               => $note,
                    ':added_date'         => $added_date,
                    ':token'              => $token
                ));
                //  print_r($GLOBALS['db']->errorInfo());
                $insertedId = $GLOBALS['db']->lastInsertId();
                //  echo 'ID:'.$insertedId;



                $sql = "UPDATE orders SET customer_id= :customer_id , payment_id=:payment_id, price=:price, currency='usd', status=:status where id=:id ";
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':customer_id'    => $payerID,
                    ':payment_id'     => $id,
                    ':price'          => $price,
                    ':status'         => 'succeeded',
                    ':id'             => $insertedId
                ));


                $sql = "UPDATE basket SET deleted = :deleted  WHERE user_id =:user_id ";
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':deleted' => 2,
                    ':user_id' => $basket_id
                ));

                unset($_COOKIE['user_id']);
                setcookie('user_id', null, -1, '/');
                unset($_SESSION['user']['id']);

                header("Location: special-offer.php?s=1&order_id=".$insertedId.'&method=p');


            }
            else
            {

                header("Location: cart.php?selected&pid=1&paypal=fail&code=".base64_encode($tresponse->getErrors()[0]->getErrorCode())."&msg=".base64_encode($tresponse->getErrors()[0]->getErrorText()) );
                exit();

            }
        }
        else
        {
            $tresponse = $response->getTransactionResponse();
            if($tresponse != null && $tresponse->getErrors() != null)
            {
                header("Location: cart.php?selected&pid=1&paypal=fail&code=".base64_encode($tresponse->getErrors()[0]->getErrorCode())."&msg=".base64_encode($tresponse->getErrors()[0]->getErrorText()) );
                exit();
            }
            else
            {
                header("Location: cart.php?selected&pid=1&paypal=fail&code=".base64_encode($response->getMessages()->getMessage()[0]->getCode())."&msg=".base64_encode($response->getMessages()->getMessage()[0]->getText()) );
                exit();
            }
        }
    }
    else
    {
        echo  "No response returned \n";
    }

    return $response;
}

function payPalGetDetails($transactionId)
{

    echo "PayPal Get Details Transaction\n";
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName("2d9V7SES2kXz");
    $merchantAuthentication->setTransactionKey("838Mqc4TtZDw2B3D");

    // Set the transaction's refId
    $refId = 'ref' . time();

    //create a transaction of type get details
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType( "getDetailsTransaction");

    //replace following transaction ID with your transaction ID for which the details are required
    $transactionRequestType->setRefTransId($transactionId);

    // Create the payment data for a paypal account
    $payPalType = new AnetAPI\PayPalType();
    //$payPalType->setCancelUrl("http://www.merchanteCommerceSite.com/Success/TC25262");
    //$payPalType->setSuccessUrl("http://www.merchanteCommerceSite.com/Success/TC25262");
    $paymentOne = new AnetAPI\PaymentType();
    $paymentOne->setPayPal($payPalType);

    $transactionRequestType->setPayment($paymentOne);

    //create a transaction request
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId( $refId);
    $request->setTransactionRequest( $transactionRequestType);

    $controller = new AnetController\CreateTransactionController($request);

    //execute the api call to get transaction details
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

    if ($response != null)
    {
        if($response->getMessages()->getResultCode() == 'Ok' )
        {
            $tresponse = $response->getTransactionResponse();


            if ($tresponse != null && $tresponse->getMessages() != null)
            {

                $shipping_response = $tresponse->getShipTo();
                if(null != $shipping_response)
                {
                    $address = array('fname'     => $shipping_response->getFirstName(),
                        'lname'     => $shipping_response->getLastName(),
                        'address1'  => $shipping_response->getAddress(),
                        'city'      => $shipping_response->getCity(),
                        'state'     => ($shipping_response->getState() == '' ? 'NO STATE' : $shipping_response->getState()),
                        'zipcode'   => $shipping_response->getZip(),
                        'country'   => $shipping_response->getCountry(),
                        'email'     => $tresponse->getSecureAcceptance()->getpayerEmail(),
                    );
                    return $address;

                }

                echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";

            }
            else
            {
                echo "Transaction Failed \n";
                if($tresponse->getErrors() != null)
                {
                    echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                    echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                }
            }
        }
        else
        {
            echo "Transaction Failed \n";
            $tresponse = $response->getTransactionResponse();
            if($tresponse != null && $tresponse->getErrors() != null)
            {
                echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
            }
            else
            {
                echo " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
                echo " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
            }
        }
    }
    else
    {
        echo  "No response returned \n";
    }

    return $response;
}




if($come_back == 0 && isset( $_GET['PayerID'] ) )
{
    payPalAuthorizeCaptureContinue( $transaction_id ,  $_GET['PayerID'], $id,  $_GET['token'], $package_id, $price, $qty, $shipping, $basket_id ) ;

    $sql = "UPDATE paypal SET come_back='1' WHERE id=:id ";
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':id'               => $id,));

}


?>
