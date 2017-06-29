<?php
namespace zongphp\request;
use zongphp\framework\build\Facade;

class RequestFacade extends Facade {
	public static function getFacadeAccessor() {
		return 'Request';
	}
}