<?php include_once 'header.php';


set_time_limit ( 0 );
if(!isset( $_GET['order_id'] ) || $_GET['order_id'] <= 0)
{
    header("Location: http://www.cutiebeauti.com/index.php");
    exit();
}

require 'lib/authorize-api/vendor/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
define("AUTHORIZENET_LOG_FILE", "phplog");

$id = $_GET['order_id'];



$upsell_added = 0;



$statement = $GLOBALS['db']->prepare("select fname, lname, country, address1, address2,city, state, zipcode, package_id, email, shipping_id, price, added_date, confirmation_email, customer_id, payment_id, status, tax from orders where id=:id   ");
$statement->execute(array( ':id' => $id));
$statement->execute();
list( $fname, $lname, $country, $address1, $address2, $city, $state, $zipcode, $package_id, $email,  $sid , $total, $added_date, $confirmation_email, $customer_id, $payment_id, $status, $tax ) = $statement->fetch();

$ship_address =  $fname.' '.$lname. '<br>'.
    $address1.'<br>'.
    ($address2 ? $address2.'<br>' : '').
    $city.'<br>'.
    $zipcode.' '.$state.'<br>'.
    $country;

$emaildate   = date('m/d/Y' , strtotime($added_date));

$bought_product = array();
$sql = "SELECT pid from basket where id in ($package_id) ";
$statement = $db->prepare($sql);
$statement->execute();
while( $row = $statement->fetch() )
{
    array_push($bought_product, $row['pid']);
}





// watch($bought_product);
// die();

if(in_array('20', $bought_product) || in_array('26', $bought_product) )
{
    header("Location: /thankyou.php?s=1&order_id=".$id.'&method='.$_GET['method']);
}


if(isset($_POST['buyupsell']) && $_POST['buyupsell'] == 1)
{
    $charge         = null;
    $upsell_package = $_POST['upsell_package'];
    $refund         = $_POST['refund'];


    $sql = "SELECT * From upsells WHERE parent_id='$id' and package_id='$upsell_package' ";
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute();

    if( $statement->rowCount() == 0 )
    {
        $charge = chargeCustomerProfile($customer_id , $payment_id, $id , $upsell_package, $refund);
    }

    if(is_array( $charge ))
    {
        $decline     = true;
        $decline_msg = $charge['msg'];
    }
    else
    {
        $upsell_added = 1;
        $item ='<tr class="item"><td> 1 '.$packages[$upsell_package]['name'].' </td><td>$'.number_format($packages[$upsell_package]['total'] , 2 ).'</td></tr>';
        $text = file_get_contents('inc/email-template.html');
        $text = str_replace('{item}', $item,  $text);
        $text = str_replace('{address}', $ship_address,  $text);

        $text = str_replace('{date}', $emaildate,  $text);
        $text = str_replace('{tax}', '0.00',  $text);
        $text = str_replace('{shipping}', '$'.($refund ==1  ? '-5.95' : '0.00' ),  $text);
        //if refund==1 shipping -5.95 $text = str_replace('{shipping}', '$'.($refund ==1  ? '-5.95' : '0.00' ),  $text);
        $text = str_replace('{total}', '$'.($refund ==1 ? number_format( ($packages[$upsell_package]['total']-5.95)  , 2 )   : number_format( $packages[$upsell_package]['total']  , 2 )),  $text);
        // ($packages[$upsell_package]['total']-5.95)
        $text = str_replace('{number}', $id,  $text);

        //$mail->addAddress($email);
        // $mail->AddCC('info.boostlash@gmail.com');
        $mail->Subject = $packages[$upsell_package]['name'].' Purchase Receipt';
        //sendEmailUsingSendinBlue($email, $packages[$upsell_package]['name'].' Purchase Receipt', $text);
        sendEmailUsingGmail($email, $packages[$upsell_package]['name'].' Purchase Receipt', $text);

        $botToken="527434317:AAFJ5lg0UyGa_YrenPcug_4S23_cmAO3OAw";
        $website="https://api.telegram.org/bot".$botToken;
        $chatId='@yashachannel';  //** ===>>>NOTE: this chatId MUST be the chat_id of a person, NOT another bot chatId !!!**
        $params=[
            'chat_id'=>$chatId,
            'text'=>"<b>\xF0\x9F\x98\x8D\xF0\x9F\x98\x8D Yeaaah Forokhtim \xF0\x9F\x98\x81\xF0\x9F\x98\x8B </b>
Product : Upsell Cutiebeauti
Buyer : ".$fname."
Price : $".($refund ==1 ? number_format( ($packages[$upsell_package]['total']-5.95)  , 2 )   : number_format( $packages[$upsell_package]['total']  , 2 ))."
Email : ".$email."
Country: ".$country."
Domain: Cutiebeauti.com",
            'parse_mode'=>'HTML'
        ];
        $ch = curl_init($website . '/sendMessage');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        //  echo $result;
        curl_close($ch);

        //header("Location: /thankyou.php?s=1&order_id=".$id.'&method=c&upsell=1');
    }
}

