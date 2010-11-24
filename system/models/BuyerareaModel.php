<?php
/**
 * BuyeareaModel
 *
 * @author Pavel Kovalyov
 */
class BuyerareaModel extends Zend_Db_Table_Abstract {
    private $_userTable         = 'user';
    private $_userDataTable     = 'buyerarea_userdata';
    private $_settingsTable     = 'buyerarea_settings';
    private $_userHistoryTable  = 'buyerarea_userhistory';
    private $_usercartTable     = 'shopping_usercart';
    private $_shippingTable     = 'shopping_shipping';
    private $_quoteTable        = 'shopping_quote';

	public function selectAllUserPayments($id) {
		$sql = $this->getAdapter()->select()->from($this->_userHistoryTable)->where('user_id = ?', $id);
		return $this->getAdapter()->fetchAssoc($sql);
	}

    public function selectSettings() {
        $sql = $this->getAdapter()->select()->from($this->_settingsTable);
        return $this->getAdapter()->fetchPairs($sql);
    }

    public function updateSettings($name, $value)
    {
        $sql = 'INSERT INTO '.$this->_settingsTable.' (name, value) VALUES (?,?)
        ON DUPLICATE KEY UPDATE name = ?, value = ?';
        $this->getAdapter()->query($sql, array($name, $value, $name, $value));
    }

    public function selectMailSettings() {
        $sql = $this->getAdapter()->select()->from(array('c'=>'config'))->where('c.name LIKE ?','%smtp%');
        return $this->getAdapter()->fetchPairs($sql);
    }
    public function selectShopConfig() {
        $sql = $this->getAdapter()->select()->from('shopping_config');
        return $this->getAdapter()->fetchPairs($sql);
    }

    public function selectAllBuyers() {
        $sql = $this->getAdapter()->select()->from(array('buyer'=>$this->_userDataTable),array('id'))
                ->joinLeft(array('user'=>$this->_userTable),'user.id = buyer.user_id',array('login','email','nickname','reg_date','role_id'))
                ->joinLeft(array('act'=>$this->_userHistoryTable),'act.user_id = buyer.user_id', array('action'=>'ref_type','action_id'=>'ref_id','action_date'=>'date'))->group('buyer.user_id');
        return $this->getAdapter()->fetchAssoc($sql);
    }

    public function selectUserDataByQuoteId($id) {
        $sql = $this->getAdapter()->select()->from(array('q'=>$this->_quoteTable),array())
                ->joinLeft(array('c'=>$this->_usercartTable), 'c.id = q.sc_id', array(
                    'name', 'email', 'company', 'address1', 'address2', 'country', 'state', 'city', 'zip', 'phone'
                ))
                ->joinLeft(array('s'=>$this->_shippingTable), 's.cart_id = q.sc_id', array('shipping_address'))
                ->where('q.id = ?', $id);
        
        return $this->getAdapter()->fetchRow($sql);
    }

    public function insertBuyer($userId, $billingAddress, $shippingAddress){
        $data = array(
            'user_id'           => $userId,
            'billing_address'   => serialize($billingAddress),
            'shipping_address'  => serialize($shippingAddress)
        );

        return $this->getAdapter()->insert($this->_userDataTable, $data);
    }

    public function selectBuyer($userId){
        $sql = $this->getAdapter()->select()->from($this->_userDataTable)->where('user_id = ?', $userId);
        return $this->getAdapter()->fetchRow($sql, null, Zend_Db::FETCH_ASSOC);
    }

    public function insertBuyerPayments($userId, $payment){
        $data = array(
            'user_id'  => $userId,
            'ref_type' => $payment['type'],
            'ref_id'   => $payment['id'],
            'date'     => date('Y-m-d H:i:s')
        );
        return $this->getAdapter()->insert($this->_userHistoryTable, $data);
    }

    public function selectUserDataByCartId($id) {
        $sql = $this->getAdapter()->select()
                ->from($this->_usercartTable, 
                        array('name', 'email', 'company', 'address1', 'address2', 'country', 'state', 'city', 'zip', 'phone'))
                ->where('id = ?', $id);
        return $this->getAdapter()->fetchRow($sql, null, Zend_Db::FETCH_ASSOC);
    }

    public function selectShippingDataByCartId($id) {
        $sql = $this->getAdapter()->select()
                ->from($this->_shippingTable,array('shipping_address'))
                ->where('cart_id = ?', $id);
        $shippingAddress = $this->getAdapter()->fetchOne($sql);
        return $shippingAddress ? unserialize($shippingAddress) : false;
    }

    public function selectUserByLogin($login) {
        $sql = $this->getAdapter()->select()->from( $this->_userTable, array('id') )->where('login = ?', $login);
        return $this->getAdapter()->fetchOne($sql);
    }

    public function selectUserInfoByUserId($id) {
        $sql = $this->getAdapter()->select()->from(array('buyer'=>$this->_userDataTable),array('id', 'billing_address', 'shipping_address'))
                ->joinLeft(array('user'=>$this->_userTable),'user.id = buyer.user_id',array('login','email','nickname','reg_date','role_id'))
                ->where('buyer.id = ?', $id);
        return $this->getAdapter()->fetchRow($sql);
    }

    public function selectAllUserCartsByUserId($id) {
        $sql = $this->getAdapter()->select()->from(array('buyer'=>$this->_userDataTable),'user_id')
                ->join(array('history'=>$this->_userHistoryTable),'history.user_id = buyer.user_id AND history.ref_type = "cart"')
                ->joinRight(array('cart'=>$this->_usercartTable), 'cart.id = history.ref_id', 'data')
                ->where('buyer.id = ?', $id);
                
        return $this->getAdapter()->fetchAll($sql);
    }

    public function selectAllUserQuotesByUserId($id) {
        $sql = $this->getAdapter()->select()->from(array('buyer'=>$this->_userDataTable),'user_id')
                ->join(array('history'=>$this->_userHistoryTable),'history.user_id = buyer.user_id AND history.ref_type = "quote"')
                ->joinRight(array('quote'=>$this->_quoteTable), 'quote.id = history.ref_id', array('status','valid_until'))
                ->where('buyer.id = ?', $id);

        return $this->getAdapter()->fetchAll($sql);
    }
}