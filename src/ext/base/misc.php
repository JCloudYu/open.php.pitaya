<?php

	using('ext.base.math');

	define('KB', 	  1024.0, TRUE);	// KiloByte
	define('MB', KB * 1024.0, TRUE);	// MegaByte
	define('GB', MB * 1024.0, TRUE);	// GigaByte
	define('TB', GB * 1024.0, TRUE);	// TeraByte
	define('PB', TB * 1024.0, TRUE);	// PetaByte
	define('EB', PB * 1024.0, TRUE);	// ExaByte
	define('ZB', EB * 1024.0, TRUE);	// ZetaByte
	define('YB', ZB * 1024.0, TRUE);	// YotaByte


	function TO($value, $type)
	{
		if (is_array($type))
		{
			$criteria	= @$type['criteria'];
			$type		= @$type['type'];
			$default	= @$type['default'];
		}
		else
			$default = $criteria = NULL;



		$type = is_string($type) ? strtolower($type) : 'raw';

		switch($type)
		{
			case 'int':
				return EXPR_NUMERIC($value) ? intval($value) : 0;

			case 'int strict':
				return EXPR_INT($value) ? intval($value) : 0;

			case 'float':
				return EXPR_NUMERIC($value) ? floatval($value) : 0.0;

			case 'float strict':
				return EXPR_FLOAT($value) ? floatval($value) : 0.0;

			case 'string':
				return trim("$value");

			case 'boolean':
				return $value == TRUE;

			case 'null':
				return NULL;

			case 'time':
				$val = strtotime("{$value}");
				return ($val === FALSE || $val < 0) ? 0 : $val;

			case 'range':
				if ($criteria) $criteria = array();
				return (in_array($value, $criteria)) ? $value : $default;



			// INFO: Experimental Conversions
			case 'uint':
				if (!is_numeric($value)) return 0;
				return (float)sprintf('%u', $value);

			case 'raw':
			default:
				return $value;
		}
	}

	/**
	 * Decode the data according to the given encoding type
	 *
	 * @param mixed $data the data to be deocded
	 * @param string $encType the encoding type of the given data
	 *
	 * @return mixed the decoded data
	 */
	function iTrans($data, $encType)
	{
		switch ($encType)
		{
			case 'urlencoded':
				$data = urldecode($data);
				break;
			case 'base64':
				$data = base64_decode($data);
				break;
			default:
				break;
		}

		return $data;
	}

	/**
	 * Encode the data according to the given encoding type
	 *
	 * @param mixed $data the data to be encoded
	 * @param string $encType the encoding type of the given data
	 *
	 * @return mixed the encoded data
	 */
	function Trans($data, $encType)
	{
		switch ($encType)
		{
			case 'urlencoded':
				$data = urlencode($data);
				break;
			case 'base64':
				$data = base64_encode($data);
				break;
			default:
				break;
		}

		return $data;
	}

	/**
	 * Returns the global variables defined in target package
	 *
	 * @param string $____path_of_the_package_file_to_be_imprinted the targeted package to be imprinted
	 *
	 * @return array|null returns array if the package exists, null otherwise
	 */
	function Imprint($____path_of_the_package_file_to_be_imprinted = '')
	{
		$____pre_cached_to_be_deleted_existing_variables = get_defined_vars();

		if (!is_string($____path_of_the_package_file_to_be_imprinted) ||
			empty($____path_of_the_package_file_to_be_imprinted))
			return NULL;

		require package($____path_of_the_package_file_to_be_imprinted, TRUE);
		$____path_of_the_package_file_to_be_imprinted = get_defined_vars();

		foreach ($____pre_cached_to_be_deleted_existing_variables as $varName => $varValue)
			unset($____path_of_the_package_file_to_be_imprinted[$varName]);

		return $____path_of_the_package_file_to_be_imprinted;
	}

	/**
	 * Execute a php script and returns the output generated by the script
	 *
	 * @param string $____path_of_the_script_to_be_executed
	 * @param array $____parameters_used_in_the_executed_script
	 * @param array $____script_defined_variables
	 *
	 * @return string the output generated by the executed script
	 */
	function Script($____path_of_the_script_to_be_executed, $____parameters_used_in_the_executed_script = array(), &$____script_defined_variables = NULL)
	{
		$____pre_cached_to_be_deleted_existing_variables = get_defined_vars();

		if (!is_string($____path_of_the_script_to_be_executed) || empty($____path_of_the_script_to_be_executed))
			return '';


		ob_start();
		extract($____parameters_used_in_the_executed_script);
		require $____path_of_the_script_to_be_executed;
		$____output_buffer_generated_by_executed_script = ob_get_clean();
		$____variables_that_are_used_in_executed_script = get_defined_vars();



		foreach ($____pre_cached_to_be_deleted_existing_variables as $varName => $varValue)
			unset($____variables_that_are_used_in_executed_script[$varName]);

		foreach ($____parameters_used_in_the_executed_script as $varName => $varValue)
			unset($____variables_that_are_used_in_executed_script[$varName]);


		$____script_defined_variables = $____variables_that_are_used_in_executed_script;
		return $____output_buffer_generated_by_executed_script;
	}

	/**
	 * Execute a php script and directly display the output generated by the script
	 *
	 * @param string $____path_of_the_script_to_be_executed
	 * @param array $____parameters_used_in_the_executed_script
	 * @param array $____script_defined_variables
	 */
	function ScriptOut($____path_of_the_script_to_be_executed, $____parameters_used_in_the_executed_script = array(), &$____script_defined_variables = NULL)
	{
		$____pre_cached_to_be_deleted_existing_variables = get_defined_vars();

		if (!is_string($____path_of_the_script_to_be_executed) || empty($____path_of_the_script_to_be_executed)) return;


		extract($____parameters_used_in_the_executed_script);
		require $____path_of_the_script_to_be_executed;
		$____variables_that_are_used_in_executed_script = get_defined_vars();



		foreach ($____pre_cached_to_be_deleted_existing_variables as $varName => $varValue)
			unset($____variables_that_are_used_in_executed_script[$varName]);

		foreach ($____parameters_used_in_the_executed_script as $varName => $varValue)
			unset($____variables_that_are_used_in_executed_script[$varName]);


		$____script_defined_variables = $____variables_that_are_used_in_executed_script;
	}