function chargeCustomerProfile($profileid, $paymentprofileid, $parent_id, $upsell_package, $refund)
{
    $added_date        = date('Y-m-d H:i:s');
    $packages          = $GLOBALS['packages'];
    $amount            = $packages[$upsell_package]['total'];
    $package_name      = $packages[$upsell_package]['name'];

    if($refund == 1)
    {
        $amount = $amount - 5.95;
        // if we do not offer free shipping on main product then reduce the charge by shipping price - 5.95
    }


    // $GLOBALS['db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = 'INSERT INTO upsells ( parent_id, package_id, price, added_date ) value(:parent_id, :package_id, :price, :added_date)';
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':parent_id'   => $parent_id,
        ':package_id'  => $upsell_package,
        ':price'       => $amount,
        ':added_date'  => $added_date,
    ));
    // print_r($GLOBALS['db']->errorInfo());
    // die();

    $upsell_id  = $GLOBALS['db']->lastInsertId();
    $invoice_id = $upsell_id+ 9000000;
    /* Create a merchantAuthenticationType object with authentication details
       retrieved from the constants file */
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName($GLOBALS['authorizeName']);
    $merchantAuthentication->setTransactionKey($GLOBALS['authorizeKey']);

    // Set the transaction's refId
    $refId = 'ref' . time();
    $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
    $profileToCharge->setCustomerProfileId($profileid);

    $order = new AnetAPI\OrderType();
    $order->setInvoiceNumber($invoice_id);
    $order->setDescription($package_name);

    $paymentProfile = new AnetAPI\PaymentProfileType();
    $paymentProfile->setPaymentProfileId($paymentprofileid);
    $profileToCharge->setPaymentProfile($paymentProfile);

    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType( "authCaptureTransaction");
    $transactionRequestType->setAmount($amount);
    $transactionRequestType->setOrder($order);
    $transactionRequestType->setProfile($profileToCharge);
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId( $refId);
    $request->setTransactionRequest( $transactionRequestType);
    $controller = new AnetController\CreateTransactionController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);


    $sql = 'INSERT INTO rawdata ( customer, charge, order_id ) value(:customer, :charge, :order_id)';
    $statement = $GLOBALS['db']->prepare($sql);
    $statement->execute(array(':customer'  => 'upsell',
        ':charge'    => serialize($response),
        ':order_id'  => $upsell_id
    ));


    // print_r($GLOBALS['db']->errorInfo());
//    print '<pre>';
//    print_r($response);
//    print '<pre>';
    if ($response != null)
    {
        if($response->getMessages()->getResultCode() =='Ok')
        {
            $tresponse = $response->getTransactionResponse();
            if ($tresponse != null && $tresponse->getMessages() != null)
            {
                //   echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                //   echo  "Charge Customer Profile APPROVED  :" . "\n";
                //   echo " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
                //   echo " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
                //   echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                //   echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";

                $sql = "UPDATE upsells SET  status=:status, response_code=:response_code, auth_code=:auth_code, card_message=:card_message, trans_id=:trans_id WHERE id=:id ";
                $statement = $GLOBALS['db']->prepare($sql);
                $statement->execute(array(':status'        => 'succeeded',
                    ':response_code' => $tresponse->getResponseCode(),
                    ':auth_code'     => $tresponse->getAuthCode(),
                    ':card_message'  => $tresponse->getMessages()[0]->getDescription(),
                    ':trans_id'      => $tresponse->getTransId(),
                    ':id'            => $upsell_id
                ));
                //print_r($GLOBALS['db']->errorInfo());

                return 'succeeded';
            }
            else
            {
                // echo "Transaction Failed 1 \n";
                if($tresponse->getErrors() != null)
                {
                    return array('status'=> 'Failed',
                        'msg'   => $tresponse->getErrors()[0]->getErrorCode().' '.$tresponse->getErrors()[0]->getErrorText()
                    );
                }
            }
        }
        else
        {
            //echo "Transaction Failed 2 \n";
            $tresponse = $response->getTransactionResponse();
            if($tresponse != null && $tresponse->getErrors() != null)
            {
                return array('status'=> 'Failed',
                    'msg'   => $tresponse->getErrors()[0]->getErrorCode().' '.$tresponse->getErrors()[0]->getErrorText()
                );
            }
            else
            {
                // echo "Transaction Failed 3 \n";
                return array('status'=> 'Failed',
                    'msg'   => $response->getMessages()->getMessage()[0]->getCode().' '.$response->getMessages()->getMessage()[0]->getText()
                );
            }
        }
    }
    else
    {
        echo "Transaction Failed 4 \n";
        return array('status'=> 'Failed',
            'msg'   => 'no response'
        );
    }
}








