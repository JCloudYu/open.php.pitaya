<?php
/**
 * 1024.QueueCounter - PBShellInstall.php
 * Created by JCloudYu on 2015/04/18 20:18
 */
	using('kernel.core.PBModule');

	class PBShellInstall extends PBModule
	{
		public function prepareShell( $moduleRequest )
		{
			s_define("FORCE_INSTALL", in_ary( array('-f', '--force'), $moduleRequest ), TRUE, TRUE);
			s_define("PATCH_DIR", path('service'),	TRUE, FALSE);



			// INFO: Check Installation
			$installFlag = path('data.flags', '.install');
			if ( file_exists( $installFlag ) && !FORCE_INSTALL )
			{
				PBStdIO::STDOUT( "System has been installed already! Updating timestamp!" );
				file_put_contents( $installFlag, date("Y/m/d H:i:s O\n"), FILE_APPEND );
				Termination::NORMALLY();
			}



			// INFO: Prepare patch module
			$patchObj = PBProcess::Process()->getModule('PBPatch');
			$patchObj->patchDir = PATCH_DIR;



			// INFO: Prepare FuncChain module
			$funcChainObj = PBProcess::Process()->getModule('PBFuncChain', FALSE);
			$funcChainObj->post = function() {
				PBStdIO::STDOUT("Finalizing...     " . date("Y/m/d H:i:s"));

				// INFO: Remove previously attached streaming destinations
				PBStream::STDOUT()->pop( TRUE );
				PBStream::STDERR()->pop( TRUE );

				PBStdIO::STDOUT("Updating timestamp...");

				$installFlag = path("data.flags", '.install');
				file_put_contents("{$installFlag}", date("Y/m/d H:i:s O\n"), FILE_APPEND);

				$dataDir = path("data");
				exec("chown -R apache:apache {$dataDir}");
			};



			PBProcess::Process()->assignNextModules(array(
				array( 'module' => $patchObj ),
				array( 'module' => $funcChainObj )
			));
		}

		public function shell($param)
		{
			// INFO: Attach another streaming destination to STDOUT and STDERR
			$stream = PBStream::Rotatable( path('data.log', 'install-detail.log') );
			PBStream::STDOUT()->tee( $stream );
			PBStream::STDERR()->tee( $stream );



			PBStdIO::STDOUT("Start installing... " . date("Y/m/d H:i:s"));

			// INFO: Read system configurations first...
			PBStdIO::STDOUT( "Collecting system runtime configuration info..." );
			$systemConfig = $this->readConfig();



			// INFO: Purge storage...
			$this->purgeStorage();

			PBStdIO::STDOUT( "Establishing runtime storage layout..." );
			$this->createStorage();



			// INFO: Write configuration back to storage...
			PBStdIO::STDOUT( "Generating runtime.json..." );
			file_put_contents(path("data.conf", "runtime.json"), @json_encode( $systemConfig ));

			return ( DB()->version == "" || FORCE_INSTALL ) ? "0.0.0" : DB()->version;
		}

		protected function purgeStorage()
		{
			$dataDir = path('data');

			// INFO: Purge project dependent contents
			PBStdIO::STDOUT("Removing previous installations and configurations...");
			exec("rm -rf {$dataDir};", $out = NULL, $status);
			if ( $status )
			{
				PBStdIO::STDERR( implode("\n", $out) );
				PBStdIO::STDERR( "Error occurred! Terminating..." );
				Termination::WITH_STATUS( Termination::STATUS_ERROR );
			}


			PBStdIO::STDOUT("Generating system dependent data storage...");
			exec("mkdir -p {$dataDir};", $out = NULL, $status);
			if ( $status )
			{
				PBStdIO::STDERR(implode("\n", $out));
				PBStdIO::STDERR( "Error occurred! Terminating..." );
				Termination::WITH_STATUS( Termination::STATUS_ERROR );
			}
		}

		protected function createStorage()
		{
			$dataDir = path('data');


			PBStdIO::STDOUT("Generating module storage...");
			exec("mkdir -p " . path('data.modules') . ";", $out = NULL, $status);
			if ( $status )
			{
				PBStdIO::STDERR( implode("\n", $out) );
				PBStdIO::STDERR( "Error occurred! Terminating..." );
				Termination::WITH_STATUS( Termination::STATUS_ERROR );
			}

			PBStdIO::STDOUT("Generating log storage...");
			exec("mkdir -p " . path('data.log') . ";", $out = NULL, $status);
			if ( $status )
			{
				PBStdIO::STDERR( implode("\n", $out) );
				PBStdIO::STDERR( "Error occurred! Terminating..." );
				Termination::WITH_STATUS( Termination::STATUS_ERROR );
			}

			PBStdIO::STDOUT("Generating configuration storage...");
			exec("mkdir -p " . path('data.conf') . ";", $out = NULL, $status);
			if ( $status )
			{
				PBStdIO::STDERR( implode("\n", $out) );
				PBStdIO::STDERR( "Error occurred! Terminating..." );
				Termination::WITH_STATUS( Termination::STATUS_ERROR );
			}

			PBStdIO::STDOUT("Generating status flag storage...");
			exec("mkdir -p " . path('data.flags') . ";", $out = NULL, $status);
			if ( $status )
			{
				PBStdIO::STDERR( implode("\n", $out) );
				PBStdIO::STDERR( "Error occurred! Terminating..." );
				Termination::WITH_STATUS( Termination::STATUS_ERROR );
			}
		}

		protected function readConfig()
		{
			$readDBConf = function()
			{
				$conf = array();

				// Database Host & port
				$value = trim(PBStdIO::READ("Enter DB Host [localhost]: "));
				$conf['host'] = empty($value) ? "localhost" : $value;

				$value = trim(PBStdIO::READ("Enter DB Port [3306]: "));
				$conf['port'] = empty($value) ? 3306 : $value;



				// Database Name
				do
				{
					$value = trim(PBStdIO::READ("Enter DB name: "));
					if ( !empty($value) ) break;

					PBStdIO::STDERR("DB name should not be empty...");
				}
				while(1);
				$conf["db"] = $value;



				// User Database Account
				do
				{
					$value = trim(PBStdIO::READ("Enter DB user account: "));
					if ( !empty($value) ) break;

					PBStdIO::STDERR("DB user account should not be empty...");
				}
				while(1);
				$conf["account"] = $value;



				// User Database Password
				do
				{
					do
					{
						$value = PBStdIO::READ("Enter DB user pass: ", TRUE, FALSE);
						if ( !empty($value) ) break;

						PBStdIO::STDERR("Password should not be empty...");
					}
					while(1);

					if ( $value == PBStdIO::READ("Confirm DB user pass: ", TRUE, FALSE) ) break;
					PBStdIO::STDERR("Password doesn't match!");
				}
				while(1);


				$conf["password"] = $value;

				return $conf;
			};

			do
			{
				PBStdIO::STDOUT( "Constructing database connection info..." );
				$conf = $readDBConf();
				try
				{
					DB( $conf, array('CREATE_VAR', 'FORCE_CREATE') );
					break;

				}
				catch(Exception $e)
				{
					PBStdIO::STDERR( "Database connection error! " . $e->getMessage() );
					PBStdIO::STDERR( "Starting over..." );
					continue;
				}
			}
			while( 1 );

			return array( 'database' => $conf );
		}
	}
