<?php
/**
 * Buyerarea plugin for SEOTOASTER
 * allows to create buyer account on checkout
 * and manipulater buyer accounts for admin
 *
 * @author Pavel Kovalyov
 * @see http://www.seotoaster.com/
 */
define('BAPLUGINPATH', dirname(realpath(__FILE__)));
require_once dirname(realpath(__FILE__)).'/system/models/BuyerareaModel.php';
require_once dirname(realpath(__FILE__)).'/system/Buyer.php';

class Buyerarea implements RCMS_Core_PluginInterface {
    private $_model         = null;
    private $_view          = null;
    private $_request       = null;
	private $_translator	= null;
    private $_websiteUrl    = '';
    private $_session       = null;
    private $_loggedUser    = null;
    private $_isAdminLogged = false;
    private $_options;

    public function __construct($options, $data) {
        $this->_model = new BuyerareaModel();
        $this->_view  = new Zend_View();
        $this->_view->setScriptPath(dirname(realpath(__FILE__)) . '/views');
		try {
			$this->_translator = new Zend_Translate(array(
				'adapter'	=> 'csv',
				'delimiter' => ',',
				'content'	=> BAPLUGINPATH.'/system/languages',
				'scan'		=> Zend_Translate::LOCALE_FILENAME,
				'locale'	=> 'en'
			));
			Zend_Registry::set('Zend_Translate', $this->_translator);
		} catch (Exception $e) {
			echo $e->getMessage();
		}

        if (!empty ($options)) $this->_options = $options;

        $this->_websiteUrl = $data['websiteUrl'];
        $this->_session = new Zend_Session_Namespace($this->_websiteUrl);
        $this->_loggedUser = unserialize($this->_session->currentUser);
		if (null !== $this->_loggedUser) {
			$this->_isAdminLogged = ($this->_loggedUser->getRoleId() == '1' || $this->_loggedUser->getRoleId() == '3')?true:false;
		}
        $this->_view->websiteUrl = $this->_websiteUrl;
    }

    public function run($requestParams = array()) {
        switch ($this->_options[0]){
            case 'userinfo':
                if (!$this->_loggedUser){
					return;
                }
                $loggedId = $this->_loggedUser->getId();
                $user = new Buyer($loggedId);
                if ($user->getBuyerId()) {
                    $this->_view->billingAddress = $user->getBillingAddress();
                    $this->_view->shippingAddress = $user->getShippingAddress();
					return $this->_view->render('attachtocpanel.phtml');
                }
                break;
            default :
				break;
        }
		// I'll die if some stranger come
		if (!$this->_isAdminLogged){
			echo ('<script>window.location.href="'.$this->_websiteUrl.'";</script>');
			die();
		}
		// I'll open my secrets to known person
        if (isset($requestParams['run']) && !empty($requestParams['run'])){
            $method = $requestParams['run'];
            if (in_array($method, get_class_methods(__CLASS__))){
                $this->$method();
            }
        }
    }