if( ($confirmation_email == 0  || (isset($_GET['resend']) && $_GET['resend']==1 ) ) && $status == 'succeeded' )
{

    $item = '';
    $statement = $db->prepare("select pid,quantity from basket where id in (".$package_id.")  ");
    $statement->execute();
    while( $row = $statement->fetch() )
    {
        $item .='<tr class="item"><td>'.$row['quantity'].' - '.$packages[$row['pid']]['qty'].' '.$packages[$row['pid']]['name'].'  </td><td>$'.number_format( ($packages[$row['pid']]['price']*$row['quantity'] ) , 2 ).'</td></tr>';
    }



    $text = file_get_contents('inc/email-template.html');
    $text = str_replace('{item}', $item,  $text);
    $text = str_replace('{address}', $ship_address,  $text);

    $text = str_replace('{date}', $emaildate,  $text);
    $text = str_replace('{tax}', '$'.number_format( $tax  , 2 ),  $text);
    $text = str_replace('{shipping}', '$'.number_format( $sid  , 2 ),  $text);
    $text = str_replace('{total}', '$'.number_format( ($total/100)+$tax  , 2 ),  $text);
    $text = str_replace('{number}', $id,  $text);

    //sendEmailUsingSendinBlue($email, "Purchase Receipt", $text);
    sendEmailUsingGmail($email, "Purchase Receipt", $text);



    $sql = 'UPDATE orders SET confirmation_email=:confirmation_email where id=:id ';
    $statement = $db->prepare($sql);
    $statement->execute(array(':confirmation_email'    => '1',
        ':id'                    => $id
    ));

    $today = date('Y-m-d', strtotime('now'));
    $sql="SELECT sum(price)/100 as total, count(id) FROM orders WHERE added_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH) and CAST(added_date AS DATE)='$today' and status='succeeded'  ";
    $statement = $db->prepare($sql);
    $statement->execute();
    list($total_sum, $total_count) =  $statement->fetch();

//     $botToken="527434317:AAFJ5lg0UyGa_YrenPcug_4S23_cmAO3OAw";
//     $website="https://api.telegram.org/bot".$botToken;
//     $chatId='@yashachannel';  //** ===>>>NOTE: this chatId MUST be the chat_id of a person, NOT another bot chatId !!!**
//     $params=[
//         'chat_id'=>$chatId,
//         'text'=>"<b>\xF0\x9F\x98\x8D\xF0\x9F\x98\x8D Yeaaah Forokhtim \xF0\x9F\x98\x81\xF0\x9F\x98\x8B </b>
// Product : Boostlash
// Buyer : ".$fname."
// Price : $".(($total/100)+$tax )."
// Revenu: $".$total_sum."
// Email : ".$email."
// Country: ".$country."
// Domain: Boostlash.com",

//         'parse_mode'=>'HTML'
//     ];
    $ch = curl_init($website . '/sendMessage');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    //  echo $result;
    curl_close($ch);



}


$statement = $GLOBALS['db']->prepare("select package_id from upsells where parent_id=:id ");
$statement->execute(array(':id' => $id));
$statement->execute();
while( $row = $statement->fetch() )
{
    array_push($bought_product, $row['package_id']);
}
$upsell_exist = 0;
$refund = 0;

?>
<style>
    .sale-text{
        color: red;
        border:1px solid red;
        float: right;
    }
