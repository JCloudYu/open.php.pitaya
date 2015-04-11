<?php
/**
 * 1027.BadmintonLa - DBCtrl.php
 * Created by JCloudYu on 2015/04/12 01:01
 */
	using('sys.db.ExtPDO');

	final class PBDBCtrl
	{
		/**
		 * Prepare and return a database connection singleton.
		 * This function will return NULL if there's no connection parameter given.
		 *
		 * @param null|array $param connection related parameter that is defined in env.ini
		 * @param array $option addition parameters for database linkage initialization
		 *
		 * @return ExtPDO|null
		 */
		public static function DB($param = NULL, $option = array('CREATE_VAR'))
		{
			static $__singleton_db = NULL;

			if ( $__singleton_db && !in_array('FORCE_CREATE', $option) )
				return $__singleton_db;

			if ($param)
			{
				if ( $__singleton_db )
				{
					unset($__singleton_db);
					$__singleton_db = NULL;
				}

				return ($__singleton_db = self::CONNECT( $param, $option ));
			}

			return NULL;
		}

		public static function CONNECT($param = NULL, $option = array('CREATE_VAR'))
		{
			$dsn = ExtPDO::DSN($param['host'], $param['db'], $param['port']);
			$connection = new ExtPDO($dsn, $param['account'], $param['password'], $option);
			$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $connection;
		}

		public static function LIMIT($SQL, $page = NULL, $pageSize = NULL, &$pageInfo = NULL)
		{
			if (is_array($SQL))
			{
				$sql	= $SQL['sql'];
				$param	= $SQL['param'];
			}
			else
			{
				$sql	= $SQL;
				$param	= array();
			}

			$sql = trim($sql);
			$sql = preg_replace('/(select)(.|[\n])*(from([^;]|[\n])*);*/i', "$1 count(*) as count $3", $sql, -1);
			$countResult = DB()->fetch($sql, $param);


			$totalCount = $countResult['count'];
			$page		= TO($page,		'int');
			$pageSize	= TO($pageSize,	'int');

			if (!empty($pageSize))
			{
				$totalPages = ceil((float)$totalCount/(float)$pageSize);
				$page = min(max($page, 1), max($totalPages, 1));

				$from = ($page - 1) * $pageSize;
				$limitClause = "{$from},{$pageSize}";
			}
			else
			{
				$totalPages = $page = 1;
				$pageSize = $totalCount;
				$limitClause = "1,{$totalCount}";
			}



			$pageInfo = array(
				'pageSize'		=> $pageSize,
				'page'			=> $page,
				'totalPages'	=> $totalPages,
				'totalRecords'	=> $totalCount
			);

			return $limitClause;
		}

		public static function SET($data, &$param = NULL, $varIndex = FALSE)
		{
			$param = $sql = array();
			foreach ($data as $col => $val)
			{
				if ($varIndex)
				{
					$stmt = "`{$col}` = :{$col}";
					$param[":{$col}"] = $val;
				}
				else
				{
					$stmt = "`{$col}` = ?";
					$param[] = $val;
				}

				$sql[] = $stmt;
			}
			return implode(', ', $sql);
		}

		public static function ORDER($orderOpt = array())
		{
			if (!is_array($orderOpt)) return '';

			$orderStmt = array();
			foreach ($orderOpt as $colName => $sequence)
			{
				$seq = (in_array(strtoupper("{$sequence}"), array('ASC', 'DESC'))) ? " $sequence" : "";
				$orderStmt[] = "`{$colName}`{$seq}";
			}
			$orderStmt = implode(', ', $orderStmt);

			if (empty($orderStmt)) return '';

			return $orderStmt;
		}

		/**
		 * @param PDOStatement $stmt PDOStatement object
		 * @param string $field Field name that is used. If empty values are provided,
		 *                      array with continuous numeric indices are returned (append mode).
		 *
		 * @return array collected results
		 */
		public static function FieldBasedDataCollector(PDOStatement $stmt, $field = 'id')
		{
			$result	= array();

			if ( !empty($field) )
				while ( ($row = $stmt->fetch()) !== FALSE ) $result[$row["{$field}"]] = $row;
			else
				while ( ($row = $stmt->fetch()) !== FALSE ) $result[] = $row;

			return $result;
		}

		/**
		 * @param PDOStatement $stmt PDOStatement object
		 * @param callable | Closure $filterFunc Function that is used to filter the retrieved data.[ array function(array) ]
		 * @param null $field Field name that is used. If empty values are provided,
		 *                    array with continuous numeric indices are returned (append mode).
		 *
		 * @return array collected results
		 */
		public static function FilterBasedDataCollector(PDOStatement $stmt, $filterFunc, $field = NULL)
		{
			$result	= array();

			while ( ($row = $stmt->fetch()) !== FALSE )
			{
				$filtered = $filterFunc($row);

				if ( !empty($field) )
					$result[$filtered["{$field}"]] = $filtered;
				else
					$result[] = $filtered;
			}

			return $result;
		}
	}