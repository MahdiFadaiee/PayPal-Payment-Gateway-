

<div id="paypal-button">
<!--    after click on div or btn you should call under code with your own variable [$total,$shipping_cost]-->
<?php
    $total=58.00;       //price your want pay
    $shipping_cost=5.00; //tax
    include_once 'generate_paypal_btn_auth.php';
    generate_paypal_button(
        'paypal3.php',
        'paypal2.php',
        'special-offer.php',
        'paypal-button',
        'COMPANY NAME TEST',
        $total,
        $shipping_cost);

?>
