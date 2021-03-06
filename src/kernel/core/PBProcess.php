<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Cloud
 * Date: 13/2/4
 * Time: PM11:56
 * To change this template use File | Settings | File Templates.
 */

class PBProcess extends PBObject
{
	private $_processId = NULL;
	private $_mainModuleId = NULL;
	private $_attachedModules = array();
	private $_system = NULL;

	private $_processState	= 'waiting';
	private $_bootSequence	= NULL;
	private $_entryModule	= NULL;

	/**
	 * Get the process with specified process id
	 *
	 * @param string|null $id the specified process id
	 *
	 * @return PBProcess | null the specified PBProcess object
	 */
	public static function Process() { return PBSysKernel::Process(); }

	public static function Module( $moduleName, $reusable = TRUE, $noThrow = FALSE ) {
		try {
			return PBSysKernel::Process()->getModule( $moduleName, $reusable );
		}
		catch( Exception $e ) {
			if ( $noThrow ) return NULL;
			throw $e;
		}
	}
	public static function ServiceModule(){
		return PBSysKernel::Process()->_entryModule;
	}
	
	public static function Execute($module, $request = NULL, $reusable = FALSE) {
		$PROC = PBSysKernel::Process();

		if (!is_a($module, "PBModule")) $module = $PROC->getModule("{$module}", $reusable);
		return $PROC->_execChain( $module, $request );
	}
	public static function PackExecution( $module, $request = NULL, $reusable = FALSE) {
		if (!is_a($module, "PBModule")) $module = PBSysKernel::Process()->getModule( "{$module}", $reusable);

		$result = self::Execute($module, $request, $reusable);
		if ( !empty($module->ext->htmlClass) || !empty($module->ext->htmlAttr) )
			$result = "<div {$module->ext->htmlAttr} class='{$module->ext->htmlClass}' data-pb-mod='{$module->class}'>{$result}</div>";

		return $result;
	}
	public static function Render($module, $request = NULL, $reusable = FALSE) {
		echo self::PackExecution( $module, $request, $reusable );
	}

	public function __construct() {
		$this->_bootSequence = PBLList::GENERATE();
	}
	public function __destruct() {
	
	}

//SEC: Process API//////////////////////////////////////////////////////////////////////////////////////////////////////
	public function __get_id() {

		return $this->_processId;
	}



	public function addSearchPath( $package ) { return $this->_system->addModuleSearchPath( $package ); }
	public function removeSearchPath( $package ) { return $this->_system->removeModuleSearchPath( $package ); }

	public function getModule($moduleName, $instParam = NULL, $reusable = TRUE) {
		if ( func_num_args() == 2 )
		{
			$reusable = $instParam;
			$instParam = NULL;
		}

		return $this->_acquireModule($moduleName, $instParam, $reusable);
	}

	public function getNextModule()
	{
		if (!PBLinkedList::NEXT($this->_bootSequence)) return NULL;
		$moduleId = $this->_bootSequence->data['data'];
		PBLinkedList::PREV($this->_bootSequence);

		return $this->_attachedModules[$moduleId];
	}

	public function transferRequest($moduleRequest)
	{
		PBLinkedList::NEXT($this->_bootSequence);
		$this->_bootSequence->data['request'] = $moduleRequest;
		PBLinkedList::PREV($this->_bootSequence);
	}

	public function assignNextModule($moduleHandle, $moduleRequest = NULL)
	{
		if (is_a($moduleHandle, 'PBModule')) $moduleHandle = $moduleHandle->id;
		if (!array_key_exists($moduleHandle, $this->_attachedModules)) $moduleHandle = $this->_acquireModule($moduleHandle, TRUE)->id;


		$status = TRUE;
		$doPrepare = ($this->_processState == 'running') ? TRUE : FALSE;
		$status = $status && PBLinkedList::AFTER($this->_bootSequence, array('prepared' => $doPrepare, 'data' => $moduleHandle, 'request' => $moduleRequest), $moduleHandle);



		if (!$doPrepare) return $status;



		$status = $status && PBLList::NEXT($this->_bootSequence);

		if ($status)
		{
			$this->_prepareChain( $this->_attachedModules[$moduleHandle], $moduleRequest, FALSE );
			$status = $status && PBLList::PREV($this->_bootSequence);
		}

		return $status;
	}

	public function assignNextModules($moduleAry)
	{
		if (!is_array($moduleAry))
			throw(new Exception("Input parameter must be an array!"));


		$moduleAry = array_reverse($moduleAry);

		foreach ($moduleAry as $requestPair)
			$this->assignNextModule(@$requestPair['module'], @$requestPair['request']);
	}

