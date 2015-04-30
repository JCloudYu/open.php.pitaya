<?php
/**
 * 1024.QueueCounter - PBEventCtrl.php
 * Created by JCloudYu on 2015/04/30 20:54
 */
	using( 'kernel.basis.PBObject' );
	using( 'ext.base.array' );

	final class PBEventCtrl extends PBObject
	{
		public static function Fire( $Service, array $evntArgs = array() )
		{
			$EVT_STORAGE = path( 'data.events' );
			if ( !is_dir( $EVT_STORAGE ) ) @mkdir( $EVT_STORAGE, 0644, TRUE );



			$PITAYA_EXEC = __WEB_ROOT__ . "/pitaya.sh";
			$EVT_ARGS	 = implode(' ', ary_filter( $evntArgs, function( $item ){ return "{$item}"; } ));
			$EVENT_HASH	 = md5("{$Service} {$EVT_ARGS}" . uniqid("", TRUE));
			$EVENT_ID	 = "E_" . date("Y-m-d") . "_" . substr($EVENT_HASH, rand(0, strlen($EVENT_HASH) - __EVENT_IDENTIFIER_LEN__), __EVENT_IDENTIFIER_LEN__);

			if ( !is_executable( $PITAYA_EXEC ) ) return FALSE;


			$OUT = array();
			exec( "{$PITAYA_EXEC} {$service} Event {$EVT_ARGS}", $OUT, $STATUS );


			if ( !empty($OUT) ) file_put_contents("{$EVT_STORAGE}/{$EVENT_ID}.out", implode("\n", $OUT));


			file_put_contents("{$EVT_STORAGE}/event.history", "{$STATUS} - {$service} {$EVT_ARGS}" . EON, FILE_APPEND);
			return $STATUS;
		}
	}
