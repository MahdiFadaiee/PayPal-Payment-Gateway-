<?php include_once("config.php");
require 'lib/authorize-api/vendor/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
define("AUTHORIZENET_LOG_FILE", "phplog");
define("AUTHORIZENET_LOG_FILE", "phplog");


$upsell_package = $_GET['upsell_package'];
$refund         = $_GET['refund'];
$parent         = $_GET['parent'];

if(!array_key_exists($upsell_package, $packages)){

    header("Location: ".ROOT."/index.php");
    exit();
}
if(!isset($_GET['parent']))
{
    header("Location: ".ROOT."/index.php");
    exit();
}





$pid                  = $upsell_package;
//$shipping_price       = ($refund == 1 ? 0: 0.00);
$shipping_price       = ($refund == 1 ? -5.95: 0.00);
$total                = $packages[$upsell_package]['total']+ $shipping_price;
$quantity             = $packages[$upsell_package]['qty'];

$sql = 'INSERT INTO paypal (
                 package_id, price, shipping, quantity, added_date, upsell_parent_id
            )value(
                 :package_id, :price, :shipping, :quantity,  NOW(), :upsell_parent_id
            )';

$statement = $db->prepare($sql);
$statement->execute(array(':package_id'           => $pid,
    ':price'                => ($total*100),
    ':shipping'             => $shipping_price,
    ':quantity'             => $quantity,
    ':upsell_parent_id'     => $parent
));
$insertedId = $db->lastInsertId();


function payPalAuthorizeCapture($amount, $id, $pid, $quantity, $parent )
{
    /* Create a merchantAuthenticationType object with authentication details
       retrieved from the constants file */
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName("2d9V7SES2kXz");
    $merchantAuthentication->setTransactionKey("838Mqc4TtZDw2B3D");

    // Set the transaction's refId
    $refId = 'ref' . time();



    $payPalType=new AnetAPI\PayPalType();
    $payPalType->setCancelUrl( ROOTS."/special-offer.php?s=1&order_id=".$parent."&method=p&cancel=1");
    $payPalType->setSuccessUrl(ROOTS."/paypal_offer_success.php?id=".$id);
    // $payPalType->setPayerID(rand(1,999999));
    $payPalType->setPaypalPayflowcolor('f2e9ee');

    //$shipTo = new AnetAPI\ShipToType();

    $order = new AnetAPI\OrderType();
    $order->setInvoiceNumber(rand(1,99999999));
    $order->setDescription("Cutie Beauti Enhancer");

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


payPalAuthorizeCapture($total , $insertedId, $pid , $quantity, $parent);
?>
