<?php
/**
 * Buyerarea plugin for SEOTOASTER
 * allows to create buyer account on checkout
 * and manipulater buyer accounts for admin
 *
 * @author Pavel Kovalyov
 * @see http://www.seotoaster.com/
 * @TODO : rename actions and functions
 */
define('BAPLUGINPATH', dirname(realpath(__FILE__)));
require_once dirname(realpath(__FILE__)).'/system/models/BuyerareaModel.php';
require_once dirname(realpath(__FILE__)).'/system/Buyer.php';

class Buyerarea implements RCMS_Core_PluginInterface {
    private $_model         = null;
    private $_view          = null;
    private $_request       = null;
    private $_responce      = null;
	private $_translator	= null;
    private $_websiteUrl    = '';
    private $_session       = null;
    private $_loggedUser    = null;
    private $_isAdminLogged = false;
	private $_settings		= null;
    private $_options;
	private $_sitePath;
	
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
		if ($this->_loggedUser) {
			$this->_isAdminLogged = ($this->_loggedUser->getRoleId() == '1' || $this->_loggedUser->getRoleId() == '3')?true:false;
		}
        $this->_view->websiteUrl = $this->_websiteUrl;

		$this->_sitePath = unserialize(Zend_Registry::get('config'))->website->website->path;

		$this->_settings = $this->_model->selectSettings();

