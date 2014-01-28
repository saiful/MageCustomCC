<?php
class Acumen_Pay_Model_Pay extends Mage_Payment_Model_Method_Cc {
    protected $_code = 'pay';
    protected $_formBlockType = 'pay/form_pay';
    protected $_infoBlockType = 'pay/info_pay';
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = false;
    protected $_canSaveCc = false;
    
    public function process($data) {
        if ($data['cancel'] == 1) {
            $order->getPayment()->setTransactionId(null)->setParentTransactionId(time())->void();
            $message = 'Unable to process Payment';
            $order->registerCancellation($message)->save();
        }
    }
    
    /** For capture **/
    public function capture(Varien_Object $payment, $amount) {
        $order  = $payment->getOrder();

        $result = $this->callApi($payment, $amount, 'Sale');

        $errorMsg = false;
        if ($result === false) {
            $errorCode = 'Invalid Data';
            $errorMsg  = $this->_getHelper()->__('Error Processing the request');
        } 
        else {
            if ($result['status'] == 1) {
                $payment->setTransactionId($result['transaction_id']);
                $payment->setIsTransactionClosed(1);

                // Save additional variables as needed
                $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array(
                    'key1' => 'value1',
                    'key2' => 'value2'
                ));
            }
            else {
                Mage::throwException($errorMsg);
            }
        }

        if ($errorMsg) {
            Mage::throwException($errorMsg);
        }

        return $this;
    }
    
    
    /** For authorization **/
    public function authorize(Varien_Object $payment, $amount) {
        $order    = $payment->getOrder();
        $items    = $order->getAllVisibleItems();

        $result   = $this->callApi($payment, $amount, 'Authorization');
        $errorMsg = "";

        if ($result === false) {
            $errorCode = 'Invalid Data';
            $errorMsg  = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        } 
        else {            
            if ($result['status'] == 1) {
                $payment->setTransactionId($result['transaction_id']);
                $payment->setIsTransactionClosed(1);
                $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array(
                    'key1' => 'value1',
                    'key2' => 'value2'
                ));
                
                $order->addStatusToHistory($order->getStatus(), 'Payment Sucessfully Placed with Transaction ID' . $result['transaction_id'], false);
                $order->save();
            } 
            else {
                $errorMsg  = $this->_getHelper()->__('Your credit card information appears to be invalid');
                Mage::throwException($errorMsg);
            }
        }
        
        return $this;
    }
    
    public function processBeforeRefund($invoice, $payment)
    {
        return parent::processBeforeRefund($invoice, $payment);
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $order  = $payment->getOrder();
        $result = $this->callApi($payment, $amount, 'refund');
        if ($result === false) {
            $errorCode = 'Invalid Data';
            $errorMsg  = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        }

        return $this;    
    }

    public function processCreditmemo($creditmemo, $payment)
    {
        return parent::processCreditmemo($creditmemo, $payment);
    }
    
    /**
     * @param (type) This string can be used to tell the API
     *               if the call is for Sale or Authorization
     */
    private function callApi(Varien_Object $payment, $amount, $type = "")
    {
        if ($amount > 0) {
			$order            = $payment->getOrder();
		    $ccNumber         = $payment->getCcNumber();
			$expiratonMonth   = $payment->getCcExpMonth();
		    $expirationYear   = $payment->getCcExpYear();
		    $billingAddress   = $order->getBillingAddress();
		    $street           = $billingAddress->getStreet(1);
		    $postcode         = $billingAddress->getPostcode();
		    $cscCode          = $payment->getCcCid();
		    
            // This is where you would make your cURL call (or whatever)
            // to your payment processing provider, such as Paytrace.

            // If the transaction or authorization was successful,
            // return true. If not, return false.
        } else {
            $error = Mage::helper('paytrace')->__('Invalid amount for authorization.');
			return array( 'status' => 0, 'transaction_id' => time(), 'fraud' => 0 );
        }
    }
}
?>
