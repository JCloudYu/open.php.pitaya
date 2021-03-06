<?php
/**
 * 0001.pitaya - PBLog.php
 * Created by JCloudYu on 2014/07/04 17:28
 */

	using('ext.net.ip');

	final class PBLog
	{
		const LOG_INFO_TIME		= 1;
		const LOG_INFO_CATE		= 2;
		const LOG_INFO_SERVICE	= 4;
		const LOG_INFO_MODULE	= 8;
		const LOG_INFO_ROUTE	= 16;
		const LOG_INFO_ALL		= 0xFFFFFFFF;
	
		private $_logStream = NULL;
		public function __construct($logPath)
		{
			$this->_logStream = self::ObtainStream($logPath);
		}
		public function genLogMsg( $message, $logPos = FALSE, $logCate = '', $options = [] )
		{
			if ( !is_array($options) ) $options = [];
			
			$LOG_INFO = array_key_exists( 'info-level', $options ) ? $options['info-level'] | 0 : self::LOG_INFO_ALL;
			if ( !is_string($message) ) $message = print_r( $message, TRUE );
			if ( !is_array(@$options['tags']) ) $options['tags'] = array();
			$info = self::PrepLogInfo($logCate, $options);



			// INFO: Process other tags
			$tags = implode('', array_map(function($item) {
				return "[{$item}]";
			}, array_unique($options['tags'])));



			// INFO: Write file stream
			$position = ($logPos) ? " {$info['position']}" : '';
			
			$timeInfo = '';
			if ( $LOG_INFO & self::LOG_INFO_TIME )
			{
				$timeInfo = in_array( 'UNIX_TIMESTAMP', $options ) ? $info['time'] : $info['timestamp'];
				$timeInfo = "[{$timeInfo}]";
			}
			
			$cateInfo	= ( $LOG_INFO & self::LOG_INFO_CATE ) ? "[{$info['cate']}]" : '';
			$basisInfo	= ( $LOG_INFO & self::LOG_INFO_SERVICE ) ? "[{$info['service']}]" : '';
			$moduleInfo	= ( $LOG_INFO & self::LOG_INFO_SERVICE ) ? "[{$info['module']}]" : '';
			$routeInfo	= ( $LOG_INFO & self::LOG_INFO_ROUTE) ? "[{$info['route']}]" : '';
			
			
			
			$msg = "{$timeInfo}{$cateInfo}{$basisInfo}{$moduleInfo}{$routeInfo}{$tags} {$message}{$position}";
			return $msg;
		}
		public function logMsg( $message, $logPos = FALSE, $logCate = '', $options = [] )
		{
			if ( empty($this->_logStream) ) return FALSE;


			$msg = ( @$options[ 'row-output' ] === TRUE ) ? $message : $this->genLogMsg( $message, $logPos, $logCate, $options );
			fwrite( $this->_logStream, "{$msg}\n" );
			fflush( $this->_logStream );

			if (!empty(PBLog::$LogDB))
			{
				if (PBLog::$_forceLogDB || in_array('WRITE_DB', $options))
					PBLog::LogDB("{$message}{$position}", $info);
			}

			return $msg;
		}



		public static function Log($message, $logPos = FALSE, $logFileName = '', $options = array())
		{
			$logPath = DEFAULT_SYSTEM_LOG_DIR . "/" . (empty($logFileName) ? "service.pblog" : $logFileName);
			$log	 = self::ObtainLog($logPath);

			return $log->logMsg($message, $logPos, '', $options);
		}
		public static function ERRLog($message, $logPos = FALSE, $logFileName = '', $options = array())
		{
			$logPath = DEFAULT_SYSTEM_LOG_DIR . "/" . (empty($logFileName) ? "error.pblog" : $logFileName);
			$log	 = self::ObtainLog($logPath);

			error_log( $msg = $log->genLogMsg( $message, $logPos, 'ERROR', array_merge($options, [ 'info-level' => self::LOG_INFO_ALL & ~self::LOG_INFO_TIME ]) ) );
			return $log->logMsg( $message, $logPos, 'ERROR', $options );
		}
		public static function SYSLog($message, $logPos = FALSE, $logFileName = '', $options = array())
		{
			$logPath = DEFAULT_SYSTEM_LOG_DIR . "/" . (empty($logFileName) ? "system.pblog" : $logFileName);
			$log	 = self::ObtainLog($logPath);

			return $log->logMsg($message, $logPos, 'SYS', $options);
		}
		public static function ShareLog($message, $logPos = FALSE, $logFileName = '', $options = array())
		{
			$logPath = DEFAULT_SYSTEM_LOG_DIR . "/" . (empty($logFileName) ? "share.pblog" : $logFileName);
			$log	 = self::ObtainLog($logPath);

			return $log->logMsg($message, $logPos, 'SHARE', $options);
		}
		public static function CustomLog($message, $cate = 'CUSTOM', $logPos = FALSE, $logFileName = '', $options = array())
		{
			$logPath = DEFAULT_SYSTEM_LOG_DIR . "/" . (empty($logFileName) ? "custom.pblog" : $logFileName);
			$log	 = self::ObtainLog($logPath);

			return $log->logMsg($message, $logPos, empty($cate) ? 'CUSTOM' : "{$cate}", $options);
		}



		// INFO: Global logging API
		/**
		 * @var PDO
		 */
		private static $LogDB	= NULL;
		private static $LogTbl	= '';

		const LOG_TABLE = '__ext_pdo_sys_wide_log';

		public static function ConnectDB($conInfo = array())
		{
			if (PBLog::$LogDB !== NULL) return;

			$driver = empty($conInfo['type']) ? 'mysql' : "{$conInfo['type']}";
			$dsn	= "{$driver}:host={$conInfo['host']};port={$conInfo['port']};dbname={$conInfo['db']};";

			PBLog::$LogDB = new PDO($dsn, "{$conInfo['account']}", "{$conInfo['password']}");
			PBLog::$LogDB->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			PBLog::$LogDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


			PBLog::$LogTbl = empty($conInfo['table']) ? PBLog::LOG_TABLE : $conInfo['table'];


			$tbl = PBLog::$LogTbl;
			$checkTbl = PBLog::$LogDB->query("SHOW TABLES LIKE '{$tbl}';")->fetch();
			if (empty($checkTbl))
			{
				PBLog::$LogDB->query(<<<SQL
					CREATE TABLE IF NOT EXISTS `{$tbl}` (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`cate` varchar(128) NOT NULL,
						`service` varchar(128) NOT NULL,
						`module` varchar(128) NOT NULL,
						`tags` text NOT NULL,
						`route` text NOT NULL,
						`msg` longtext NOT NULL,
						`time` bigint(20) NOT NULL,
						PRIMARY KEY (`id`)
					) DEFAULT CHARSET=utf8;
SQL
				);
			}
		}

		// cate time service module tag route msg
		private static function LogDB($message, array $attributes = array())
		{
			if (empty(PBLog::$LogDB)) return FALSE;

			$tableName = PBLog::$LogTbl;
			$stmt = PBLog::$LogDB->prepare("INSERT INTO `{$tableName}`(`cate`, `service`, `module`, `tags`, `route`, `msg`, `time`)
								  								VALUES(:cate, :service, :module, :tags, :route, :msg, :time);");

			$stmt->execute(array(
				':cate'	=> @"{$attributes['cate']}",
				':service'	=> @"{$attributes['service']}",
				':module'	=> @"{$attributes['module']}",
				':tags'	=> @"{$attributes['tags']}",
				':route'	=> @"{$attributes['route']}",
				':msg'		=>  "{$message}",
				':time'	=> @"{$attributes['time']}"
			));

			return $stmt->rowCount() > 0;
		}

		private static $_forceLogDB = FALSE;
		public static function ForceDB($force = FALSE) { self::$_forceLogDB = !empty($force); }


		/**
		 * Produce environmental information
		 *
		 * @param string $logCate
		 * @param array $options
		 *
		 * @return array
		 */
		private static function PrepLogInfo($logCate = '', $options = array())
		{
			static $remoteIp = NULL;

			// INFO: Collect remote ip
			if ($remoteIp === NULL)
				$remoteIp = RemoteIP(PBRequest::Request()->server, TRUE, TRUE);

			// INFO:
			if (SYS_WORKING_ENV === SYS_ENV_CLI)
			{
				$route = 'CLI';

				if (function_exists("posix_geteuid") && function_exists("posix_getpwuid"))
				{
					$shellInfo = posix_getpwuid(posix_getuid());
					$route = "CLI({$shellInfo['name']}|{$shellInfo['uid']}|{$shellInfo['gid']})";
				}
			}
			else
				$route = $remoteIp;



			$trace = debug_backtrace();
			array_shift($trace);	// This scope
			array_shift($trace);	// Caller scope


			// INFO: Retrieve the first module in the stack
			$counter = count($trace);
			$module = '';
			while ($counter > 0)
			{
				$inst = @$trace[$counter]['object'];
				if (is_a($inst, 'PBModule')) $module = $inst->class;

				$counter--;
			}


			$curTime = time();
			return array(
				'cate'		=> (empty($logCate) || !is_string($logCate)) ? 'INFO' : "{$logCate}",
				'time'		=> $curTime,
				'timestamp' => date("Y-m-d G:i:s", $curTime),
				'service'	=> (!defined('__SERVICE__') ? 'Pitaya' : __SERVICE__),
				'module'	=> $module,
				'route'		=> $route,
				'position'	=> "{$trace[0]['file']}:{$trace[0]['line']}"
			);
		}

		/**
		 * Obtain log singleton according to log file path
		 *
		 * @param string $logFilePath
		 *
		 * @return PBLog
		 */
		public static function ObtainLog($logFilePath)
		{
			static $_cachedLog = array();

			$pathKey = md5($logFilePath);
			if (empty($_cachedLog[$pathKey]))
				$_cachedLog[$pathKey] = new PBLog($logFilePath);

			return $_cachedLog[$pathKey];
		}

		/**
		 * Obtain log stream according to log file path
		 *
		 * @param string $logFilePath
		 *
		 * @return Resource
		 */
		private static function ObtainStream($logFilePath)
		{
			static $_fileStream = array();

			$pathKey = md5($logFilePath);

			if (empty($_fileStream[$pathKey]))
			{
				if (is_dir($logFilePath))
					return NULL;

				$logPath = dirname($logFilePath);
				if (!is_dir($logPath)) @mkdir($logPath);



				if (is_file($logFilePath))
				{
					$today = strtotime(date('Y-m-d'));
					$fileTime = filemtime($logFilePath);

					if ($fileTime <= $today)
					{
						$fileTime = date('Ymd', filemtime($logFilePath));
						fileMove($logFilePath, "{$logFilePath}-{$fileTime}");
					}
				}


				$hLog = @fopen($logFilePath, 'a+b');
				if ( empty( $hLog ) ) return NULL;



				$_fileStream[$pathKey] = $hLog;
			}

			return $_fileStream[$pathKey];
		}
	}
