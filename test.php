<?php

require_once 'vendor/autoload.php';

use JsonRpc\Base\Rpc;
use JsonRpc\Base\Response;
use JsonRpc\RpcError;
use JsonRpc\SwooleServer as RpcServer;

//class Methods {
//	function foo() {}
//}
//
//$rcp = new JsonRpc\Server(new Methods());

////$e = new Error(null);
//$e = new Error(Rpc::ERR_PARSE);
//
//$r = new Response();
//$r->create(['error' => $e->getRpcError(), 'id'=>null]);
//var_dump($r);
//
//echo $r->toJson()."\n";


use Swoole\Http\server as HttpServer;
use Swoole\Http\Request as HttpRequest;
use Swoole\Http\Response as HttpResponse;

use FastRoute\RouteCollector;

// https://github.com/james2doyle/swoole-examples/blob/master/router.php
class dispatcher_t {
	private $dispatcher;
	private static $err_handlers = [];
	public function __construct($dispatcher)
	{
		$this->dispatcher = $dispatcher;
		if (!count(self::$err_handlers)) {
			self::$err_handlers[FastRoute\Dispatcher::NOT_FOUND]
				= function ($route, $req, $res) {
					$res->status(404);
					//return 'Not Found';
					$res->end('Not Found');
				};
			self::$err_handlers[FastRoute\Dispatcher::FOUND]
				= null;
			self::$err_handlers[FastRoute\Dispatcher::METHOD_NOT_ALLOWED]
				= function ($route, $req, $res) {
					$res->status(405);
					$res->header('Allow', join(', ', $route[1]));
					//return 'Method Not Allowed';
					$res->end('Method Not Allowed');
				};
		}
	}

	public function handle($req, $res)
	{
		$verb = $req->server['request_method'];
		$uri = $req->server['request_uri'];

		$route = $this->dispatcher->dispatch($verb, $uri);
		if ($route[0] != FastRoute\Dispatcher::FOUND) {
			return self::err_handlers[$route[0]]($route, $req, $res);
		}

		// call installed handler
		// return $route[1]($route[2], $req, $res);
		$route[1]($route[2], $req, $res);
	}
}

// curl -i -X POST localhost:3000/api?a=b -d '{"jsonrpc":"2.0", "method":"foo", "params":["abc", "efg"], "id":1}' -H 'Content-Type: application/json'

class Methods {
	function foo($p0, $p1)
	{
		$rv = new \stdClass();
		$rv->first = $p0;
		$rv->second = $p1;
		return $rv; 
	}

	function __call($name, $args)
	{
		throw new RpcError(Rpc::ERR_METHOD);
	}
}

$rpc = new RpcServer(new Methods());

// Request Header
// Authorization: Bearer client_id:hs256(access_token, nonce + body)
// api-expiry: nonce (Unix time)
function api_handler(array $vars, $req, $res)
{
	//global $transport;
	//return $transport->handle($vars, $req, $res);

	global $rpc;
	$rpc->call($req->rawContent(), function($r) use($res) {
		if ($r) {
			$res->header('Content-Type', 'application/json');
		}
		$res->end($r);
	});
}

$dispatcher = new dispatcher_t(FastRoute\simpleDispatcher(function (RouteCollector $r) {
	$r->addRoute('POST', '/api', 'api_handler');
}));

Swoole\Runtime::enableCoroutine();

// https://www.swoole.co.uk/
$http = new HttpServer('localhost', 3000);

$http->on('start', function (HttpServer $server) {
	echo "Server is started\n";
});

$http->on('request', function (HttpRequest $req, HttpResponse $res) use ($dispatcher) {
	$verb = $req->server['request_method'];
	$uri = $req->server['request_uri'];

	$_SERVER['REQUEST_URI'] = $uri;
	$_SERVER['REQUEST_METHOD'] = $verb;
	$_SERVER['REMOTE_ADDR'] = $req->server['remote_addr'];

	$_GET = $req->get ?? [];
	$_FILES = $req->files ?? [];

	$content_type = $req->header['content-type']?? '';
	if ($verb === 'POST' && $content_type === 'application/json') {
		$body = $req->rawContent();
		$_POST = empty($body)? []: json_decode($body);
	}
	else {
		$_POST = $req->post ?? [];
	}

	//$res->header('Content-Type', 'application/json');
	// TODO: this should properly handled
	//$result = $dispatcher->handle($verb, $uri);
	//$result = $dispatcher->handle($req, $res);

	//$res->end(json_encode($result));
	//$res->end($result);

	$result = $dispatcher->handle($req, $res);
});

$http->start();