</style>
<script>
    fbq('track', 'Purchase', {
        value: <?php echo (($total/100)+$tax ); ?>,
        currency: 'USD',
    });
</script>

<div class="container" style="
      margin-top: 100px;
      margin-bottom: 100px;
    ">


    <?php
    if(isset($_GET['paypal']) && $_GET['paypal']=='fail')
    {
        echo '<h3 style="color:red">'.base64_decode($_GET['msg']).' Please try Again</h3>';
    }

    ?>
    <?php if($decline_msg!='') echo '<h2 class="text-center" style="color: red;"> DECLINE ! '.$decline_msg.' Try Again</h2>'; ?>
    <?php if($upsell_added ==1)  echo '<h2 class="text-center" style="color: green;"> Success ! The Product has been added to your order !</h2>'; ?>
    <div class="col-xs-12" style="border: 40px solid #6cccc1; padding: 0 0 20px 0;">
        <div class="text-center">
            <h3>Thanks for Purchasing CutieBeauti!</h3>
            <?php if($bought_product[0] == '1' && count($bought_product) ==1){ $refund=1;  ?>
                <h4><strong>Add any of these products  to your order and qualify for <b style="color: #6cccc1;">FREE SHIPPING</b>. The initial $5.95 shipping charge will be deducted from the total order price</strong></h4>
            <?php } ?>
            <div style="background-color: #f7d9dd; padding: 4px; margin-bottom: 20px;">
                <h3 style="color: #b11c72;">You can <span style="text-decoration: underline;"><strong>AMPLIFY YOUR RESULTS</strong></span> and take advantage of this <span style="text-decoration: underline;"><strong>ONE TIME OFFER</strong></span> by adding the product(s) to your order.</h3>
            </div>
        </div>
        <?php if(!in_array('1', $bought_product) && !in_array('8', $bought_product) && !in_array('9', $bought_product) && !in_array('21', $bought_product) && !in_array('29', $bought_product) && !in_array('30', $bought_product) ){
            $upsell_exist = 1;
            ?>
            <div class="col-sm-6 col-xs-12">
                <div class="text-center" style="padding: 0 0 10px 0;">
                    <!-- <p class="sale-text">SALE</p> -->
                    <img src="images/BL-specialOffer.jpg" width="75%">
                    <div class="col-xs-12 text-center" style="font-size: 18px;">
                        <h3 class="text-center">CutieBeauti</h3>
                        <p>Eyelash Growth Serum</p>
                    </div>
                    <div class="text-center" style="font-size: 23px;">
                        <h3><strike style="color: red;"><span style="color: #000;">$59.95</span></strike><strong style="color: red;"> $45.95 USD</strong></h3>
                        <?php if($_GET['method'] == 'c'){ ?>
                            <form action="" method="post">

                                <button type="submit" class="addToCart btn btn-lg">ADD TO ORDER</button>
                                <input type="hidden" name="upsell_package" value="21" />
                                <input type="hidden" name="buyupsell" value="1" />
                                <input type="hidden" name="refund" value="<?php echo $refund; ?>" />
                            </form>
                        <?php }?>

                        <?php if($_GET['method']== 'p'){ ?>
                            <a id="paypalBtn" href="paypal_offer_request.php?upsell_package=21&refund=<?php echo $refund; ?>&parent=<?php echo $id; ?>" class="btn btn-lg"><img style="max-width: 70%;" src="images/paypal-button.jpg"></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if(!in_array('12', $bought_product) && !in_array('22', $bought_product) && !in_array('27', $bought_product) ){
            $upsell_exist = 1;
            ?>
            <div class="col-sm-6 col-xs-12">
                <div class="text-center" style="padding: 0 0 10px 0;">
                    <img src="images/BB-specialOffer.jpg" width="75%">
                    <div class="col-xs-12 text-center" style="font-size: 18px;">
                        <h3 class="text-center">DenseBROW™</h3>
                        <p>Eyebrow Growth Serum - </p>
                    </div>
                    <div class="text-center" style="font-size: 23px;">
                        <h3><strike style="color: red;"><span style="color: #000;">$49.95</span></strike><strong style="color: red;"> $34.95 USD</strong></h3>
                        <?php if($_GET['method'] == 'c'){ ?>
                            <form action="" method="post">
                                <button disabled type="submit" class="addToCart btn btn-lg">OUT OF STOCK</button>
                                <input type="hidden" name="upsell_package" value="22" />
                                <input type="hidden" name="buyupsell" value="1" />
                                <input type="hidden" name="refund" value="<?php echo $refund; ?>" />
                            </form>
                        <?php }?>

                        <?php if($_GET['method']== 'p'){ ?>
                            <!--  <a id="paypalBtn" href="paypal_offer_request.php?upsell_package=22&refund=<?php echo $refund; ?>&parent=<?php echo $id; ?>" class="btn btn-lg"><img style="max-width: 70%;" src="images/paypal-button.jpg"></a>-->
                            <p>OUT OF STOCK</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        <?php if(!in_array('13', $bought_product) && !in_array('23', $bought_product) && !in_array('28', $bought_product)  ){
            $upsell_exist = 1;
            ?>
            <div class="col-sm-6 col-xs-12" >
                <div class="text-center" style="padding: 0 0 10px 0;">
                    <img src="images/BV-specialOffer.jpg" width="75%">
                    <div class="col-xs-12 text-center" style="font-size: 18px;">
                        <h3 class="text-center">BoostVOLUME</h3>
                        <!-- <p style="color: red;font-size: 65%;margin-top: -10px;margin-bottom: 3px;">On Back Order, will be back in stock on May 31st</p> -->
                        <p>Eyelash Volumizing Serum</p>
                    </div>
                    <div class="text-center" style="font-size: 23px;">
                        <h3><strike style="color: red;"><span style="color: #000;">$39.95</span></strike><strong style="color: red;"> $34.95 USD</strong></h3>
                        <?php if($_GET['method'] == 'c'){ ?>
                            <!-- <a  class=" btn btn-lg" style="background-color: #807e7e; color:white; cursor: default;">OUT OF STOCK</a> -->
                            <form action="" method="post">
                                <button type="submit" class="addToCart btn btn-lg">ADD TO ORDER</button>
                                <input type="hidden" name="upsell_package" value="23" />
                                <input type="hidden" name="buyupsell" value="1" />
                                <input type="hidden" name="refund" value="<?php echo $refund; ?>" />
                            </form>
                        <?php }?>

                        <?php if($_GET['method']== 'p'){ ?>
                            <!-- <a  class=" btn btn-lg" style="background-color: #807e7e; color:white; cursor: default;">OUT OF STOCK</a> -->
                            <a id="paypalBtn" href="paypal_offer_request.php?upsell_package=23&refund=<?php echo $refund; ?>&parent=<?php echo $id; ?>" class="btn btn-lg"><img style="max-width: 70%;" src="images/paypal-button.jpg"></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>



        <?php if(!in_array('44', $bought_product) && !in_array('41', $bought_product)  ){
            $upsell_exist = 1;
            ?>
            <div class="col-sm-6 col-xs-12" >
                <div class="text-center" style="padding: 0 0 10px 0;">
                    <img src="images/HB-specialOffer.jpg" width="75%">
                    <div class="col-xs-12 text-center" style="font-size: 18px;">
                        <h3 class="text-center">HAIRBOOST</h3>
                        <!-- <p style="color: red;font-size: 65%;margin-top: -10px;margin-bottom: 3px;">On Back Order, will be back in stock on May 31st</p> -->
                        <p>All Natural - Hair Boosting Oil</p>
                    </div>
                    <div class="text-center" style="font-size: 23px;">
                        <h3><strike style="color: red;"><span style="color: #000;">$39.95</span></strike><strong style="color: red;"> $29.95 USD</strong></h3>
                        <?php if($_GET['method'] == 'c'){ ?>
                            <!-- <a  class=" btn btn-lg" style="background-color: #807e7e; color:white; cursor: default;">OUT OF STOCK</a> -->
                            <form action="" method="post">
                                <button type="submit" class="addToCart btn btn-lg">ADD TO ORDER</button>
                                <input type="hidden" name="upsell_package" value="44" />
                                <input type="hidden" name="buyupsell" value="1" />
                                <input type="hidden" name="refund" value="<?php echo $refund; ?>" />
                            </form>
                        <?php }?>

                        <?php if($_GET['method']== 'p'){ ?>
                            <!--  <a  class=" btn btn-lg" style="background-color: #807e7e; color:white; cursor: default;">OUT OF STOCK</a> -->
                            <a id="paypalBtn" href="paypal_offer_request.php?upsell_package=44&refund=<?php echo $refund; ?>&parent=<?php echo $id; ?>" class="btn btn-lg"><img style="max-width: 70%;" src="images/paypal-button.jpg"></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>



        <?php if(!in_array('45', $bought_product) && !in_array('42', $bought_product)  ){
            $upsell_exist = 1;
            ?>
            <div class="col-sm-6 col-xs-12" >
                <div class="text-center" style="padding: 0 0 10px 0;">
                    <img src="images/MR-specialOffer.jpg" width="75%">
                    <div class="col-xs-12 text-center" style="font-size: 18px;">
                        <h3 class="text-center">MAKEUP REMOVER</h3>
                        <!-- <p style="color: red;font-size: 65%;margin-top: -10px;margin-bottom: 3px;">On Back Order, will be back in stock on May 31st</p> -->
                        <p>CutieBeauti® Eye Makeup Remover</p>
                    </div>
                    <div class="text-center" style="font-size: 23px;">
                        <h3><strike style="color: red;"><span style="color: #000;">$29.95</span></strike><strong style="color: red;"> $24.95 USD</strong></h3>
                        <?php if($_GET['method'] == 'c'){ ?>
                            <!-- <a  class=" btn btn-lg" style="background-color: #807e7e; color:white; cursor: default;">OUT OF STOCK</a> -->
                            <form action="" method="post">
                                <button type="submit" class="addToCart btn btn-lg">ADD TO ORDER</button>
                                <input type="hidden" name="upsell_package" value="45" />
                                <input type="hidden" name="buyupsell" value="1" />
                                <input type="hidden" name="refund" value="<?php echo $refund; ?>" />
                            </form>
                        <?php }?>

                        <?php if($_GET['method']== 'p'){ ?>
                            <!--  <a  class=" btn btn-lg" style="background-color: #807e7e; color:white; cursor: default;">OUT OF STOCK</a> -->
                            <a id="paypalBtn" href="paypal_offer_request.php?upsell_package=45&refund=<?php echo $refund; ?>&parent=<?php echo $id; ?>" class="btn btn-lg"><img style="max-width: 70%;" src="images/paypal-button.jpg"></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>





        <?php
        if($upsell_exist == 0){
            header("Location: /thankyou.php?s=1&order_id=".$id.'&method='.$_GET['method']);
        }

        ?>

        <div class="col-xs-12 text-center">
            <a href="thankyou.php?s=1&order_id=<?php echo $_GET['order_id']; ?>&method=<?php echo $_GET['method']; ?>" id="nothanks" class="col-xs-12" style="
                                    text-decoration: underline;
                                    margin-top: 20px;
                                    font-size: 17px;
                                "><strong>No Thanks. I'm not into savings!</strong></a>
        </div>
    </div>

</div><!-- END CONTAINER-->





<?php include 'footer.php'; ?>


<script>

    $(document).ready(function(){

        mixpanel.track("Upsell Page");

        $(".addToCart").click(function(){
            mixpanel.track("Upsell added ");
        });
        $("#nothanks").click(function(){
            mixpanel.track("upsell decline");
        });
    });

</script>



<?php
if($confirmation_email == 0)
{
    $sscid = ! empty( $_COOKIE['shareasaleSSCID'] ) ? $_COOKIE['shareasaleSSCID'] : '';
    if(!empty($sscid))
    {
        echo '<img src="https://www.shareasale.com/sale.cfm?tracking='.$id.'&amount='.($total/100).'&merchantID=89938&transtype=sale&sscidmode=6&sscid='.$sscid.'" width="1" height="1">
<script defer async type="text/javascript" src="https://shareasale-analytics.com/j.js"></script>';
    }
    ?>






    <img src="https://www.clkmg.com/api/s/pixel/?uid=74481&att=2&amt=<?php echo round(($total/100)); ?>&ref=cutiebeauti-thankyou" height="1" width="1" />
    <script>

        $(document).ready(function(){
            mixpanel.track("Yeki Kharid :)");
        });

    </script>






    <!-- Event snippet for buy conversion page -->
    <script>
        gtag('event', 'conversion', {
            'send_to': 'AW-848876976/XgUCCMiKpXIQsKvjlAM',
            'value': <?php echo (($total/100)+$tax ); ?>,
            'currency': 'USD',
            'transaction_id': ''
        });
    </script>





<?php  }  ?>

</body>
</html>