	public function cancelNextModule() {

		$status = PBLList::NEXT($this->_bootSequence);
		if(!$status) return $status;

		$status = $status && PBLList::REMOVE($this->_bootSequence);
		return $status;
	}

	public function cancelFollowingModules( $skips = NULL ) {
	
		if ( func_num_args() == 0 )
		{
			while( PBLinkedList::NEXT($this->_bootSequence) )
				PBLinkedList::REMOVE($this->_bootSequence);
		}
		else
		if ( is_numeric( $skips ) )
		{
			if ( $skips > 0 )
			{
				$skipCounter = $skips;
				while( PBLinkedList::NEXT($this->_bootSequence) )
				{
					if ( $skipCounter <= 0 )
						PBLinkedList::REMOVE($this->_bootSequence);
					else
						$skipCounter--;
				}
				
				while( $skips-- > 0 )
					PBLinkedList::PREV( $this->_bootSequence );
			}
			else
			if ( $skips < 0 )
			{
				$skips = -$skips; $length = 0;
				while( PBLinkedList::NEXT($this->_bootSequence) ) $length++;
				
				if ( $length <= $skips )
				{
					while( $length-- > 0 ) PBLinkedList::PREV( $this->_bootSequence );
					return;
				}
				
				
				
				$length -= $skips;
				while( $skips-- > 0 ) PBLinkedList::PREV($this->_bootSequence);
				
				PBLinkedList::PREV($this->_bootSequence);
				while( $length-- > 0 ) PBLinkedList::REMOVE( $this->_bootSequence );
			}
		}
		else
		{
			if ( !is_array( $skips ) ) $skips = [ $skips ];
		
		
			$skipCounter = 0;
			while( PBLinkedList::NEXT($this->_bootSequence) )
			{
				$module = $this->_attachedModules[ $this->_bootSequence->data['data'] ];
				
				$valid = FALSE;
				ary_filter( $skips, function( $name ) use( &$valid, &$module ) {
					$name = "{$name}";
					$valid = $valid || ( $module instanceof $name );
				});
				
				if ( $valid )
					$skipCounter++;
				else
					PBLinkedList::REMOVE($this->_bootSequence);
			}
			
			
			while( $skipCounter-- > 0 )
				PBLinkedList::PREV( $this->_bootSequence );
		}
	}

	public function cancelFollowingUntilClass( $moduleName ) {

		if ( !is_array( $moduleName ) ) $moduleName = array( $moduleName );

		while ( PBLinkedList::NEXT($this->_bootSequence) )
		{
			$moduleId = $this->_bootSequence->data['data'];
			$module = $this->_attachedModules[ $moduleId ];
			
			$valid = FALSE;
			ary_filter( $moduleName, function( $name ) use( &$valid, &$module ) {
				$name = "{$name}";
				$valid = $valid || ( $module instanceof $name );
			});
			
			if ( !$valid )
				PBLinkedList::REMOVE($this->_bootSequence);
			else
			{
				PBLinkedList::PREV($this->_bootSequence);
				return;
			}
		}
	}

	public function replaceNextModule($moduleHandle, $moduleRequest = NULL)
	{
		if (is_a($moduleHandle, 'PBModule')) $moduleHandle = $moduleHandle->id;
		if(!array_key_exists($moduleHandle, $this->_attachedModules)) $moduleHandle = $this->_acquireModule($moduleHandle, TRUE)->id;

		$doPrepare = ($this->_processState == 'running') ? TRUE : FALSE;

		$status = TRUE;
		$status = $status && PBLList::NEXT($this->_bootSequence);

		if ($status)
		{
			$status = $status && PBLList::SET($this->_bootSequence, array('prepared' => $doPrepare, 'data' => $moduleHandle), $moduleHandle);

			if ($status && $doPrepare)
			{
				$this->_prepareChain( $this->_attachedModules[$moduleHandle], $moduleRequest, FALSE );
			}

			$status = $status && PBLList::PREV($this->_bootSequence);
		}

		return $status;
	}

