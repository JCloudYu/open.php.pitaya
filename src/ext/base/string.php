<?php

	function CheckAccountSyntax($account) { return preg_match("/^[a-zA-Z0-9][a-zA-Z0-9._-]+$/i", $account) ? TRUE : FALSE; }
	function CheckPasswordSyntax($password) { $pass = trim($password); return (strlen($pass) >= 8 && $password === $pass); }
	function CheckEmailSyntax($email) { return (filter_var($email, FILTER_VALIDATE_EMAIL) !== FALSE); }

	function ParseVersion($verStr, $keepEmpty = FALSE)
	{
		if(!preg_match('/^\d+[.-]\d+(([.-]\d+[.-]\d+){0,1}|([.-]\d+){0,1})$/', $verStr)) return NULL;

		$ver = preg_split('/[.-]/', $verStr);
		return array(
			'major'		=> TO($ver[0], 'int'),
			'minor'		=> TO($ver[1], 'int'),
			'build'		=> ($ver[2] === NULL && $keepEmpty) ? NULL : TO($ver[2], 'int'),
			'revision'	=> ($ver[3] === NULL && $keepEmpty) ? NULL : TO($ver[3], 'int')
		);
	}

	function NormalizeVersion($verStr)
	{
		$ver = ParseVersion($verStr);
		return ($ver === NULL) ? NULL : "{$ver['major']}.{$ver['minor']}.{$ver['build']}-{$ver['revision']}";
	}

	function CompareVersion($verA, $verB, $minimalMajored = TRUE)
	{
		$normalize = (func_num_args() > 2) ? TRUE : FALSE;

		$verA = ParseVersion($verA, !$normalize);
		$verB = ParseVersion($verB, !$normalize);

		if (empty($verA) || empty($verB)) return FALSE;

		// major
		if ($verA['major'] > $verB['major']) return  1;
		if ($verA['major'] < $verB['major']) return -1;

		if ($verA['minor'] > $verB['minor']) return  1;
		if ($verA['minor'] < $verB['minor']) return -1;

		if ($verA['build'] !== NULL || $verB['build'] !== NULL)
		{
			if ($verA['build'] === NULL) return ($minimalMajored) ? -1 : 1;
			if ($verB['build'] === NULL) return ($minimalMajored) ?  1 : -1;

			if ($verA['build'] > $verB['build']) return  1;
			if ($verA['build'] < $verB['build']) return -1;


			if ($verA['revision'] !== NULL || $verB['revision'] !== NULL)
			{
				if ($verA['revision'] === NULL) return ($minimalMajored) ? -1 : 1;
				if ($verB['revision'] === NULL) return ($minimalMajored) ?  1 : -1;

				if ($verA['revision'] > $verB['revision']) return  1;
				if ($verA['revision'] < $verB['revision']) return -1;
			}
		}

		return 0;
	}

	function TimeElapsedQuantum($now, $target) {

		$nowBuff = new DateTime();
		$nowBuff->setTimestamp($now);
		$targetBuff = new DateTime();
		$targetBuff->setTimestamp($target);

		$interval = $nowBuff->diff($targetBuff);

		if ($interval->y)
		{
			$unit = ($interval->y > 1) ? 'years' : 'year';
			return "{$interval->y} {$unit} before";
		}

		if ($interval->m)
		{
			$unit = ($interval->m > 1) ? 'months' : 'month';
			return "{$interval->m} {$unit} before";
		}

		if ($interval->d)
		{
			$unit = ($interval->d > 1) ? 'days' : 'day';
			return "{$interval->d} {$unit} before";
		}

		if ($interval->h)
		{
			$unit = ($interval->h > 1) ? 'hours' : 'hour';
			return "{$interval->h} {$unit} before";
		}

		if ($interval->i)
		{
			$unit = ($interval->i > 1) ? 'minutes' : 'minute';
			return "{$interval->i} {$unit} before";
		}

		$unit = ($interval->s > 1) ? 'seconds' : 'second';
		return "{$interval->s} {$unit} before";
	}

	function ext_strtr($pattern, $replacements, $glue = FALSE, $mapper = NULL)
	{
		// INFO: Fail safe
		if ( !is_array($replacements) ) return '';
		$mapper	 = ( !is_callable($mapper) ) ? function($item){ return $item; } : $mapper;
		$pattern = "{$pattern}";
		$glue	 = ( $glue === TRUE ) ? '' : $glue;


		$firstElm = reset($replacements);
		if ( !empty($firstElm) && !is_array($firstElm) )
			return strtr( $pattern, $mapper($replacements) );
		else
		{
			$sepMode	= ( $glue === FALSE || $glue === NULL );
			$collector	= array();

			foreach ( $replacements as $key => $replace )
			{
				$result = strtr( $pattern, $mapper($replace, $key) );
				$collector[$key] = $result;
			}

			return ($sepMode) ? $collector : implode("{$glue}", $collector);
		}
	}

	function ext_trim($instance)
	{
		if (!is_array($instance))
			return trim($instance);
		else
		{
			$result = array();
			foreach ($instance as $key => $str)
				$result[$key] = trim($str);

			return $result;
		}
	}

	function LogStr($logMsg, $dateStr = TRUE, $timeSecond = TRUE, $timeZoneStr = TRUE) {
		$fmt = array();
		if ( $dateStr ) $fmt[] = "Y/m/d";
		$fmt[] = ($timeSecond) ? "H:i:s" : "H:i";
		if ( $timeZoneStr ) $fmt[] = "O";

		$fmt = implode(' ', $fmt);
		return "[" . date($fmt) . "] {$logMsg}";
	}

	function ord_utf8($string, &$offset) {
		$code = ord(substr($string, $offset,1));
		if ($code >= 128) {        //otherwise 0xxxxxxx
			if ($code < 224) $bytesnumber = 2;                //110xxxxx
			else if ($code < 240) $bytesnumber = 3;        //1110xxxx
			else if ($code < 248) $bytesnumber = 4;    //11110xxx
			$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
			for ($i = 2; $i <= $bytesnumber; $i++) {
				$offset ++;
				$code2 = ord(substr($string, $offset, 1)) - 128;        //10xxxxxx
				$codetemp = $codetemp*64 + $code2;
			}
			$code = $codetemp;
		}
		$offset += 1;
		if ($offset >= strlen($string)) $offset = -1;
		return $code;
	}

	function chr_utf8($u) {
		return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
	}

	function xml2array( $xmlString )
	{
		static $ImprintFunc = NULL;
		if ( $ImprintFunc === NULL )
		{
			$ImprintFunc = function( SimpleXMLElement $imprint ) use ( &$ImprintFunc )
			{
				$attributes	 = array();
				$collectData = NULL;

				// INFO: Eat attributes
				foreach ( $imprint->attributes() as $name => $value ) $attributes[ $name ] = (string) $value;

				// INFO: Simple contents
				if ( $imprint->count() <= 0 )
					$returnVal = (string) $imprint;
				else
				{
					$returnVal = $split = array();
					foreach ( $imprint as $property => $content )
					{
						$result = $ImprintFunc( $content );

						if ( !isset($returnVal[ $property ]) )
							$returnVal[ $property ] = $result;
						else
						if ( in_array( $property, $split ) )
							$returnVal[ $property ][] = $result;
						else
						{
							$split[] = $property;
							$returnVal[ $property ] = array( $returnVal[ $property ], $result );
						}
					}
				}



				if ( !is_array( $returnVal ) )
					$returnVal = ( empty($attributes) ) ? $returnVal : array( $returnVal, '@type' => 'simple', '@attr' => $attributes );
				else
				if ( !empty($attributes) )
					$returnVal[ '@attr' ] = $attributes;


				return $returnVal;
			};
		}

		$newsContents = simplexml_load_string( $xmlString );
		return $ImprintFunc( $newsContents );
	}
