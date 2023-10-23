<?php  include_once("config.php");
require 'lib/authorize-api/vendor/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
include("lib/phpmailerv2/PHPMailerAutoload.php");
define("AUTHORIZENET_LOG_FILE", "phplog");

if(!isset($_GET['id']) || empty($_GET['id']))
{
    // header("Location: http://www.boostlashenhancer.com/order.php");
    // exit();
}
$id = $_GET['id'];

$sql = "SELECT 	transaction_id, come_back, package_id, price, shipping, upsell_parent_id  from paypal WHERE id=:id  ";
$statement = $db->prepare($sql);
$statement->execute(array( ':id' => $id));
$statement->execute();
list($transaction_id, $come_back, $package_id, $price, $shipping, $upsell_parent_id) = $statement->fetch();


$qty   = 1;
if($come_back == 1)
{
    // header("Location: http://www.boostlashenhancer.com/order.php");
    // exit();
}


function payPalAuthorizeCaptureContinue($refTransId, $payerID, $id, $token, $pid, $price, $qty, $shipping, $upsell_parent_id)
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
                //$address = payPalGetDetails($refTransId);
                // print_r($address);

                $sql = 'INSERT INTO upsells ( parent_id, package_id, price, added_date ) value(:parent_id, :package_id, :price, :added_date)';
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':parent_id'   => $upsell_parent_id,
                    ':package_id'  => $pid,
                    ':price'       => ($price/100),
                    ':added_date'  => date('Y-m-d H:i:s'),
                ));
                $upsell_id = $GLOBALS['db']->lastInsertId();

                $sql = "UPDATE upsells SET  status=:status, response_code=:response_code, auth_code=:auth_code, card_message=:card_message, trans_id=:trans_id WHERE id=:id ";
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':status'        => 'succeeded',
                    ':response_code' => 'paypal',
                    ':auth_code'     => $payerID,
                    ':card_message'  => $tresponse->getMessages()[0]->getDescription(),
                    ':trans_id'      => $token,
                    ':id'            => $upsell_id
                ));


                $statement = $GLOBALS['db']->prepare("select fname, lname, country, address1, address2,city, state, zipcode, package_id, email, shipping_id, price, added_date, confirmation_email, customer_id, payment_id, status from orders where id=:id   ");
                $statement->execute(array( ':id' => $upsell_parent_id));
                $statement->execute();
                list( $fname, $lname, $country, $address1, $address2, $city, $state, $zipcode, $package_id, $email,  $sid , $total, $added_date, $confirmation_email, $customer_id, $payment_id, $status ) = $statement->fetch();

                $ship_address =  $fname.' '.$lname. '<br>'.
                    $address1.'<br>'.
                    ($address2 ? $address2.'<br>' : '').
                    $city.'<br>'.
                    $zipcode.' '.$state.'<br>'.
                    $country;
                $packages           = $GLOBALS['packages'];
                $emaildate          = date('m/d/Y' , strtotime($added_date));

                $account='info.cutiebeauti@gmail.com';
                $password='333victory888!';
                $from='support@cutiebeauti.com';
                $from_name="Cutie Beauti";
                $mail = new PHPMailer();
                $mail->IsSMTP();
                $mail->CharSet = 'UTF-8';
                $mail->Host = "smtp.gmail.com";
                $mail->SMTPAuth= true;
                $mail->Port =  465;
                $mail->Username= $account;
                $mail->Password= $password;
                $mail->SMTPSecure = 'ssl';
                // $mail->SMTPDebug = 1;
                $mail->From = $from;
                $mail->FromName= $from_name;
                $mail->isHTML(true);

                $item ='<tr class="item"><td> 1 '.$packages[$pid]['name'].' </td><td>$'.number_format($packages[$pid]['total'] , 2 ).'</td></tr>';
                $text = file_get_contents('inc/email-template.html');
                $text = str_replace('{item}', $item,  $text);
                $text = str_replace('{address}', $ship_address,  $text);

                $text = str_replace('{date}', $emaildate,  $text);
                $text = str_replace('{tax}', '$0.00',  $text);
                $text = str_replace('{shipping}', '$'.$shipping,  $text);
                $text = str_replace('{total}', '$'.number_format(  ($price/100)  , 2 ),  $text);
                $text = str_replace('{number}', $upsell_parent_id,  $text);

                $mail->addAddress($email);
                $mail->AddCC('info.cutiebeauti@gmail.com');
                $mail->Subject = $packages[$pid]['name'].' Purchase Receipt';
                $mail->Body = $text;
                $mail->send();

//                 $botToken="527434317:AAFJ5lg0UyGa_YrenPcug_4S23_cmAO3OAw";
//                 $website="https://api.telegram.org/bot".$botToken;
//                 $chatId='@yashachannel';  //** ===>>>NOTE: this chatId MUST be the chat_id of a person, NOT another bot chatId !!!**
//                 $params=[
//                     'chat_id'=>$chatId,
//                     'text'=>"<b>\xF0\x9F\x98\x8D\xF0\x9F\x98\x8D Yeaaah Forokhtim \xF0\x9F\x98\x81\xF0\x9F\x98\x8B </b>
// Product : Upsell Cutiebeauti
// Buyer : ".$fname."
// Price : $".number_format(  ($price/100)  , 2 )."
// Email : ".$email."
// Country: ".$country."
// Domain: Boostlash.com P",

//                     'parse_mode'=>'HTML'
//                 ];
                $ch = curl_init($website . '/sendMessage');
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $result = curl_exec($ch);
                //  echo $result;
                curl_close($ch);





                header("Location: ".ROOTS."/thankyou.php?s=1&order_id=".$upsell_parent_id."&method=p");
                exit();

            }
            else
            {

                header("Location: ".ROOTS."/thankyou.php?s=1&order_id=".$upsell_parent_id."&method=p&paypal=fail&code=".base64_encode($tresponse->getErrors()[0]->getErrorCode())."&msg=".base64_encode($tresponse->getErrors()[0]->getErrorText()) );
                exit();

            }
        }
        else
        {
            $tresponse = $response->getTransactionResponse();
            if($tresponse != null && $tresponse->getErrors() != null)
            {
                header("Location: ".ROOTS."/thankyou.php?s=1&order_id=".$upsell_parent_id."&method=p&paypal=fail&code=".base64_encode($tresponse->getErrors()[0]->getErrorCode())."&msg=".base64_encode($tresponse->getErrors()[0]->getErrorText()) );
                exit();
            }
            else
            {
                header("Location: ".ROOTS."/thankyou.php?s=1&order_id=".$upsell_parent_id."&method=p&paypal=fail&code=".base64_encode($tresponse->getErrors()[0]->getErrorCode())."&msg=".base64_encode($tresponse->getErrors()[0]->getErrorText()) );
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
    payPalAuthorizeCaptureContinue( $transaction_id ,  $_GET['PayerID'], $id,  $_GET['token'], $package_id, $price, $qty, $shipping, $upsell_parent_id ) ;

    $sql = "UPDATE paypal SET come_back='1' WHERE id=:id ";
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':id'               => $id,));

}


?>
