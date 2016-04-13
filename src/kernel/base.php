<?php
	// Detect minimum PHP Version
	if ( PHP_VERSION_ID < 50600 )
		die( "The system requires php 5.6.0 or higher!" );


	if ( defined( 'PITAYA_BASE_CORE_INITIALIZED' ) ) return
	define( 'PITAYA_BASE_CORE_INITIALIZED', TRUE );



	define("PITAYA_VERSION_MAJOR",	 1);
	define("PITAYA_VERSION_MINOR",	 4);
	define("PITAYA_VERSION_BUILD",	 0);
	define("PITAYA_VERSION_PATCH",	 1);
	define("PITAYA_VERSION_ID", PITAYA_VERSION_MAJOR * 10000 + PITAYA_VERSION_MINOR * 100 + PITAYA_VERSION_BUILD );
	define('PITAYA_VERSION_SHORT', PITAYA_VERSION_MAJOR . '.' . PITAYA_VERSION_MINOR);
	define('PITAYA_VERSION', PITAYA_VERSION_MAJOR . '.' . PITAYA_VERSION_MINOR . '.' . PITAYA_VERSION_BUILD);
	define('PITAYA_VERSION_DETAIL', PITAYA_VERSION . '-' . PITAYA_VERSION_PATCH);



	$GLOBALS['invokeTime'] = $_SERVER['REQUEST_TIME'];


	// DEPRECATED: The constants will be removed in v2.0.0
	define('SYS_ENV_CLI', 'CMD', TRUE);
	define('SYS_ENV_NET', 'NET', TRUE);



	define('EXEC_ENV_CLI',	'CLI', TRUE);
	define('EXEC_ENV_HTTP', 'HTTP', TRUE);

	define('EON',	"\n",	TRUE);
	define('EOR',	"\r",	TRUE);
	define('EORN',	"\r\n",	TRUE);
	define('EOB',	'<br>',	TRUE);

	define('LF',	"\n",	TRUE);
	define('CR',	"\r",	TRUE);
	define('CRLF',	"\r\n",	TRUE);
	define('BR',	'<br>',	TRUE);



	// INFO:Some special initializations
	call_user_func(function() {

		// Detect operating system information
		(preg_match('/^win|^WIN/', PHP_OS) === 1) ? define('__OS__', 'WIN', TRUE) : define('__OS__', 'UNIX', TRUE);

		$GLOBALS['RUNTIME_ENV'] = array();

		$env = shell_exec( ( __OS__ == "WIN" ) ? 'set' : 'printenv');
		$env = preg_split("/(\n)+|(\r\n)+/", $env);
		foreach ( $env as $envStatement )
		{
			if ( ($pos = strpos($envStatement, "=")) === FALSE ) continue;

			$var	 = substr( $envStatement, 0, $pos );
			$content = substr( $envStatement, $pos + 1 );
			$GLOBALS['RUNTIME_ENV'][$var] = $content;
		}
	});


	if ( !defined( '__ROOT__' ) )
		define('__ROOT__', realpath( dirname($_SERVER["SCRIPT_FILENAME"]) ), TRUE);


	if ( php_sapi_name() == "cli" )
	{
		define('__WEB_ROOT__',	getcwd(), TRUE);


		define('SYS_WORKING_ENV',	SYS_ENV_CLI,	TRUE); // DEPRECATED: The constants will be removed in v2.0.0

		define('SYS_EXEC_ENV',		EXEC_ENV_CLI, 	TRUE);
		define('REQUESTING_METHOD',	'',				TRUE);
		define('PITAYA_HOST',		 @"{$GLOBALS['RUNTIME_ENV']['PITAYA_HOST']}", TRUE);
		define('EOL',				"\n", TRUE);


		// NOTE: Remove script file path
		array_shift( $_SERVER['argv'] );


		// NOTE: Special intialization
		if ( "{$_SERVER['argv'][0]}" == "-entry" )
		{
			array_shift($_SERVER['argv']);
			$GLOBALS['STANDALONE_EXEC'] = array(
				'script' => "{$_SERVER['argv'][0]}",
				'cwd'	 => getcwd()
			);
			array_shift( $_SERVER['argv'] );

			define( '__STANDALONE_EXEC_MODE__', TRUE, TRUE );
		}


		$_SERVER['argc'] = count($_SERVER['argv']);
	}
	else
	{
		define('__WEB_ROOT__',	($_SERVER['DOCUMENT_ROOT'] = dirname(__ROOT__)), TRUE);

		define('SYS_WORKING_ENV',	SYS_ENV_NET, TRUE); // DEPRECATED: The constants will be removed in v2.0.0

		define('SYS_EXEC_ENV',		EXEC_ENV_HTTP,	TRUE);
		define('REQUESTING_METHOD',	strtoupper($_SERVER['REQUEST_METHOD']),	TRUE);
		define('PITAYA_HOST', "{$_SERVER['HTTP_HOST']}", TRUE);

		define('EOL', '<br />', TRUE);

		$_SERVER['argv'] = array(); $_SERVER['argc'] = 0;
	}



	// INFO: Change current working environment space root
	chdir( __WEB_ROOT__ );


	if ( !defined( '__STANDALONE_EXEC_MODE__' ) )
		define( '__STANDALONE_EXEC_MODE__', FALSE, TRUE );







	require_once __ROOT__ . '/kernel/api.tool.php';



	// ISSUE: We need to verify the configuration data...
	if ( SYS_EXEC_ENV === EXEC_ENV_CLI )
	{
		define( 'CLI_ENV',	TRUE,	TRUE );
		define( 'NET_ENV',	FALSE,	TRUE );

		if ( file_exists(__WEB_ROOT__ . "/cli.php") )
		{
			require_once __WEB_ROOT__ . "/cli.php";
			define( 'CONFIG_MODE', 'CLI' );
		}
	}
	else
	{
		define( 'CLI_ENV',	FALSE,	TRUE );
		define( 'NET_ENV',	TRUE,	TRUE );

		if ( PITAYA_HOST != "" && file_exists( __WEB_ROOT__ . "/config-" . PITAYA_HOST . ".php" ) )
		{
			require_once __WEB_ROOT__ . "/config-" . PITAYA_HOST . ".php";
			define( 'CONFIG_MODE', 'HOST' );
		}
	}


	if ( !defined( 'CONFIG_MODE' ) )
	{
		if ( file_exists( __WEB_ROOT__ . "/config.php" ) )
		{
			require_once __WEB_ROOT__ . "/config.php";
			define( 'CONFIG_MODE', 'DEFAULT' );
		}
		else
		{
			define( 'CONFIG_MODE', 'NONE' );
		}
	}



	// INFO: Common configurations...
	if ( file_exists( __WEB_ROOT__ . "/common.php" ) )
		require_once __WEB_ROOT__ . "/common.php";

	if ( __STANDALONE_EXEC_MODE__ && file_exists( "{$GLOBALS['STANDALONE_EXEC']['cwd']}/runtime.php" ) )
		require_once "{$GLOBALS['STANDALONE_EXEC']['cwd']}/runtime.php";






	// INFO: System Core APIs ( using, package, path, available and etc... )
	require_once __ROOT__ . '/kernel/runtime.php';
	require_once __ROOT__ . '/kernel/api.core.php';
	require_once __ROOT__ . '/kernel/api.encrypt.php';



	// INFO: Include configurations according working environment
	require_once __ROOT__ . "/kernel/" . ( (SYS_WORKING_ENV == SYS_ENV_CLI) ? "cli.config.php" : "net.config.php" );
	require_once __ROOT__ . "/kernel/env.const.php";



	// INFO: Runtime Configuration Control
	call_user_func(function(){
		// INFO: Error Reporting Control
		s_define( "PITAYA_SUPPRESS_EXPECTED_WARNINGS", TRUE, TRUE, FALSE );
		error_reporting( PITAYA_SUPPRESS_EXPECTED_WARNINGS ? (E_ALL & ~E_STRICT & ~E_NOTICE) : E_ALL );
	});



	// INFO: Load system core libraries and prepare system constants
	using('kernel.basis.PBObject');
	using('kernel.basis.*');
	using('kernel.core.*');
	using('kernel.sys');

	PBSysKernel::__imprint_constants();
	PBRequest::__imprint_constants();
	PBRuntimeCtrl::__ImprintEnvironment();

	// INFO: Clean up everything
	unset($GLOBALS['randomCert']);
	unset($GLOBALS['servicePath']);
	unset($GLOBALS['sharePath']);
	unset($GLOBALS['dataPath']);
	unset($GLOBALS['extPath']);
	unset($GLOBALS['invokeTime']);
	unset($GLOBALS['RUNTIME_ENV']);
	unset($GLOBALS['RUNTIME_CONF']);
	unset($GLOBALS['RUNTIME_ARGC']);
	unset($GLOBALS['RUNTIME_ARGV']);
	unset($GLOBALS['STANDALONE_EXEC']);


	// INFO: There's no DEBUG_BACKTRACE_PROVIDE_OBJECT before PHP 5.3.6
	s_define('DEBUG_BACKTRACE_PROVIDE_OBJECT', TRUE, TRUE);
