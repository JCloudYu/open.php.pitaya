<?php

using('kernel.core.PBModule');
using('kernel.http.PBHTTP');
class WEB extends PBModule
{
	protected $incomingRequest = NULL;

	public function prepare($moduleRequest) {

		$this->incomingRequest = PBHTTP::ParseRequest($moduleRequest);
	}

	public function exec($param) {


	}
}