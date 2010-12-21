<?php
/**
 * Buyer 
 *
 * @author Pavel Kovalyov
 */
require_once dirname(realpath(__FILE__)).'/models/BuyerModel.php';

class Buyer extends RCMS_Object_User_User {
    private $_buyerModel = null;

    private $_buyerId = false;
    private $_billingAddress = array();
    private $_shippingAddress = array();

    public function __construct($userId = false){
        $this->_buyerModel = new BuyerModel();
        parent::__construct($userId);

        if ($userId){
            $buyerData = $this->_buyerModel->selectBuyer($userId);
            if ($buyerData) {
                $this->_buyerId         = $buyerData['id'];
                $this->_billingAddress  = unserialize($buyerData['billing_address']);
                $this->_shippingAddress = unserialize($buyerData['shipping_address']);
            } 
        }
    }

    public function setBillingAddress($value) {
        $this->_billingAddress = $value;
    }

    public function getBillingAddress() {
        return $this->_billingAddress;
    }

    public function setShippingAddress($value) {
        $this->_shippingAddress = $value;
    }

    public function getShippingAddress() {
        return $this->_shippingAddress;
    }

    public function getBuyerId() {
        return $this->_buyerId;
    }

    public function save(){
        parent::save();

        if ($this->_buyerId) {
            return $this->_buyerModel->updateBuyer($this->_buyerId, $this->getBillingAddress(), $this->getShippingAddress());
        } else {
            return $this->_buyerModel->insertBuyer($this->getId(), $this->_billingAddress, $this->_shippingAddress);
        }
        return false;
    }

    public function savePayment($payment) {
        if (!empty($payment['type']) && !empty ($payment['id'])){
            return $this->_buyerModel->insertPayment($this->getId(), $payment);
        }
        return false;
    }

	public function delete(){
		if ( parent::delete() ) {
			return $this->_buyerModel->deleteBuyer($this->getId());
		} else {
			return false;
		}
	}

}