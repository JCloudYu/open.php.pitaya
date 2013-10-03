<?php
	using('ext.base.time');
	using('ext.base.misc');

	final class PBCookie
	{
		// region [ Singleton Control ]
		private static $_cookieInst = NULL;
		private static $_confDomain = '';
		private static $_confPath = '';
		public static function Cookie()
		{
			if (self::$_cookieInst) return self::$_cookieInst;
			self::$_cookieInst = new PBCookie(self::$_confDomain);
			return self::$_cookieInst;
		}

		public static function CookieDomain($domain = '') { self::$_confDomain = $domain; }
		public static function CookiePath($subPath = '') { self::$_confPath = (empty($subPath)) ? '/'.__SERVICE__ : $subPath; }

		private $_domain = '';
		private $_path = '';
		private function __construct($path = '', $domain = '')
		{
			$this->_domain = $domain;
			$this->_path = $path;
		}
		// endregion

		// region [ Getters and Setters ]
		public function __unset($name)
		{
			if (headers_sent()) return;
			unset($_COOKIE[$name]);
			setcookie($name, NULL, 1, self::$_confPath, $this->_domain);
		}

		public function __isset($name) { return array_key_exists($name, $_COOKIE); }

		public function __get($name) { return @$_COOKIE[$name]; }

		public function __set($name, $value)
		{
			if (headers_sent()) return;

			if (is_array($value))
			{
				foreach ($value as $key => $value)
					setcookie("{$name}[$key]", $value, 0, self::$_confPath, $this->_domain);
			}
			else
				setcookie($name, $value, 0, self::$_confPath, $this->_domain);
		}

		public function set($name, $value, $time = 0)
		{
			if (headers_sent()) return;

			$time = (is_array($time)) ? time() + TO(@$time['day'], 	  'int') * DAY_SEC
				+ TO(@$time['hour'],   'int') * HOUR_SEC
				+ TO(@$time['minute'], 'int') * MINUTE_SEC
				+ TO(@$time['second'], 'int')
				: time() + TO($time, 'int');

			if (is_array($value))
			{
				foreach ($value as $key => $value)
					setcookie("{$name}[$key]", $value, $time, self::$_confPath, $this->_domain);
			}
			else
				setcookie($name, $value, $time, self::$_confPath, $this->_domain);
		}

		public function get($name, $type, $default)
		{
			if (!array_key_exists($name, $_COOKIE)) return $default;

			return TO($_COOKIE[$name], $type);
		}
		// endregion
	}