    /**
     * Method creates an user and store information for BuyerArea
     * @param array $billingData - must be associative array with next fields: firstname, lastname, company, email, phone, country, city, state, zip
     * @param array $shippingData - must be associative array with next fields: firstname, lastname, company, email, phone, country, city, state, zip
     * @param array $payment - optional array: 'type' - quote or cart, 'id' - number
     * @return <type> - id of created user
     */
    public function createUser($billingData = array(), $shippingData = null, $payment = null) {
        $billingData['password'] = self::generatePassword(10, true);
        $user = new Buyer();
        $user->setEmail($billingData['email']);
        $user->setLogin($billingData['email']);
        $user->setRoleId(RCMS_Object_User_User::USER_ROLE_USER);
        $user->setStatus('active');
        $user->setIdSeosambaUser('0');
        if (!isset($billingData['firstname']) && !isset ($billingData['lastname']) && isset($billingData['name'])) {
            if (strpos(' ', $billingData['name']) > 2) {
                $tmp = explode(' ', $billingData['name'], 2);
                $billingData['firstname'] = $tmp[0];
                $billingData['lastname'] = $tmp[1];
            } else {
                $billingData['firstname'] = $billingData['name'];
                $billingData['lastname'] = '';
            }

        }
        $user->setNickName($billingData['firstname'].' '.$billingData['lastname']);
        $user->setPassword(md5($billingData['password']));
        $user->setRegDate(date('Y-m-d H:i:s'));
                
        $billingAddress = array(
            'firstname' =>  $billingData['firstname'],
            'lastname'  =>  $billingData['lastname'],
            'company'   =>  $billingData['company'],
            'email'     =>  $billingData['email'],
            'phone'     =>  $billingData['phone'],
            'country'   =>  $billingData['country'],
            'city'      =>  $billingData['city'],
            'state'     =>  $billingData['state'],
            'zip'       =>  $billingData['zip']
        );
        if ( $shippingData ) {
            $shippingAddress = array(
                'firstname' =>  $shippingData['firstname'],
                'lastname'  =>  $shippingData['lastname'],
                'company'   =>  $shippingData['company'],
                'email'     =>  $shippingData['email'],
                'phone'     =>  $shippingData['phone'],
                'country'   =>  $shippingData['country'],
                'city'      =>  $shippingData['city'],
                'state'     =>  $shippingData['state'],
                'zip'       =>  $shippingData['zip']
            );
        }
        $user->setBillingAddress($billingAddress);
        $user->setShippingAddress($shippingAddress);
        
        if ($user->save()){
            $this->sendEmail($billingAddress['email'], $user->getNickName(), 'Welcome to '.$this->_websiteUrl, '<b>'.$billingData['password'].'</b>');
        }

        return $user->getId();
    }

	/**
	 * Method generates password with gived lenth.
	 * @param int $length - Lenght of password
	 * @param bool $complicated - Use both cases and special charasters in password
	 * @return string
	 */
    public static function generatePassword($length = 8, $complicated = false){
        $vowels     = 'aeuy';
        $consonants = 'bdghjmnpqrstvz';
        $numbers    = '23456789';

        if ($complicated) {
            $consonants .= 'BDGHJLMNPQRSTVWXZ';
            $vowels     .= 'AEUY';
            $numbers .= '@#$%';
        }

        $password = '';
        $string = str_shuffle($vowels . $consonants . $numbers);
        mt_srand(time());
        for ($i = 0; $i < $length; $i++) {
            $password .= $string[(mt_rand(1, strlen($string)))];
        }
        
        return $password;
    }

	/**
	 * Method send an email to user.
	 * @param string $toMail
	 * @param string $to
	 * @param string $subject
	 * @param string $body
	 * @return bool
	 */
    private function sendEmail($toMail, $to, $subject, $body) {
        $shoppingConfig = $this->_model->selectShopConfig();
        $settings = $this->_model->selectMailSettings();
        
        $mailer = new RCMS_Object_Mailer_Mailer();

        if ($settings['use_smtp']) {
            $mailer->setSmtpConfig($settings['smtp_login'], $settings['smtp_password'], $settings['smtp_host']);
            $mailer->setTransport(RCMS_Object_Mailer_Mailer::MAIL_TYPE_SMTP);
        }

        $mailer->setFrom($shoppingConfig['company']);
        $mailer->setFromMail($shoppingConfig['email']);
        $mailer->setTo($to);
        $mailer->setToMail($toMail);
        $mailer->setSubject($subject);
        $mailer->setBody($body);

        return $mailer->send();
    }

	/**
	 * Method saving a record for user payment
	 * @param array $payment
	 * @return bool
	 */
    public function logpayment( Array $payment){
        
        $id = $this->_loggedUser ? $this->_loggedUser->getId() : false;
        if (!$id) {
            switch ($payment['type']){
                case 'quote':
                    $billingData    = $this->_model->selectUserDataByQuoteId($payment['id']);
                    $shippingData   = unserialize($billingData['shipping_address']);
                    unset($billingData['shipping_address']);
                    break;
                case 'cart':
                    $billingData    = $this->_model->selectUserDataByCartId($payment['id']);
                    $shippingData   = $this->_model->selectShippingDataByCartId($payment['id']);
                    break;
            }
            $exists = $this->_model->selectUserByLogin($billingData['email']);
            if ($exists){
                $id = $exists;
            } else {
                $id = $this->createUser($billingData, $shippingData, $payment);
            }
        }
        $user = new Buyer($id);

        if ($user->getBuyerId()) {
            return $user->savePayment($payment);
        } else {
            if (!empty ($billingData)) {
                $user->setBillingAddress($billingData);
            }
            if (!empty ($shippingData)) {
                $user->setShippingAddress($shippingData);
            }
            $user->save();
            return $user->savePayment($payment);
        }

    }

