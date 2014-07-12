<?php
/**
 * 1003.IMSIS - file.php
 * Created by JCloudYu on 2014/07/08 18:59
 */ 
	using('kernel.core.PBModule');
	using('sys.tool.PBStream');

	class PBFileRequest extends PBModule
	{
		private $_targetPath	= '';
		private $_acceptableExt	= '';

		public function __construct() {
			$this->_acceptableExt = json_decode(file_get_contents(path("modules.PBFileRequest", "acceptableExts.json")), TRUE);
		}

		private $_relPath		= '';
		public function __set_relPath($value) { $this->_relPath = "{$value}"; }
		public function __get_relPath() { return $this->_relPath; }

		private $_multiByteRangeMode = FALSE;
		public function __set_multiBytes($value) { $this->_multiByteRangeMode = !empty($value); }
		public function __get_multiBytes() { return $this->_multiByteRangeMode; }

		private $_allowRagneRequest = FALSE;
		public function __set_allowRangeRequest($value) { $this->_allowRagneRequest = !empty($value); }
		public function __get_allowRangeRequest() { return $this->_allowRagneRequest; }



		public function prepare($moduleRequest) {
			$this->_targetPath = (is_array($moduleRequest)) ? implode('/', $moduleRequest) : "{$moduleRequest}";
		}

		public function exec()
		{
			$CONSTANT = PBConstant::Constant();

			$filePath = (empty($this->_relPath) ? "{$CONSTANT['__WORKING_ROOT__']}/{$this->_targetPath}" : "{$this->_relPath}/{$this->_targetPath}");
			$ext = @strtoupper(pathinfo($filePath, PATHINFO_EXTENSION));

			if (!is_readable($filePath) || !in_array($ext, array_keys($this->_acceptableExt)))
			{
				header('HTTP/1.1 404 Not Found');
				exit(0);
			}



			// INFO: Get file info and http range info
			$ranges	  = PBRequest::Request()->range;



			// INFO: Normal mode
			if (empty($ranges) || !$this->_allowRagneRequest)
			{
				$fileStream	= fopen($filePath, "rb");
				$outStream	= fopen("php://output", "wb");
				$fileSize = filesize($filePath);

				if (empty($fileStream))
				{
					header("HTTP/1.1 429 Too Many Requests");
					exit(0);
				}

				header("HTTP/1.1 200 OK");
				header("Content-Type: {$this->_acceptableExt[$ext]}");
				header("Content-Length: {$fileSize}");

				PBStream::ChunkStream($outStream, $fileStream, array('from' => 0, 'to' => $fileSize-1));

				fclose($fileStream);
				fclose($outStream);

				exit(0);
			}

			if ($this->_multiByteRangeMode)
				$this->multiRanges($filePath, $ranges);
			else
				$this->singleRange($filePath, $ranges);

			exit(0);
		}

		public function multiRanges($filePath, $ranges)
		{
			$fileSize = filesize($filePath);
			$ext = @strtoupper(pathinfo($filePath, PATHINFO_EXTENSION));
			$boundaryToken	= '--pb-' . sha1(uniqid('', TRUE));
			$rangeSize = 0;

			foreach ($ranges as $idx => $range)
			{
				$from = $range['from']; $to = $range['to'];
				$notValid = FALSE;

				// Check nullness and convert null values
				if ($from === NULL && $to === NULL)
					$notValid = $notValid || TRUE;
				else
				{
					if ($from === NULL)
					{
						$from = $fileSize - $to;
						$to = $fileSize - 1;
					}

					if ($to === NULL)   $to   = $fileSize - 1;

					// Validate other conditions
					if ($from < 0 || $to < 0) $notValid = $notValid || TRUE;
					if ($to < $from) $notValid = $notValid || TRUE;
					if ($from >= $fileSize || $to >= $fileSize) $notValid = $notValid || TRUE;
				}

				if ($notValid)
				{
					header("HTTP/1.1 416 Request Range Not Satisfiable");
					exit(0);
				}

				$ranges[$idx] = array('from' => $from, 'to' => $to);
				$ragneSize += strlen(CRLF . "--{$boundaryToken}" . CRLF);
				$ragneSize += strlen("Content-Type: {$this->_acceptableExt[$ext]}" . CRLF);
				$rangeSize += strlen("Content-Range: bytes {$range['from']}-{$range['to']}/{$fileSize}" . CRLF . CRLF);

				$rangeSize += ($to - $from) + 1;
			}
			$rangeSize += strlen(CRLF . "--{$boundaryToken}--" . CRLF);


			$fileStream		= fopen($filePath, "rb");
			$outStream		= fopen("php://output", "wb");

			if (empty($fileStream))
			{
				header("HTTP/1.1 429 Too Many Requests");
				exit(0);
			}


			header('HTTP/1.1 206 Partial Content');
			header("Accept-Ranges: bytes");
			header("Content-Type: multipart/byteranges; boundary={$boundaryToken}");
			header("Content-Length: {$rangeSize}");


			foreach ($ranges as $range)
			{
				echo CRLF . "--{$boundaryToken}" . CRLF;

				echo "Content-Type: {$this->_acceptableExt[$ext]}" . CRLF;
				echo "Content-Range: bytes {$range['from']}-{$range['to']}/{$fileSize}" . CRLF . CRLF;

				PBStream::ChunkStream($outStream, $fileStream, $range);

			}
			echo CRLF . "--{$boundaryToken}--" . CRLF;

			fclose($fileStream);
			fclose($outStream);
		}

		public function singleRange($filePath, $ranges)
		{
			$fileSize = filesize($filePath);
			$ext = @strtoupper(pathinfo($filePath, PATHINFO_EXTENSION));
			$endByte  = $fileSize - 1;


			$range = array_shift($ranges);
			$from = $range['from']; $to = $range['to'];
			$notValid = FALSE;

			// Check nullness and convert null values
			if ($from === NULL && $to === NULL)
				$notValid = $notValid || TRUE;
			else
			{
				if ($from === NULL)
				{
					$from = $fileSize - $to;
					$to = $fileSize - 1;
				}

				if ($to === NULL)   $to   = $fileSize - 1;

				// Validate other conditions
				if ($from < 0 || $to < 0) $notValid = $notValid || TRUE;
				if ($to < $from) $notValid = $notValid || TRUE;
				if ($from >= $fileSize || $to >= $fileSize) $notValid = $notValid || TRUE;
			}

			if ($notValid)
			{
				header("HTTP/1.1 416 Request Range Not Satisfiable");
				exit(0);
			}

			$range 		= array('from' => $from, 'to' => $to);
			$rangeSize  = ($to - $from) + 1;



			$fileStream	= fopen($filePath, "rb");
			$outStream  = fopen("php://output", "wb");

			if (empty($fileStream))
			{
				header("HTTP/1.1 429 Too Many Requests");
				exit(0);
			}


			header('HTTP/1.1 206 Partial Content');
			header("Accept-Ranges: 0-{$endByte}");
			header("Content-Type: {$this->_acceptableExt[$ext]}");
			header("Content-Length: {$rangeSize}");
			header("Content-Range: bytes {$range['from']}-{$range['to']}/{$fileSize}");


			PBStream::ChunkStream($outStream, $fileStream, $range);

			fclose($fileStream);
			fclose($outStream);
		}
	}