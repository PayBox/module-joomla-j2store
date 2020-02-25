<?php
/*
 * --------------------------------------------------------------------------------
   Quantum Technologies Kazakhstan  - J2 Store v 3.0 - Payment Plugin - SagePay
 * --------------------------------------------------------------------------------
 * @package		Joomla! 3.5x
 * @subpackage	J2 Store
 * @author    	Quantum Technologies Kazakhstan https://www.paybox.money
 * @copyright	Copyright (c) 2020 Quantum Technologies Kazakhstan Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link		http://paybox.money
 * --------------------------------------------------------------------------------
*/

//no direct access
defined('_JEXEC') or die('Restricted access');



?>

<style type="text/css">
    #platron_form { width: 100%; }
    #platron_form td { padding: 5px; }
    #platron_form .field_name { font-weight: bold; }
</style>

<form action="<?php echo JRoute::_( "https://api.paybox.money/payment.php" ); ?>" method="post" name="adminForm" enctype="multipart/form-data">

	<?foreach($vars as $name => $value){?>
		<input type='hidden' name='<?echo $name?>' value='<?echo $value?>'>
    <?}?>

    <input type="submit" class="btn btn-primary button" value="<?php echo JText::_('J2STORE_SAGEPAY_CLICK_TO_COMPLETE_ORDER'); ?>" />
</form>
