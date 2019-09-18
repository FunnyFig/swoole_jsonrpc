<?php
namespace JsonRpc;

use JsonRpc\Base\Rpc;
use JsonRpc\Base\Response;

class RpcError extends \Exception {
	protected $data;

	public function __construct( $code
				   , $msg=null
				   , $data=null
				   , \Exception $prev=null)
	{
		parent::__construct($msg, $code, $prev);
		$this->data = $data;
// FIXME:
//		if (is_null($code) || empty($msg)) {
//			$error = Response::makeError(Rpc::ERR_SERVER);
//			$this->code = $error['code'];
//			$this->message = $error['message'];
//		}
	}

	final public function getRpcError()
	{
		$rv = new \stdClass();
		$rv->code = $this->getCode();
		$rv->message = $this->getMessage();
		$rv->data = $this->data;
		return $rv;
	}
}