	public function pushModule($moduleHandle, $moduleRequest = NULL)
	{
		if (is_a($moduleHandle, 'PBModule')) $moduleHandle = $moduleHandle->id;
		if(!array_key_exists($moduleHandle, $this->_attachedModules)) $moduleHandle = $this->_acquireModule($moduleHandle, TRUE)->id;


		$doPrepare = ($this->_processState == 'running') ? TRUE : FALSE;

		$status = PBLList::PUSH($this->_bootSequence, array('prepared' => $doPrepare, 'data' => $moduleHandle), $moduleHandle);



		if (!$doPrepare) return $status;



		if ($status)
		{
			$this->_prepareChain( $this->_attachedModules[$moduleHandle], $moduleRequest, FALSE );
		}

		return $status;
	}
//END SEC///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// MARK: Friend(PBSysKernel)
	public function run() {

		if(!$this->friend('PBSysKernel'))
			throw(new Exception("Calling an inaccessible method PBProcess::run()."));

		if($this->_processId === NULL)
			throw(new Exception("The process has no module to execute!."));

		$this->_processState = 'running';

		switch (SERVICE_EXEC_MODE)
		{
			case 'EVENT':
				$dataInput = array('propagation' => TRUE);

				PBLList::HEAD($this->_bootSequence);
				do
				{
					$moduleHandle = $this->_bootSequence->data['data'];
					$dataInput = $this->_attachedModules[$moduleHandle]->precommon($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->event($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->common($dataInput);
					if ( !is_array($dataInput) )
						$dataInput = array('propagation' => TRUE, 'data' => $dataInput);
					else
					if ( !isset($dataInput['propagation']) )
						$dataInput['propagation'] = TRUE;
					
					$dataInput['propagation'] = !empty($dataInput['propagation']);

					// INFO: Stop propagation
					if ( empty($dataInput['propagation']) ) break;
				}
				while(PBLList::NEXT($this->_bootSequence));
				break;

			case 'SHELL':
				$dataInput = NULL;

				PBLList::HEAD($this->_bootSequence);
				do
				{
					$moduleHandle = $this->_bootSequence->data['data'];
					$dataInput = $this->_attachedModules[$moduleHandle]->precommon($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->shell($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->common($dataInput);
				}
				while(PBLList::NEXT($this->_bootSequence));
				break;

			case "CORS":
				$dataInput = NULL;

				PBLList::HEAD($this->_bootSequence);
				do
				{
					$moduleHandle = $this->_bootSequence->data['data'];
					$dataInput = $this->_attachedModules[$moduleHandle]->precommon($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->cors($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->common($dataInput);
				}
				while(PBLList::NEXT($this->_bootSequence));
				break;

			case 'NORMAL':
			default:
				$dataInput = NULL;

				PBLList::HEAD($this->_bootSequence);
				do
				{
					$moduleHandle = $this->_bootSequence->data['data'];
					$dataInput = $this->_attachedModules[$moduleHandle]->precommon($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->exec($dataInput);
					$dataInput = $this->_attachedModules[$moduleHandle]->common($dataInput);
				}
				while(PBLList::NEXT($this->_bootSequence));
				break;
		}

		$this->_processState = 'waiting';

		return 'terminated';
	}

	// MARK: Friend(PBSysKernel)
	public function attachMainService($moduleName, $instParam, $moduleRequest) {

		if(!$this->friend('PBSysKernel')) throw(new Exception("Calling an inaccessible function PBProcess::attachMainModule()."));

		if($this->_mainModuleId != NULL) throw(new Exception("Reattachment of main module is not allowed"));

		// INFO: Reference the definition file comes along with the service
		if( available("service.env") ) using("service.env");


		// NOTE: Leading Module
		if ( defined('LEADING_MODULE') )
		{
			$module = $this->_acquireModule(LEADING_MODULE, TRUE);
			$moduleId = $module->id;
			PBLList::PUSH($this->_bootSequence,  array('prepared' => FALSE, 'data' => $moduleId, 'request' => $moduleRequest), $moduleId);
		}


		// NOTE: Service Entry Module
		$this->_entryModule = $this->_acquireModule($moduleName, $instParam, TRUE);
		$this->_mainModuleId = $this->_entryModule->id;
		PBLList::PUSH($this->_bootSequence, array('prepared' => FALSE, 'data' => $this->_mainModuleId, 'request' => $moduleRequest), $this->_mainModuleId);


		// NOTE: Tailing Module
		if ( defined('TAILING_MODULE') )
		{
			$module = $this->_acquireModule(TAILING_MODULE, TRUE);
			$moduleId = $module->id;
			PBLList::PUSH($this->_bootSequence,  array('prepared' => FALSE, 'data' => $moduleId, 'request' => $moduleRequest), $moduleId);
		}


		PBLList::HEAD($this->_bootSequence);
		do
		{
			$data	 = &$this->_bootSequence->data;
			$handle  = $data['data'];
			$request = $data['request'];
			$data['prepared'] = TRUE;

			$this->_prepareChain( $this->_attachedModules[$handle], $request );
		}
		while (PBLList::NEXT($this->_bootSequence));



		// INFO: EXEC Ready
		PBLinkedList::HEAD($this->_bootSequence);
	}

	// MARK: Friend(PBSysKernel)
	public function __set___processId($value) {

		if(!$this->friend('PBSysKernel'))
			throw(new Exception("Setting value to an undefined property __processId."));

		$this->_processId = $value;
	}

	// MARK: Friend(PBSysKernel)
	public function __set___sysAPI($value) {

		if(!$this->friend('PBSysKernel'))
			throw(new Exception("Setting value to an undefined property __sysAPI."));

		$this->_system = $value;
	}

	// INFO: Parse and prepare bootSequence
	private function _appendBootSequence( $bootSequence ) {

		if( empty( $bootSequence ) || !is_array( $bootSequence )) return;

		$bootSequence = array_reverse( $bootSequence );

		foreach( $bootSequence as $illustrator )
		{
			if ( is_a( $illustrator, stdClass::class ) )
				$illustrator = (array)$illustrator;
		
			// Skipping none array
			if (!is_array($illustrator)) continue;
				

			if(!array_key_exists('module', $illustrator))
				throw(new Exception("Error bootSequence structure definition"));



			$moduleHandle = $illustrator['module'];

			if (is_a($moduleHandle, 'PBModule') && array_key_exists($moduleHandle->id, $this->_attachedModules))
				$moduleId = $moduleHandle->id;
			else
			{
				$reuse = TRUE;
				if(array_key_exists('reuse', $illustrator))
				{
					if(!is_bool($illustrator['reuse']))
						throw(new Exception("Error bootSequence structure definition"));

					$reuse = $reuse && $illustrator['reuse'];
				}

				$moduleId = $this->_acquireModule($moduleHandle, $reuse)->id;
			}

			$request = (array_key_exists('request', $illustrator)) ? $illustrator['request'] : NULL;

			PBLList::AFTER($this->_bootSequence,  array('prepared' => FALSE, 'data' => $moduleId, 'request' => $request), $moduleId);
		}
	}

	private function _acquireModule( $moduleIdentifier, $instParam = NULL, $reusable = TRUE )
	{
		if ( func_num_args() == 2 )
		{
			$reusable = $instParam;
			$instParam = NULL;
		}



		if ( array_key_exists( $moduleIdentifier, $this->_attachedModules ) )
		{
			$module = $this->_attachedModules[ $moduleIdentifier ];

			// INFO: Given module identifier is in package format
			if ( ($moduleIdentifier != $module->id) && !$reusable ) $module = NULL;
		}


		if ( empty($module) )
		{
			$module	= $this->_system->acquireModule( $moduleIdentifier, $instParam );
			$module->__processInst = $this;
			$moduleId = $module->id;
			$this->_attachedModules[ $moduleId ] = $module;

			if ( $reusable ) $this->_attachedModules[ $moduleIdentifier ] = $module;
		}

		return $module;
	}

	private function _prepareChain( PBModule $module, $request = NULL, $bootProcessing = TRUE )
	{
		$module->preparePrecommon($request);
	
		switch (SERVICE_EXEC_MODE)
		{
			case 'EVENT':
				$module->prepareEvent($request);
				break;

			case 'SHELL':
				$module->prepareShell($request);
				break;

			case "CORS":
				$module->prepareCORS($request);
				break;

			case 'NORMAL':
			default:
				$module->prepare($request);
				break;
		}
		
		$module->prepareCommon($request);

		if ( $bootProcessing )
			$this->_appendBootSequence( $module->bootstrap );
	}

	private function _execChain( PBModule $module, $request = NULL )
	{
		switch ( SERVICE_EXEC_MODE )
		{
			case "EVENT":
				if ( func_num_args() > 1 ) {
					$module->preparePrecommon($request);
					$module->prepareEvent($request);
					$module->prepareCommon($request);
				}

				$result = $module->precommon(NULL);
				$result = $module->event($result);
				$result = $module->common($result);
				break;

			case "SHELL":
				if ( func_num_args() > 1 ) {
					$module->preparePrecommon($request);
					$module->prepareShell($request);
					$module->prepareCommon($request);
				}

				$result = $module->precommon(NULL);
				$result = $module->shell($result);
				$result = $module->common($result);
				break;

			case "CORS":
				if ( func_num_args() > 1 ) {
					$module->preparePrecommon($request);
					$module->prepareCORS($request);
					$module->prepareCommon($request);
				}

				$result = $module->precommon(NULL);
				$result = $module->cors($result);
				$result = $module->common($result);
				break;

			case "NORMAL":
			default:
				if ( func_num_args() > 1 ) {
					$module->preparePrecommon($request);
					$module->prepare($request);
					$module->prepareCommon($request);
				}

				$result = $module->precommon(NULL);
				$result = $module->exec($result);
				$result = $module->common($result);
				break;
		}
		
		return $result;
	}
}
