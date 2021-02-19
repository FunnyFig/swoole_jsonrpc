<?php

require_once 'vendor/autoload.php';

use JsonRpc\SwooleServer;

\Co\run(function() {
	$http = new \Co\Http\Server('127.0.0.1', 28888);

	$http->handle('/', function ($req, $res) {
		// swoole rawContent length problem workaround
		if ($req->server['request_method'] == 'POST') {
			$data = $req->getData();
			$cl = intval($req->header['content-length']);
			$req->post = substr($data, strlen($data) - $cl);

			echo "raw data: {$req->post}\n";

			RpcMethods::inst()->handle($req, $res);
		}
		else {
			$res->status(404);
			$res->end('Not Found');
		}
	});

	$http->start();
});

class RpcMethods {
	protected static $instance;
	protected $json_rpc;

	static function inst()
	{
		if (!self::$instance) {
			$self_class = self::class;
			self::$instance = new $self_class();
		}
		return self::$instance;
	}

	static function handle($req, $res)
	{
		$res->header('Content-Type', 'application/json; charset=utf-8');

		self::inst()->json_rpc->call(
			$req->rawContent(),
			function ($result) use($res) {
				$res->end($result);
			}
		);
	}

	protected function __construct()
	{
		$this->json_rpc = new SwooleServer($this);
	}

	function notify($args)
	{
		echo "notify\n";
		var_dump($args);
		return;
	}

	function foo($args)
	{
		echo "foo\n";
		var_dump($args);
		return ['method'=>'foo', 'args'=>$args];
	}

	function bar($args)
	{
		echo "bar\n";
		var_dump($args);
		return ['method'=>'bar', 'args'=>$args];
	}
}


