<?php include_once("../../config.php");
require '../../lib/authorize-api/vendor/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
define("AUTHORIZENET_LOG_FILE", "phplog");
define("AUTHORIZENET_LOG_FILE", "phplog");

$basket_ids = array();

$statement = $db->prepare("select id from basket where user_id=:id and deleted='0'  ");
$statement->execute(array( ':id' => $_SESSION['user']['id'] ));
$statement->execute();
while( $row = $statement->fetch() )
{
    array_push($basket_ids, $row['id']);
}
if(count($basket_ids) <= 0)
{
    header("Location: ".ROOT."/order.php?empty");
}


$statement = $GLOBALS['db']->prepare("select sum(price * quantity) as total_price from basket where user_id=:id and deleted='0'  ");
$statement->execute(array( ':id' => $_SESSION['user']['id'] ));
list($price) = $statement->fetch();

$pid                  = implode(',', $basket_ids);
$shipping_price       = ($price >70 ? 0.00: 5.95);
$total                = $price+$shipping_price;
$quantity             = 1;

$sql = 'INSERT INTO paypal (
                 package_id, price, shipping, quantity, added_date, basket_id
            )value(
                 :package_id, :price, :shipping, :quantity,  NOW(), :basket_id
            )';

$statement = $db->prepare($sql);
$statement->execute(array(':package_id'           => $pid,
    ':price'                => ($total*100),
    ':shipping'             => $shipping_price,
    ':quantity'             => $quantity,
    ':basket_id'            => $_SESSION['user']['id']
));
$insertedId = $db->lastInsertId();


function payPalAuthorizeCapture($amount, $id, $pid, $quantity )
{
    /* Create a merchantAuthenticationType object with authentication details
       retrieved from the constants file */
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName("2d9V7SES2kXz");
    $merchantAuthentication->setTransactionKey("838Mqc4TtZDw2B3D");

    // Set the transaction's refId
    $refId = 'ref' . time();



    $payPalType=new AnetAPI\PayPalType();
    $payPalType->setCancelUrl( ROOT."/cart.php?cancel=1");
    $payPalType->setSuccessUrl(ROOTS."/paypal_success.php?id=".$id);
    // $payPalType->setPayerID(rand(1,999999));
    $payPalType->setPaypalPayflowcolor('f2e9ee');

    //$shipTo = new AnetAPI\ShipToType();

    $order = new AnetAPI\OrderType();
    $order->setInvoiceNumber(rand(1,99999999));
    $order->setDescription("Cutiebeauti Enhancer Serum");

    //   $lineItem = new AnetAPI\LineItemType();
    //   $lineItem->setItemId("1".rand(1,99999));
    //   $lineItem->setName("Boostlash Enhancer Serum");
    //   $lineItem->setDescription("Boostlash Enhancer Serum 5 mil");
//    $lineItem->setQuantity($quantity);
    //   $lineItem->setUnitPrice(number_format( ($amount/ $quantity)  ,2));
    //   $lineItem_Array[] = $lineItem;


    // $invoice_id = 'p_'.rand(0, 99999);
    $paymentOne = new AnetAPI\PaymentType();
    //$paymentOne->setInvoiceNumber($invoice_id);
    $paymentOne->setPayPal($payPalType);

    // Create an authorize and capture transaction
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType( "authCaptureTransaction");
    $transactionRequestType->setOrder($order);
    $transactionRequestType->setPayment($paymentOne);
    $transactionRequestType->setAmount($amount);
    //  $transactionRequestType->setAddress($customershippingaddress);


    //$transactionRequestType->setLineItems($lineItem_Array);

    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($refId);
    $request->setTransactionRequest( $transactionRequestType);
    //  print '<pre>';
    // print_r( $request);
    //  print '</pre>';
    //  die();
    $controller = new AnetController\CreateTransactionController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

    if ($response != null)
    {
        if($response->getMessages()->getResultCode() =='Ok')
        {
            $tresponse = $response->getTransactionResponse();

            if ($tresponse != null && $tresponse->getMessages() != null)
            {

                $sql = "UPDATE paypal SET transaction_id=:transaction_id, response_code=:response_code, code=:code, description_txt=:description_txt WHERE id=:id ";
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':transaction_id'   => $tresponse->getTransId(),
                    ':response_code'    => $tresponse->getResponseCode(),
                    ':code'             => $tresponse->getMessages()[0]->getCode(),
                    ':description_txt'  => $tresponse->getMessages()[0]->getDescription(),
                    ':id'               => $id,
                ));

                header("Location: ".$tresponse->getSecureAcceptance()->getSecureAcceptanceUrl());
                exit();

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


payPalAuthorizeCapture($total , $insertedId, $pid , $quantity);
?>
