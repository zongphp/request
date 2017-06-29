<?php
namespace zongphp\request;
use zongphp\framework\build\Provider;

class RequestProvider extends Provider {
	//延迟加载
	public $defer = true;

	public function boot() {
		Request::get();
	}

	public function register() {
		$this->app->single( 'Request', function () {
			return Request::single();
		} );
	}
}