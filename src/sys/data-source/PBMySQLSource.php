<?php
	/**
	 ** 1024.QueueCounter - PBMySQLSource.php
	 ** Created by JCloudYu on 2016/04/14 18:27
	 **/

	using( 'sys.data-source.PBDataSource' );
	using( 'sys.db.ExtPDO' );
	using( 'ext.base.assistive' );

	class PBMySQLSource extends PBDataSource
	{
		private $_pdoConnection = NULL;

		public function __construct( $DSURI = "//user:pass@127.0.0.1:3306/db", $options = array(), $driverOpt = array() ) {

			$URI = is_array($DSURI) ? $DSURI : PBDataSource::ParseURI( $DSURI );

			$host	= CAST( @$URI[ 'host' ], 'string' );
			$db		= CAST( @$URI[ 'path' ][ 0 ], 'string' );
			$port	= CAST( @$URI[ 'port' ], 'int strict', 3306 );
			$user	= CAST( @$URI[ 'user' ], 'string', '' );
			$pass	= CAST( @$URI[ 'pass' ], 'string', '' );



			if ( !empty($db) ) $options[] = 'CREATE_VAR';

			$this->_pdoConnection = new ExtPDO(
				ExtPDO::DSN( $host, $db, $port, 'mysql' ),
				empty($user) ? NULL : $user,
				empty($pass) ? NULL : $pass,
				array_merge( $options, $driverOpt )
			);
		}

		public function __get_source(){
			return $this->_pdoConnection;
		}
	}