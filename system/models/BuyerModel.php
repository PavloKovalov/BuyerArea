<?php
/**
 * BuyerModel
 *
 * @author Pavel Kovalyov
 */
class BuyerModel extends Zend_Db_Table_Abstract {

    private $_userTable     = 'user';
    private $_userDataTable = 'buyerarea_userdata';
    private $_userHistoryTable = 'buyerarea_userhistory';
    
    public function insertBuyer($userId, $billingAddress, $shippingAddress){
        $data = array(
            'user_id'           => $userId,
            'billing_address'   => serialize($billingAddress),
            'shipping_address'  => serialize($shippingAddress)
        );

        return $this->getAdapter()->insert($this->_userDataTable, $data);
    }
    public function updateBuyer($buyerId, $billingAddress, $shippingAddress){
        $data = array(
            'billing_address'   => serialize($billingAddress),
            'shipping_address'  => serialize($shippingAddress)
        );
        $where = $this->getAdapter()->quoteInto('id = ?', $buyerId);
        return $this->getAdapter()->update($this->_userDataTable, $data, $where);
    }

    public function selectBuyer($userId){
        $sql = $this->getAdapter()->select()->from($this->_userDataTable)->where('user_id = ?', $userId);
        return $this->getAdapter()->fetchRow($sql, null, Zend_Db::FETCH_ASSOC);
    }

	public function deleteBuyer($id) {
		$where = $this->getAdapter()->quoteInto('user_id = ? ', $id);
		// cleaning buyer history
		$this->getAdapter()->delete($this->_userHistoryTable, $where);
		// removing buyer
		return $this->getAdapter()->delete($this->_userDataTable, $where);
	}

    public function insertPayment($userId, $payment){
        $data = array(
            'user_id'  => (int) $userId,
            'ref_type' => $payment['type'],
            'ref_id'   => (int) $payment['id'],
            'date'     => date('Y-m-d H:i:s')
        );
        return $this->getAdapter()->insert($this->_userHistoryTable, $data);
    }

}