		$this->_responce = new Zend_Controller_Response_Http();
		$this->_request  = new Zend_Controller_Request_Http();
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
					return $this->_view->render('userinfo.phtml');
                }
                break;
			case 'loginform':
				if ($this->_loggedUser) {
					return $this->_view->render('clientlogout.phtml');
				} else {
					return $this->_view->render('clientlogin.phtml');
				}
				break;
            default :
				break;
        }
		//login for clients
		if (isset($requestParams['run']) && $requestParams['run']=='clientLogin'){
			$this->clientLogin();
		} 
		// I'll die if some stranger come
		if (!$this->_isAdminLogged){
			echo ('<script>window.location.href="'.$this->_websiteUrl.'";</script>');
			die();
		} else {
			// I'll open my secrets to known person
			if (isset($requestParams['run']) && !empty($requestParams['run'])){
				$method = $requestParams['run'];
				if (in_array($method, get_class_methods(__CLASS__))){
					$this->$method();
				}
			}
		}
    }

    /**
     * Method creates an user and store information for BuyerArea
     * @param array $billingData - must be associative array with next fields: firstname, lastname, company, email, phone, country, city, state, zip
     * @param array $shippingData - must be associative array with next fields: firstname, lastname, company, email, phone, country, city, state, zip
     * @param array $payment - optional array: 'type' - quote or cart, 'id' - number
     * @param boolean $notifyWithEmail - send email to user or not (this is not the same thing from settings!)
     * @return <type> - id of created user
     */
    public function createUser($billingData = array(), $shippingData = null, $payment = null, $notifyWithEmail = true) {
        $billingData['password'] = self::generatePassword(10, true);
        $user = new Buyer();
        $user->setEmail($billingData['email']);
        $user->setLogin($billingData['email']);
        $user->setRoleId(RCMS_Object_User_User::USER_ROLE_MEMBER);
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
            'mobile'    =>  $billingData['mobile'],
            'country'   =>  $billingData['country'],
            'city'      =>  $billingData['city'],
            'state'     =>  $billingData['state'],
            'zip'       =>  $billingData['zip'],
            'address1'  =>  $billingData['address1'],
            'address2'  =>  $billingData['address2']
        );
        if ( $shippingData ) {
            $shippingAddress = array(
                'firstname' =>  $shippingData['firstname'],
                'lastname'  =>  $shippingData['lastname'],
                'company'   =>  $shippingData['company'],
                'email'     =>  $shippingData['email'],
                'phone'     =>  $shippingData['phone'],
                'mobile'    =>  $shippingData['mobile'],
                'country'   =>  $shippingData['country'],
                'city'      =>  $shippingData['city'],
                'state'     =>  $shippingData['state'],
                'zip'       =>  $shippingData['zip'],
				'address1'  =>  $shippingData['address1'],
				'address2'  =>  $shippingData['address2']
            );
        }
        $user->setBillingAddress($billingAddress);
        $user->setShippingAddress($shippingAddress);
        
        if ($user->save()){
			if ($notifyWithEmail) {
				if ($this->_settings['autoemail'] == 'true'){
					$emailBody = $this->_settings['email'];
					$emailBody = strip_tags($this->_settings['email'], '<p><a><b><br>');
					$emailBody = preg_replace(array('~{websiteurl}~','~{login}~','~{password}~i'), array($this->_websiteUrl,$user->getLogin(),$billingData['password']), $emailBody);
					$emailBody = nl2br($emailBody);

					try {
						$this->sendEmail($billingAddress['email'], $user->getNickName(), 'Welcome to '.$this->_websiteUrl, $emailBody);
					} catch (Exception $e) {
						error_log('[SEOTOASTER] [BuyerArea plugin] Mailer error - '.$e->getMessage());
						error_log($e->getTraceAsString());
						error_log('[SEOTOASTER] [BuyerArea plugin] - Error sending email to '.$billingAddress['email']);
					}
				}
			} else {
				return array('id' => $user->getId(), 'pwd' => $billingData['password']);
			}
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
        if ($this->_isAdminLogged) {
			$id = false;
		} else {
			$id = $this->_loggedUser ? $this->_loggedUser->getId() : false;
		}
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
        if ( $id = $this->_request->getPost('id') ) {
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
		if ( $id = $this->_request->getParam('id') ) {
			$result = array();
			$quotes = $this->_model->selectAllUserQuotesByUserId((int)$id);
			$carts = $this->_model->selectAllUserCartsByUserId((int)$id);
			if ($quotes) {
				foreach ($quotes as $quote) {
					$quoteLink		= ''.$this->_websiteUrl.'sys/backend_quote/preview/qid/'.$quote['ref_id'].'.'. md5($quote['ref_id']).'.'.$quote['ref_id'];
					$invoiceLink	= ''.$this->_websiteUrl.'sys/backend_quote/pdf/type/quote/id/'.$quote['ref_id'].'/title/invoice/customId/{cid}/payment/{pm}';
					array_push($result, array(
						$quote['ref_type'].' '.$quote['ref_id'],
						$quote['date'],
						'Status: '.$quote['status'],
						'<button class="user-toolbar-button button-quote" link="'.$quoteLink.'">'.$this->_translator->translate('Quote').'</button>' .
						($quote['status']==RCMS_Object_Quote_Quote::Q_STATUS_SOLD?'<button link="'.$invoiceLink.'" class="user-toolbar-button button-invoice">'.$this->_translator->translate('Invoice').'</button>':'')
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
						'<button link="'.$pdfLink.'" class="user-toolbar-button button-invoice">'.$this->_translator->translate('Invoice').'</button>'
					));
				}
			}
			echo json_encode(array("aaData"=>$result));
			return true;
		}
		echo json_encode(array('done'=>'false'));
	}

	/**
	 * Method returns an html of settings screen (AJAX)
	 */
    private function settings(){
		if ($this->_request->isPost() && ($settings = $this->_request->getPost('settings')) ){
			if (is_array($settings)) {
				foreach ($settings as $key => $value){
					$this->_model->updateSettings($key, $value);
				}
				echo json_encode(array('done'=>true));
				return true;
			} else {
				echo json_encode(array('done'=>false));
				return false;
			}
        }
        $this->_view->settings = $this->_model->selectSettings();
        echo $this->_view->render('settings.phtml');
    }

	/**
	 * Method removes client (AJAX)
	 */
	private function delclient(){
		if ($this->_request->isPost() && ($buyerId = $this->_request->getParam('id')) ) {
			$id = $this->_model->getUserIdByBuyerId( $buyerId );
			$user = new Buyer($id);
			if ( $user->getBuyerId() == $buyerId ) {
				if ( $user->delete() ) {
					echo json_encode(array('done'=>true));
					return true;
				}
			}
		}
		echo json_encode(array('done' => false));
		}

	/**
	 * Method updates client info (AJAX)
	 */
	private function updclient() {
		if ($this->_request->isPost()) {
			$info = RCMS_Tools_Tools::stripSlashesIfQuotesOn( $this->_request->getParam('info')  );
			if ($info) {
				$userId = $this->_model->getUserIdByBuyerId( (int)$info['userinfo-userid'] );
				$billingAddress = array(
					'firstname' => $info['billing-address-firstname'],
					'lastname' => $info['billing-address-lastname'],
					'company' => $info['billing-address-company'],
					'email' => $info['billing-address-email'],
					'phone' => $info['billing-address-phone'],
					'mobile' => $info['billing-address-mobile'],
					'country' => $info['billing-address-country'],
					'city' => $info['billing-address-city'],
					'state' => $info['billing-address-state'],
					'zip' => $info['billing-address-zip'],
					'address1' => $info['billing-address-address1'],
					'address2' => $info['billing-address-address2']
				);
				$shippingAddress = array(
					'firstname' => $info['shipping-address-firstname'],
					'lastname' => $info['shipping-address-lastname'],
					'company' => $info['shipping-address-company'],
					'email' => $info['shipping-address-email'],
					'phone' => $info['shipping-address-phone'],
					'mobile' => $info['shipping-address-mobile'],
					'country' => $info['shipping-address-country'],
					'city' => $info['shipping-address-city'],
					'state' => $info['shipping-address-state'],
					'zip' => $info['shipping-address-zip'],
					'address1' => $info['shipping-address-address1'],
					'address2' => $info['shipping-address-address2']
				);
				$billingAddress = preg_replace('/^null$/i', '', $billingAddress);
				$shippingAddress = preg_replace('/^null$/i', '', $shippingAddress);
				$user = new Buyer($userId);
				if ( $user->getBuyerId() == $info['userinfo-userid'] ) {
					$user->setBillingAddress($billingAddress);
					$user->setShippingAddress($shippingAddress);
					if ( $user->save() ) {
						echo json_encode( array('done'=>true) );
						return true;
					}
				}
			}
		}
		
		echo json_encode(array('done' => false));
		return false;
	}

	private function uploadForm(){
		echo $this->_view->render('uploadform.phtml');
		return true;
	}

	private function uploadcsv(){
		$adapter = new Zend_File_Transfer_Adapter_Http();
		$adapter->addValidator('Extension', false, 'csv,txt');
		$adapter->setDestination($this->_sitePath.'tmp');
		if (!$adapter->receive()){
			$messages = $adapter->getMessages();
			echo implode("\n", $messages);
		} else {
			$shopSettings = $this->_model->selectShopConfig();

			$filename = $adapter->getFileName();

			if ( ($handle = fopen($filename, 'r')) !== false) {
				$data = array();
				while ($row = fgetcsv($handle)) {
					array_push($data, $row);
				}
			}
			fclose($handle);
			
			$keys = array_shift($data);

			$rndName = self::generatePassword(8, false);
			$outputFilename = $this->_sitePath.'tmp/'.$rndName.'.tmp';
			$handle = fopen($outputFilename, 'w');
			
			$countyList = RCMS_Object_QuickConfig_QuickConfig::$worldCountries;

			foreach ($data as &$row) {
			   $row = preg_replace('/^null/i', '', array_combine($keys, $row));

			   if (isset($row['billing-email'])&&!empty($row['billing-email'])) {
				   $row['billing-country'] = array_search($row['billing-country'], $countyList);
				   $row['shipping-country'] = array_search($row['shipping-country'], $countyList);
				   
				   if ($row['billing-country'] == '') {
					   $row['billing-country'] = $shopSettings['country'];
					   if ($shopSettings['state'] != ''){
						   $row['billing-state'] = $shopSettings['state'];
					   }
				   }
				   if ($row['shipping-country'] == '') {
					   $row['shipping-country'] = $shopSettings['country'];
					   if ($shopSettings['state'] != ''){
						   $row['shipping-state'] = $shopSettings['state'];
					   }
				   }
				   

				   $userData = $this->createUser(
					   array(
							'firstname' =>  $row['billing-firstname'],
							'lastname'  =>  $row['billing-lastname'],
							'company'   =>  $row['billing-company'],
							'email'     =>  $row['billing-email'],
							'phone'     =>  $row['billing-phone'],
							'country'   =>  $row['billing-country'],
							'city'      =>  $row['billing-city'],
							'state'     =>  $row['billing-state'],
							'zip'       =>  $row['billing-zip'],
							'address1'  =>  $row['billing-address1'],
							'address2'  =>  $row['billing-address2']
						)
					   , array(
							'firstname' =>  $row['shipping-firstname'],
							'lastname'  =>  $row['shipping-lastname'],
							'company'   =>  $row['shipping-company'],
							'email'     =>  $row['shipping-email'],
							'phone'     =>  $row['shipping-phone'],
							'country'   =>  $row['shipping-country'],
							'city'      =>  $row['shipping-city'],
							'state'     =>  $row['shipping-state'],
							'zip'       =>  $row['shipping-zip'],
							'address1'  =>  $row['shipping-address1'],
							'address2'  =>  $row['shipping-address2'],
							'mobile'	=>  $row['mobile'],
							'instructions'  =>  $row['shipping-instructions']
						)
					   , null, false);
				   if (is_array($userData)){
					fputcsv($handle, array('id'=>$userData['id'],'email'=>$row['billing-email'], 'password'=>$userData['pwd']));
				   }
			   }
			}
			fclose($handle);

			$this->_responce->clearAllHeaders()
				->setHeader('Content-type', 'application/force-download')
				->setHeader('Content-Disposition', 'attachment; filename="'.$rndName.'.csv"')
				->sendHeaders();
			readfile($outputFilename);
			$this->_responce->sendResponse();
			
			RCMS_Tools_FilesystemTools::deleteFile($outputFilename);
		}
	}

	private function clientLogin(){
		if ($this->_request->isPost()){
			$memberLoginModel = new LoginModel;
			$login		= $this->_request->getParam('clientLoginName');
			$password	= $this->_request->getParam('clientPassword');
			$client = $memberLoginModel->selectUserIdByLoginPass($login, $password);
			if ($client && is_object($client)){
				if ($client->role_id == RCMS_Object_User_User::USER_ROLE_MEMBER){
					$user = new RCMS_Object_User_User($client->id);
					$user->setLastLogin(date("Y-m-d H:i:s", time()));
					$user->save();
					$this->_session->memberLogged = true;
					$this->_session->memberLogin  = $client->login;
					$this->_session->memberEmail  = $client->email;
					$this->_session->memberNick   = $client->nickname;
					$this->_session->currentUser  = serialize($user);
					$buyerData = $this->_model->selectBuyer($user->getId());
					if ($buyerData && !empty ($buyerData['shipping_address'])) {
						$shipping = new RCMS_Object_Shipping_Shipping();
						$shipping->setShippingAddress(unserialize($buyerData['shipping_address']));
					}
					$pageToRedirect = $_SERVER['HTTP_REFERER'];
					$memberLandingPageData = $memberLoginModel->selectMemberLandingPage();
					$pageToRedirect = (!empty($memberLandingPageData) && $memberLandingPageData['url']) ? $memberLandingPageData['url'] . '.html' : $pageToRedirect;
					$this->_responce->setRedirect($pageToRedirect)->sendResponse();
				}
			}	
		}

		$this->_responce->setRedirect($_SERVER['HTTP_REFERER'])->sendResponse();
	}

	private function clientList() {
		if ($this->_request->isPost()) {
			$params = $this->_request->getParams();
			$totalRecords = $this->_model->getTotalBuyersCount();
			$filtered = false;
			// processing pagination
			if (isset ($params['iDisplayStart'])) {
				$limit = array('count'=>$params['iDisplayLength'],'offset'=>$params['iDisplayStart']);
			}

			if (isset ($params['iSortingCols']) && $params['iSortingCols']) {
				$order = array();
				for ($i = 0; $i <= --$params['iSortingCols']; $i++){
					$order[$this->_colsNumToName($params['iSortCol_'.$i])] = $params['sSortDir_'.$i];
				}
			}

			if (isset ($params['sSearch']) && !empty ($params['sSearch'])){
				$conditions['search'] = $params['sSearch'];
				$conditions['search_timestamp'] = Zend_Date::isDate($params['sSearch']) ? strtotime($params['sSearch']) : false;
				$conditions['fields'] = array();
				$cols = --$params['iColumns'];
				for ($i = 0; $i < $cols; $i++ ){
					if ( isset($params['bSearchable_'.$i]) && $params['bSearchable_'.$i] == true ){
						array_push($conditions['fields'], $this->_colsNumToName($i));
					}
				}
				$filtered = true;
			}
			$data = $this->_model->selectAllBuyers($conditions, $order, $limit);
			$return = array();
			foreach ($data as $row) {
				array_push($return, array(
					$row['id'],
					$row['nickname'],
					$row['email'],
					date('d M Y H:i:s', strtotime($row['reg_date'])),
					$row['action_id']?$row['action'].' #'.$row['action_id'].' on '.date('d M Y H:i:s', strtotime($row['action_date'])):'No activity',
					'<button class="user-toolbar-button button-info" >User info</button><button class="user-toolbar-button button-delete" >Delete</button>'
				) );
			}
			echo json_encode(array('sEcho'=>  intval($params['sEcho']), "iTotalRecords" => $totalRecords, 'iTotalDisplayRecords' => $filtered?count($return):$totalRecords, 'aaData' => $return));
			return true;
		}
		echo ' ';
		return false;
	}

	private function _colsNumToName($number){
		$return = false;
		switch ($number){
			case 0:
				$return = 'buyer.id';
				break;
			case 1:
				$return = 'user.nickname';
				break;
			case 2:
				$return = 'user.email';
				break;
			case 3:
				$return = 'user.reg_date';
				break;
			case 4:
				$return = 'act.ref_id';
				break;
		}

		return $return;
	}

}