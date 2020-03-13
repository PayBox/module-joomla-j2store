<?php
/*------------------------------------------------------------------------
# com_j2store - J2Store
# ------------------------------------------------------------------------
# author    Galym Sarsebek - PayBox Money  https://www.paybox.money
# copyright Copyright (C) 2020 paybox.money All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://j2store.org
# Technical Support:  Forum - http://j2store.org/forum/index.html
-------------------------------------------------------------------------*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');
include 'PG_Signature.php';
require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');

class plgJ2StorePayment_paybox extends J2StorePaymentPlugin
{
    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    var $_element    = 'payment_paybox';

    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object $subject The object to observe
     * @param 	array  $config  An array that holds the plugin configuration
     * @since 2.5
     */
    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage( 'com_j2store', JPATH_ADMINISTRATOR );
    }


    /**
     * Prepares the payment form
     * and returns HTML Form to be displayed to the user
     * generally will have a message saying, 'confirm entries, then click complete order'
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _prePayment( $data )
    {
        F0FTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_j2store/tables');
        $order = F0FTable::getInstance ( 'Order', 'J2StoreTable' );

        $order->load( $data['orderpayment_id'] );
        $items = $order;
        $strDescription = '';
        foreach($items as $objItem){
            $strDescription .= $objItem->orderitem_name;
            if($objItem->orderitem_quantity > 1)
                $strDescription .= "*".$objItem->orderitem_quantity;
            $strDescription .= "; ";
        }
        $nLifeTime = $this->params->get("lifetime", '');

        $strCurrency = $order->currency_code;
        if($strCurrency == 'RUR')
            $strCurrency = 'RUB';

        $returnUrl = JURI::base() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=payment_paybox&Itemid=".$data['order_id']."&orderpayment_id=".$data['orderpayment_id'];
        $arrFields = array(
            'pg_merchant_id'		=> $this->params->get('merchant_id',''),
            'pg_order_id'			=> $data['order_id'],
            'pg_currency'			=> $strCurrency,
            'pg_amount'				=> sprintf('%0.2f',$data['orderpayment_amount']),
            'pg_lifetime'			=> isset($nLifeTime)?$nLifeTime*60:0,
            'pg_testing_mode'		=> $this->params->get("test_mode",''),
            'pg_description'		=> 'Покупка на ' . ' ' . $_SERVER['HTTP_HOST'],
            'pg_user_ip'			=> $_SERVER['REMOTE_ADDR'],
            'pg_language'			=> (JFactory::getLanguage()->getTag() == 'ru-RU')?'ru':'en',
            'pg_check_url'			=> $returnUrl,
            'pg_result_url'			=> $returnUrl,
            'pg_success_url'		=> $this->params->get("success_url",''),
            'pg_failure_url'		=> $this->params->get("failure_url",''),
            'pg_request_method'		=> 'GET',
            'pg_salt'				=> rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
        );

        if(!empty($data['orderinfo']['phone_1'])){
            preg_match_all("/\d/", $data['orderinfo']['phone_1'], $array);
            $strPhone = implode('',@$array[0]);
            $arrFields['pg_user_phone'] = $strPhone;
        }

        if(!empty($data['orderinfo']['phone_2'])){
            preg_match_all("/\d/", $data['orderinfo']['phone_2'], $array);
            $strPhone = implode('',@$array[0]);
            $arrFields['pg_user_phone'] = $strPhone;
        }

        if(!empty($data['orderinfo']['billing_phone_1'])){
            preg_match_all("/\d/", $data['orderinfo']['billing_phone_1'], $array);
            $strPhone = implode('',@$array[0]);
            $arrFields['pg_user_phone'] = $strPhone;
        }

        if(!empty($data['orderinfo']['billing_phone_2'])){
            preg_match_all("/\d/", $data['orderinfo']['billing_phone_2'], $array);
            $strPhone = implode('',@$array[0]);
            $arrFields['pg_user_phone'] = $strPhone;
        }

        if(!empty($data['orderinfo']['billing_email'])){
            $arrFields['pg_user_email'] = $data['orderinfo']['billing_email'];
            $arrFields['pg_user_contact_email'] = $data['orderinfo']['billing_email'];
        }

        if(!empty($data['orderinfo']['email'])){
            $arrFields['pg_user_email'] = $data['orderinfo']['email'];
            $arrFields['pg_user_contact_email'] = $data['orderinfo']['email'];
        }

        if(!empty($data['user_email'])){
            $arrFields['pg_user_email'] = $data['user_email'];
            $arrFields['pg_user_contact_email'] = $data['user_email'];
        }


        $arrFields['pg_sig'] = PG_Signature::make('payment.php', $arrFields, $this->params->get("secret_key",''));

        $html = $this->_getLayout('prepayment', $arrFields);
        return $html;
    }

    /**
     * Processes the payment form
     * and returns HTML to be displayed to the user
     * generally with a success/failed message
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _postPayment( $data )
    {
        if(!empty($_POST))
            $arrRequest = $_POST;
        else
            $arrRequest = $_GET;


        $order = F0FTable::getInstance ( 'Order', 'J2StoreTable' );
        $order->load( $arrRequest['orderpayment_id'] );

        $arrStatuses = array(
            'Confirmed' => 1,
            'Processed' => 2,
            'Failed' => 3,
            'Pending' => 4,
            'Incomplete' => 5,
        );

        $thisScriptName = PG_Signature::getOurScriptName();

        if (empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], $thisScriptName, $arrRequest, $this->params->get('secret_key','')))
            die("Wrong signature");

        if(!isset($arrRequest['pg_result'])){
            $bCheckResult = 0;
            if(empty($order) || !in_array( $order->transaction_status, array('Incomplete','Pending')))
                $error_desc = "Товар не доступен. Либо заказа нет, либо его статус " . $order->transaction_status;
            elseif(sprintf('%0.2f',$arrRequest['pg_amount']) != sprintf('%0.2f',$order->order_total))
                $error_desc = "Неверная сумма";
            else
                $bCheckResult = 1;

            $arrResponse['pg_salt']              = $arrRequest['pg_salt']; // в ответе необходимо указывать тот же pg_salt, что и в запросе
            $arrResponse['pg_status']            = $bCheckResult ? 'ok' : 'error';
            $arrResponse['pg_error_description'] = $bCheckResult ?  ""  : $error_desc;
            $arrResponse['pg_sig']				 = PG_Signature::make($thisScriptName, $arrResponse, $this->params->get('secret_key',''));

            $objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
            $objResponse->addChild('pg_salt', $arrResponse['pg_salt']);
            $objResponse->addChild('pg_status', $arrResponse['pg_status']);
            $objResponse->addChild('pg_error_description', $arrResponse['pg_error_description']);
            $objResponse->addChild('pg_sig', $arrResponse['pg_sig']);

        }
        else{
            $bResult = 0;
            if(empty($order) ||
                (!in_array( $order->transaction_status, array('Incomplete','Pending')) &&
                    !(!in_array( $order->transaction_status, array('Confirmed','Processed')) && $arrRequest['pg_result'] == 1) &&
                    ( $order->transaction_status != 'Failed' && $arrRequest['pg_result'] == 0)))

                $strResponseDescription = "Товар не доступен. Либо заказа нет, либо его статус " . $order->transaction_status;
            elseif(sprintf('%0.2f',$arrRequest['pg_amount']) != sprintf('%0.2f',$order->order_total))
                $strResponseDescription = "Неверная сумма";
            else {
                $bResult = 1;
                $strResponseStatus = 'ok';
                $strResponseDescription = "Оплата принята";
                if ($arrRequest['pg_result'] == 1) {
                    // Установим статус оплачен
                    $order->transaction_status = 'Confirmed';
                    $order->order_state_id = $arrStatuses['Confirmed'];
                    $order->order_state = 'J2STORE_CONFIRMED';
                    $order->empty_cart();
                    $order->payment_complete();
                    $order->store();
                }
                else{
                    // Неудачная оплата
                    $order->transaction_status = 'Failed';
                    $order->order_state_id = $arrStatuses['Failed'];
                    $order->order_state = 'J2STORE_FAILED';
                    $order->store();
                }
            }
            if(!$bResult)
                if($arrRequest['pg_can_reject'] == 1)
                    $strResponseStatus = 'rejected';
                else
                    $strResponseStatus = 'error';

            $objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
            $objResponse->addChild('pg_salt', $arrRequest['pg_salt']); // в ответе необходимо указывать тот же pg_salt, что и в запросе
            $objResponse->addChild('pg_status', $strResponseStatus);
            $objResponse->addChild('pg_description', $strResponseDescription);
            $objResponse->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $objResponse, $this->params->get('secret_key','')));
        }

        header("Content-type: text/xml");
        echo $objResponse->asXML();
        die();
    }

    /**
     * Prepares variables and
     * Renders the form for collecting payment info
     *
     * @return unknown_type
     */
    function _renderForm( $data )
    {
        $user = JFactory::getUser();
        $vars = new JObject();
        $vars->onselection_text = $this->params->get('onselection', '');
        $html = $this->_getLayout('form', $vars);
        return $html;
    }

    function getPaymentStatus($payment_status) {
        $status = '';
        switch($payment_status) {

            case 1:
                $status = JText::_('J2STORE_CONFIRMED');
                break;

            case 3:
                $status = JText::_('J2STORE_FAILED');
                break;

            default:
            case 4:
                $status = JText::_('J2STORE_PENDING');
                break;
        }
        return $status;
    }
}
