<?php
/*
 * File: sys.php
 * Created by JCloudYu.
 * DateTime: 13/2/9 PM4:05
 */

class SYS extends PBObject implements ISYS
{
//SEC: System Boot Loader///////////////////////////////////////////////////////////////////////////////////////////////
	private static $_SYS_INSTANCE = NULL;
	public static function boot($argc = 0, $argv = NULL) {

		// INFO: Avoid repeated initialization
		if(self::$_SYS_INSTANCE) return;

		self::$_SYS_INSTANCE = new SYS();
		self::$_SYS_INSTANCE->__jobDaemonRun();
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////

//SEC: System Instance//////////////////////////////////////////////////////////////////////////////////////////////////
	private $_entryModule = NULL;
	private $_systemId = NULL;

	// INFO: Constructor declared as a private function is to maintain the singleness of the SYS object
	private function __construct() {

		try
		{
			$this->__arrangeVariables();

			// INFO: Generate the unique system execution Id
			$this->_systemId = encode($this->_incomingRecord['rawRequest']);
			$this->_entryModule = $this->_incomingRecord['module'];
			$this->__forkProcess($this->_entryModule, $this->_incomingRecord['request']);
		}
		catch(PBException $e)
		{
			print_r($e);
		}
		catch(Exception $e)
		{
			print_r($e);
		}
	}

	public function __get_id() {

		return $this->_systemId;
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////

//SEC: System Variable Manager//////////////////////////////////////////////////////////////////////////////////////////
	private $_incomingRecord = NULL;

	private function __arrangeVariables() {

		// ISSUE: We still need to deal with other HTTP methods such as DELETE, UPDATE, .....etc

		// INFO: This function will unify the means accessing arguments passed into the system
		// INFO: In this version, only $_COOKIE and $_SESSION will be kept

		$this->_incomingRecord = array();

		//SEC: REQUEST_URI Purge////////////////////////////////////////////////////////////////////////////////////////
		// INFO: Purge redundant separators from the REQUEST_URI
		// INFO: Example: http://SERVER_HOST////////RC//REQUEST/REQUEST///REQUEST?PARAMETERS=FDSAFDSAFDSADSA//
		// INFO: 		  will be purged into
		// INFO:		  http://SERVER_HOST/RC/REQUEST/REQUEST/REQUEST?PARAMETERS=FDSAFDSAFDSADSA
		$this->_incomingRecord['rawRequest'] = preg_replace('/\/+/',
															'/',
															preg_replace('/^\/*|\/*$/', '', $_SERVER['REQUEST_URI'])
														   );
		//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////

		$this->_incomingRecord['post'] = $_POST;
		$this->_incomingRecord['files'] = $_FILES;
		$this->_incomingRecord['env'] = $_ENV;
		$this->_incomingRecord['server'] = $_SERVER;


		// INFO: GET information is not kept since it may contains error parsed parameters
		// INFO: This means that the main module have to parse its own parameters from request
		unset($_GET);
		unset($HTTP_GET_VARS);

		unset($_POST);
		unset($HTTP_POST_VARS);

		unset($_FILES);
		unset($HTTP_POST_FILES);

		unset($_ENV);
		unset($HTTP_ENV_VARS);

		unset($_SERVER);
		unset($HTTP_SERVER_VARS);

		unset($_REQUEST);

		// NOTE: We still need to solve the session and cookie problem
		//$this->_incomingRecord['session'] = $_SESSION;
		//$this->_incomingRecord['cookie'] = $_COOKIE;
		//unset($_COOKIE); unset($HTTP_COOKIE_VARS);
		//unset($_SESSION); unset($HTTP_SESSION_VARS);

		// INFO: Extract the requested module from request string
		// DECLARE($requestItems)
		$requestItems = explode('/', $this->_incomingRecord['rawRequest']);

		// INFO: http://SERVER_HOST/
		// INFO: http://SERVER_HOST/RC
		// DECLARE($module, $moduleRequest)
		if(count($requestItems) == 1)
		{
			$module = $requestItems[0] === '' ? 'WEB' : strtoupper($requestItems[0]);
			$moduleRequest = '';
		}
		else
		// INFO: http://SERVER_HOST/RC/REQUEST
		{
			$module = strtoupper(array_shift($requestItems));
			$moduleRequest = implode('/', $requestItems);
		}

		$this->_incomingRecord['module'] = $module;
		$this->_incomingRecord['request'] = $moduleRequest;
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////

//SEC: System Execution API/////////////////////////////////////////////////////////////////////////////////////////////

	// INFO: In this version of system, there will be only one process instance in the system (main process)
	private $_processQueue = array();

	private function __forkProcess($module, $moduleRequest) {

		$systemIds = divide($this->_systemId);
		$processId = encode(array($module, uniqid("", TRUE)), $systemIds['extended']);
		$process = new PBProcess();

		$process->__processId = $processId;
		$process->__sysAPI = $this;
		$process->attachMainModule($module, $moduleRequest);

		$this->_processQueue[$processId] = $process;
	}

	private function __killProcess($processId) {

		if(array_key_exists($processId, $this->_processQueue))
			unset($this->_processQueue[$processId]);
	}

	private function __jobDaemonRun() {

		while(count($this->_processQueue) > 0)
		{
			foreach($this->_processQueue as $process)
			{
				$result = $process->run();
				if(is_null($result))
					$result = 'terminated';

				switch($result)
				{
					case 'terminated':
						$this->__killProcess($process->id);
						break;
					case 'exit':
						return;
					default:
						break;
				}
			}
		}
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////

//SEC: System Response//////////////////////////////////////////////////////////////////////////////////////////////////
	// NOTE: We need to figure out a way to response error according to request module media type
	private function __responseError() {

		using('sys.http.PBHTTPReply');

		PBHTTPReply::ReplyStatus(PBHTTPStatus::STATUS_404_NOT_FOUND);
		PBHTTPReply::ReplyHTML('error', array('responseId' 	=> PBHTTPStatus::STATUS_404_NOT_FOUND,
											  'errMsg'		=> 'Service not found!',
											  'errId'		=> 10));
		die();
	}

	private function dump() {

		preg_replace('/\n/', '<br \>', preg_replace('/\ /', '&nbsp;', print_r(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT), TRUE)));
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////

//SEC: ISYS
	public function acquireModule($moduleName) {

		$caller = $this->caller;
		if($caller['class'] != 'PBProcess')
			throw(new Exception("Calling an inaccessible function SYS::acquireModule()."));

		if(!file_exists(__ROOT__."/modules/$moduleName/$moduleName.php"))
			throw(new Exception("Module doesn't exist!"));

		$processId = $caller['object']->id;
		$processIds = divide($processId);
		$moduleId = encode(array($processId, $moduleName), $processIds['extended']);

		using("modules.$moduleName.$moduleName");
		$module = new $moduleName();
		$module->__moduleId = $moduleId;

		return $module;
	}

	public function validateChild($childrenId) {

		$childrenId = divide($childrenId);
		$selfId = $this->id;

		return $selfId['base'] === $childrenId['extended'];
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////
}