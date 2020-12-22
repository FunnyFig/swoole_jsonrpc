<?php
namespace JsonRpc;

use JsonRpc\Base\Rpc;
use JsonRpc\Base\Request;
use JsonRpc\Base\Response;


// implementer is responsible for
// throw new RpcError(Rpc::ERR_PARAMS);
abstract class MethodsBase {
	public function __call($method, $args)
	{
		throw new RpcError(Rpc::ERR_METHOD);
	}
}


// we have to limit request size and ban the user tried to exceed it.
// # of params...
class SwooleServer {
	protected $handler;
	protected $logger;

	public function __construct($methodHandler)
	{
		if (!is_object($methodHandler)) {
			throw new \RuntimeException('Invalid argument');
		}

		$this->handler = $methodHandler;
	}

	public function call(string $json, callable $cb)
	{
		go(function() use($json, $cb) {
			try {
				// throw Error when failed
				// throw new RpcError
				$struct = self::parse($json);

				$n_res = 0;
				foreach ($struct as $item) {
					$r = new Request($item);
					$reqs[] = $r;
					$n_res += !$r->notification;
				}

				if (count($reqs) == 1) {
					// nothrow
					return $cb($this->invoke($reqs[0]));
				}

				$chan = $n_res>0? new chan($n_res) : null;
				foreach ($reqs as $req) {
					// nothrow
					$this->invoke($req
						, function($res) use($chan) {
							$chan->push($res);
						});
				}

				if ($n_res) {
					$resps = array_map(function ($i) use($chan) {
						return $chan->pop();
					}, range(0, $n_res-1));

					$cb("[$implode('.', $resps)]");
				}
			}
			// TODO: ID needed
			catch (RpcError $e) {
				// error code should be one of parse or request
				// because we don't have id
				// id is javascript null
				// see https://www.jsonrpc.org/specification#id1
				$cb($this->createResponse(null, $e->getRpcError(), false));
			}
			catch (\Throwable $t) {
				// TODO call callback
				echo "$t\n";
			}
		});
	}

	protected function invoke($req, $cb=null)
	{
		try {
			if ($req->fault) {
				throw new RpcError(Rpc::ERR_REQUEST);
			}

			// this can not catch all undefined methods
			// if methods class implements __call()
			if (!$method = $this->getMethod($req->method)) {
				throw new RpcError(Rpc::ERR_METHOD);
			}

			if (!$cb) {
				// about void function
				//https://groups.google.com/forum/#!topic/json-rpc/esusPURMBu8
				$rv = $this->_invoke($method, $req->params);
				return $this->createResponse($req->id, $rv);
			}

			// batch
			go(function () use($req, $method, $cb) {
				// we can not use outter exception handler
				try {
					// convert
					$rv = $this->_invoke($method, $req->params);
					$cb($this->createResponse($req->id, $rv));
				}
				catch (RpcError $e) {
					if (!$req->notification) {
						$id = $e->getCode() != Rpc::ERR_REQUEST? $req->id : null;
						$rv = $this->createResponse($id, $e->getRpcError(), false);
						return $cb($rv);
					}
				}
				catch (\Throwable $e) {
					if (!$req->notification) {
						$rv = $this->createResponse($req->id, Rpc::ERR_INTERNAL, false);
						return $cb($rv);
					}
				}
			});
			
		}
		catch (RpcError $e) {
			// request error in notification request
			// https://stackoverflow.com/questions/31091376/json-rpc-2-0-allow-notifications-to-have-an-error-response
			if (!$req->notification) {
				$id = $e->getCode() != Rpc::ERR_REQUEST? $req->id : null;
				$rv = $this->createResponse($id, $e->getRpcError(), false);
				return $cb? $cb($rv) : $rv;
			}
		}
		catch (\Throwable $t) {
			if (!$req->notification) {
				$rv = $this->createResponse($req->id, Rpc::ERR_INTERNAL, false);
				return $cb? $cb($rv) : $rv;
			}
		}
	}

	protected function _invoke($method, $params)
	{
		try {
			// we pass params as is.
			if (!is_array($params)) $params = [$params];
			//var_dump($params);

			$rv = call_user_func_array($method, $params);
			//echo "rv is ..\n";
			//var_dump($rv);
			return $rv;
		}
		catch (RpcError $e) {
			throw $e;
		}
		catch (\ArgumentCountError $e) {
			// too few params
			throw new RpcError(Rpc::ERR_PARAMS);
		}
		catch (\Throwable $t) {
			throw $t;
		}
	}

	protected static function parse($json)
	{
		$rv = Rpc::decode($json, $batch);
		if ($rv) {
			return is_array($rv)? $rv: [$rv];
		}

		throw new RpcError( is_null($rv)?
		       	Rpc::ERR_PARSE : Ppc::ERR_REQUEST);
	}

	protected function createResponse($id, $result, $noerror=true)
	{
		$ar = ['id' => $id];
		$ar[$noerror? 'result': 'error'] = $result;
		$rv = new Response();

		if (!$rv->create($ar)) {
			$this->logError($rv->fault);
			$rv->createStdError(Rpc::ERR_INTERNAL, $id);
		}

		return $rv->toJson();
	}

	protected function logError($message)
	{
		echo "$message\n";
	}

	protected function getMethod($method)
	{
		// already checked by Rpc::check()
		// if (empty($mthod)) return null;
		$rv = [$this->handler, $method];
		return is_callable($rv)? $rv : null;
	}
}

