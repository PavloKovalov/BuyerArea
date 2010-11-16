<?php
class Request {
	
	/**
	 * Object instance
	 *
	 * @var HttpRequest
	 */
	private static $_instance;
	
	/**
	 * instance of singleton object
	 *
	 * @return HttpRequest
	 */
	public static function getInstance () {
		if(self::$_instance === null) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Common method for get parameter from current request e.g. POST / GET / ENV etc..
	 *
	 * @param mixed $param
	 * @param null $default
	 * @return mixed
	 */
	protected function _getRequestParam($param, $default = null) {
		if(isset($_POST[$param])) {
			return $_POST[$param];
		}
		elseif(isset($_GET[$param])) {
			return $_GET[$param];
		}
		return $default;
	}
	
	/**
	 * Get integer parameter from request
	 *
	 * @param string $param
	 * @param integer $default
	 * @return integer
	 */
	public function getInt($param, $default = 0) {
		return (int)$this->_getRequestParam($param, $default);
	}
	
	/**
	 * Get string parameter from request
	 *
	 * @param string $param
	 * @param string $default
	 * @param boolean $trim
	 * @return string
	 */
	public function getString($param, $default = '', $trim = true) {
		$val = (string)$this->_getRequestParam($param, $default);
		if($trim) {
			return trim(htmlspecialchars($val));
		}
		return htmlspecialchars($val);
	}

    public function getArray($param) {
        return (array)($this->_getRequestParam($param));
    }

	public function getPost() {
		if(isset($_POST)) {
			return $_POST;
		}
	}

	public function getGet() {
		return $_GET;
	}
}