    private function _checkInfoArrayKeys($searcharray){
        $keys = array('firstname', 'lastname', 'company', 'email', 'phone', 'country', 'city', 'state', 'zip');
        return array_key_exists($keys, $searcharray);
    }

	/**
	 * method render main backed screen
	 * @return <void>
	 */
    private function manageClients(){
        $this->_view->buyers = $this->_model->selectAllBuyers();
		echo $this->_view->render('manageclients.phtml');
	}

	/**
	 * method returns informatiom about buyer (AJAX)
	 * @return json
	 */
    public function getbuyerinfo(){
        if ( $id = $_POST['id'] ) {
            $data = $this->_model->selectUserInfoByUserId($id);
            if ($data){
                $data['billing_address'] = unserialize($data['billing_address']);
                $data['shipping_address'] = unserialize($data['shipping_address']);
                echo json_encode($data);
                return true;
            }
        }
        echo json_encode(array('done'=>'false'));
    }

	/**
	 * method generates array for DataTable with users payment history (AJAX)
	 * @return json
	 */
	private function getbuyerpayments(){
		if ( $id = $_REQUEST['id'] ) {
			$result = array();
			$quotes = $this->_model->selectAllUserQuotesByUserId((int)$id);
			//var_dump($quotes);
			$carts = $this->_model->selectAllUserCartsByUserId((int)$id);
			//var_dump($carts);
			if ($quotes) {
				foreach ($quotes as $quote) {
					$quoteLink		= ''.$this->_websiteUrl.'sys/backend_quote/pdf/type/quote/id/'.$quote['ref_id'].'/title/quote/customId/'.$quote['ref_id'];
					$invoiceLink	= ''.$this->_websiteUrl.'sys/backend_quote/pdf/type/quote/id/'.$quote['ref_id'].'/title/invoice/customId/{cid}/payment/{pm}';
					array_push($result, array(
						$quote['ref_type'].' '.$quote['ref_id'],
						$quote['date'],
						'Status: '.$quote['status'],
						'<button class="user-toolbar-button button-quote" link="'.$quoteLink.'">Quote</button>' .
						($quote['status']==RCMS_Object_Quote_Quote::Q_STATUS_SOLD?'<button link="'.$invoiceLink.'" class="user-toolbar-button button-invoice">Invoice</button>':'')
					));
				}
			}
			if ($carts){
				foreach ($carts as $cart) {
					$pdfLink = ''.$this->_websiteUrl.'sys/backend_quote/pdf/type/cart/id/'.$cart['ref_id'].'/title/invoice/customId/{cid}/payment/{pm}';
					array_push($result, array(
						$cart['ref_type'].' '.$cart['ref_id'],
						$cart['date'],
						'',
						'<button link="'.$pdfLink.'" class="user-toolbar-button button-invoice">Invoice</button>'
					));
				}
			}
			echo json_encode(array("aaData"=>$result));
			return true;
		}
		echo json_encode(array('done'=>'false'));
	}

    private function getusercarts(){
        if ( $id = $_REQUEST['id'] ) {
            $this->_view->carts = $this->_model->selectAllUserCartsByUserId((int)$id);
            echo $this->_view->render('viewusercarts.phtml');
            return true;
        }
        return false;
    }
    private function getuserquotes(){
        if ( $id = $_REQUEST['id'] ) {
            $this->_view->quotes = $this->_model->selectAllUserQuotesByUserId((int)$id);
            echo $this->_view->render('viewuserquotes.phtml');
            return true;
        }
        return false;
    }

	/**
	 * Method returns an html of settings screen (AJAX)
	 */
    private function settings(){
        if (isset($_POST['settings'])){
            foreach ($_POST['settings'] as $key => $value){
                $this->_model->updateSettings($key, $value);
            }
        }
        $this->_view->settings = $this->_model->selectSettings();
        echo $this->_view->render('settings.phtml');
    }

}