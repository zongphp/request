<?php
namespace zongphp\request\build;

//请求处理
use zongphp\arr\Arr;
use zongphp\config\Config;
use zongphp\cookie\Cookie;
use zongphp\session\Session;

class Base {
	protected $items = [];
	/**
     * @var string URL地址
     */
    protected $url;
	/**
     * @var string 基础URL
     */
    protected $baseUrl;
	/**
     * @var array 当前路由信息
     */
    protected $routeInfo = [];
	
	//启动组件
	public function __construct() {
		defined( 'IS_CLI' ) or define( 'IS_CLI', PHP_SAPI == 'cli' );
		$this->items['POST']    = $_POST;
		$this->items['GET']     = $_GET;
		$this->items['REQUEST'] = $_REQUEST;
		$this->items['SERVER']  = $_SERVER;
		$this->items['GLOBALS'] = $GLOBALS;
		if ( ! IS_CLI ) {
			//post数据解析
			if ( empty( $_POST ) ) {
				$input = file_get_contents( 'php://input' );
				if ( $data = json_decode( $input, true ) ) {
					$this->items['POST'] = $data;
				} else {
					parse_str( $input, $post );
					if ( ! empty( $post ) ) {
						$this->items['POST'] = $post;
					}
				}
			}
			defined( 'NOW' ) or define( 'NOW', $_SERVER['REQUEST_TIME'] );
			defined( 'MICROTIME' ) or define( 'MICROTIME', $_SERVER['REQUEST_TIME_FLOAT'] );
			defined( 'IS_GET' ) or define( 'IS_GET', $_SERVER['REQUEST_METHOD'] == 'GET' );
			defined( 'IS_POST' ) or define( 'IS_POST', $_SERVER['REQUEST_METHOD'] == 'POST' || ! empty( $this->items['POST'] ) );
			defined( 'IS_DELETE' ) or define( 'IS_DELETE', $_SERVER['REQUEST_METHOD'] == 'DELETE' ? true : ( isset( $_POST['_method'] ) && $_POST['_method'] == 'DELETE' ) );
			defined( 'IS_PUT' ) or define( 'IS_PUT', $_SERVER['REQUEST_METHOD'] == 'PUT' ? true : ( isset( $_POST['_method'] ) && $_POST['_method'] == 'PUT' ) );
			defined( 'IS_AJAX' ) or define( 'IS_AJAX', $this->isAjax() );
			defined( 'IS_WECHAT' ) or define( 'IS_WECHAT', isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( $_SERVER['HTTP_USER_AGENT'], 'MicroMessenger' ) !== false );
			defined( 'IS_MOBILE' ) or define( 'IS_MOBILE', $this->isMobile() );
			defined( '__ROOT__' ) or define( '__ROOT__', PHP_SAPI == 'cli' ? '' : trim( 'http://' . $_SERVER['HTTP_HOST'] . dirname( $_SERVER['SCRIPT_NAME'] ), '/\\' ) );
			defined( '__WEB__' ) or define( '__WEB__', Config::get( 'http.rewrite' ) ? __ROOT__ : __ROOT__ . '/index.php' );
			defined( '__URL__' ) or define( '__URL__', trim( $this->httpType() . $_SERVER['HTTP_HOST'] . '/' . trim( $_SERVER['REQUEST_URI'], '/\\' ), '/' ) );
			defined( '__HISTORY__' ) or define( "__HISTORY__", isset( $_SERVER["HTTP_REFERER"] ) ? $_SERVER["HTTP_REFERER"] : '' );
		}
		$this->items['SESSION'] = Session::all();
		$this->items['COOKIE']  = Cookie::all();
	}

	/**
	 * 是否为异步提交
	 * @return bool
	 */
	public function isAjax() {
		return isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest';
	}

	/**
	 * 获取数据
	 *
	 * @param $name
	 * @param $value
	 * @param array $method
	 *
	 * @return null
	 */
	public function query( $name, $value = null, $method = [] ) {
		$exp = explode( '.', $name );
		if ( count( $exp ) == 1 ) {
			array_unshift( $exp, 'request' );
		}
		$action = array_shift( $exp );

		return $this->__call( $action, [ implode( '.', $exp ), $value, $method ] );
	}

	/**
	 * 设置值
	 *
	 * @param $name 类型如get.name,post.id
	 * @param $value
	 *
	 * @return bool
	 */
	public function set( $name, $value ) {
		$info   = explode( '.', $name );
		$action = strtoupper( array_shift( $info ) );
		if ( isset( $this->items[ $action ] ) ) {
			$this->items[ $action ] = Arr::set( $this->items[ $action ], implode( '.', $info ), $value );

			return true;
		}
	}

	/**
	 * 获取数据
	 * 示例: Request::get('name')
	 *
	 * @param $action 类型如get,post
	 * @param $arguments 参数结构如下
	 * [
	 *  'name'=>'变量名',//config.a 可选
	 *  'value'=>'默认值',//可选
	 *  'method'=>'回调函数',//数组类型 可选
	 * ]
	 *
	 * @return mixed
	 */
	public function __call( $action, $arguments ) {
		$action = strtoupper( $action );
		if ( empty( $arguments ) ) {
			return $this->items[ $action ];
		}
		$data = Arr::get( $this->items[ $action ], $arguments[0] );

		if ( ! is_null( $data ) && ! empty( $arguments[2] ) ) {
			return Tool::batchFunctions( $arguments[2], $data );
		}

		return ! is_null( $data ) ? $data : ( isset( $arguments[1] ) ? $arguments[1] : null );
	}

	//客户端IP
	public function ip( $type = 0 ) {
		$type = intval( $type );
		//保存客户端IP地址
		if ( isset( $_SERVER ) ) {
			if ( isset( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
				$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
			} else if ( isset( $_SERVER["HTTP_CLIENT_IP"] ) ) {
				$ip = $_SERVER["HTTP_CLIENT_IP"];
			} else if ( isset( $_SERVER["REMOTE_ADDR"] ) ) {
				$ip = $_SERVER["REMOTE_ADDR"];
			} else {
				return '';
			}
		} else {
			if ( getenv( "HTTP_X_FORWARDED_FOR" ) ) {
				$ip = getenv( "HTTP_X_FORWARDED_FOR" );
			} else if ( getenv( "HTTP_CLIENT_IP" ) ) {
				$ip = getenv( "HTTP_CLIENT_IP" );
			} else if ( getenv( "REMOTE_ADDR" ) ) {
				$ip = getenv( "REMOTE_ADDR" );
			} else {
				return '';
			}
		}
		$long     = ip2long( $ip );
		$clientIp = $long ? [ $ip, $long ] : [ "0.0.0.0", 0 ];

		return $clientIp[ $type ];
	}

	//判断请求来源是否为本网站域名
	public function isDomain() {
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = parse_url( $_SERVER['HTTP_REFERER'] );
			$root    = parse_url( __ROOT__ );

			return $referer['host'] == $root['host'];
		}

		return false;
	}

	//https请求
	public function isHttps() {
		if ( isset( $_SERVER['HTTPS'] ) && ( '1' == $_SERVER['HTTPS'] || 'on' == strtolower( $_SERVER['HTTPS'] ) ) ) {
			return true;
		} elseif ( isset( $_SERVER['SERVER_PORT'] ) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}

		return false;
	}
	
	//返回http类型
	public function httpType(){
		if($this->isHttps()){
			return 'https://';
		}else{
			return 'http://';
		}
	}
	
	    /**
     * 设置或获取当前完整URL 包括QUERY_STRING
     * @access public
     * @param string|true $url URL地址 true 带域名获取
     * @return string
     */
    public function url($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->url = $url;
            return $this;
        } elseif (!$this->url) {
            if (IS_CLI) {
                $this->url = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
                $this->url = $_SERVER['HTTP_X_REWRITE_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $this->url = $_SERVER['REQUEST_URI'];
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
                $this->url = $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
            } else {
                $this->url = '';
            }
        }
        return true === $url ? $this->domain() . $this->url : $this->url;
    }
	
	/**
     * 设置或获取当前URL 不含QUERY_STRING
     * @access public
     * @param string $url URL地址
     * @return string
     */
    public function baseUrl($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->baseUrl = $url;
            return $this;
        } elseif (!$this->baseUrl) {
            $str           = $this->url();
            $this->baseUrl = strpos($str, '?') ? strstr($str, '?', true) : $str;
        }
        return true === $url ? $this->domain() . $this->baseUrl : $this->baseUrl;
    }

    /**
     * 设置或获取当前包含协议的域名
     * @access public
     * @param string $domain 域名
     * @return string
     */
    public function domain($domain = null)
    {
        if (!is_null($domain)) {
            return $domain;
        } else {
            $domain = $this->scheme() . '://' . $this->host();
        }
        return $domain;
    }

        /**
     * 当前请求的host
     * @access public
     * @param bool $strict true 仅仅获取HOST
     * @return string
     */
    public function host($strict = false)
    {
        if (isset($_SERVER['HTTP_X_REAL_HOST'])) {
            $host = $_SERVER['HTTP_X_REAL_HOST'];
        } else {
            $host =$_SERVER['HTTP_HOST'];
        }

        return true === $strict && strpos($host, ':') ? strstr($host, ':', true) : $host;
    }

    /**
     * 当前URL地址中的scheme参数
     * @access public
     * @return string
     */
    public function scheme()
    {
        return $this->isHttps() ? 'https' : 'http';
    }
	
	/**
     * 设置或者获取当前的Header
     * @access public
     * @param string|array $name    header名称
     * @param string       $default 默认值
     * @return string
     */
    public function header($name = '', $default = null)
    {
        $header = [];
        if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
            $header = $result;
        } else {
            $server = $_SERVER;
            foreach ($server as $key => $val) {
                if (0 === strpos($key, 'HTTP_')) {
                    $key          = str_replace('_', '-', strtolower(substr($key, 5)));
                    $header[$key] = $val;
                }
            }
            if (isset($server['CONTENT_TYPE'])) {
                $header['content-type'] = $server['CONTENT_TYPE'];
            }
            if (isset($server['CONTENT_LENGTH'])) {
                $header['content-length'] = $server['CONTENT_LENGTH'];
            }
        }
        $header = array_change_key_case($header);

        if (is_array($name)) {
            return $header = array_merge($header, $name);
        }
        if ('' === $name) {
            return $header;
        }
        $name = str_replace('_', '-', strtolower($name));
        return isset($header[$name]) ? $header[$name] : $default;
    }
	
	/**
     * 获取当前请求的路由信息
     * @access public
     * @param array $route 路由名称
     * @return array
     */
    public function routeInfo()
    {
        $route = Route::getMatchRoute();
        
        $this->routeInfo = $route;

        return $this->routeInfo;
    }
	
	/**
     * 获取当前请求的路由规则
     * @access public
     * @param array $route 路由名称
     * @return array
     */
    public function routeRule()
    {
        $routeInfo = $this->routeInfo();
        if (!empty($routeInfo)) {
            $routeRule = explode('/', $routeInfo['route']);
            return $routeRule;
        } else {
            return [];
        }
    }
	
	 /**
     * 获取当前请求的时间
     * @access public
     * @param bool $float 是否使用浮点类型
     * @return integer|float
     */
    public function time($float = false)
    {
        return $float ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'];
    }

	//微信客户端检测
	public function isWeChat() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( $_SERVER['HTTP_USER_AGENT'], 'MicroMessenger' ) !== false;
	}

	//手机客户端判断
	public function isMobile() {
		//微信客户端检测
		if ( $this->isWeChat() ) {
			return true;
		}
		if ( ! empty( $_GET['_mobile'] ) ) {
			return true;
		}
		$_SERVER['ALL_HTTP'] = isset( $_SERVER['ALL_HTTP'] ) ? $_SERVER['ALL_HTTP'] : '';
		$mobile_browser      = '0';
		if ( preg_match( '/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower( $_SERVER['HTTP_USER_AGENT'] ) ) ) {
			$mobile_browser ++;
		}
		if ( ( isset( $_SERVER['HTTP_ACCEPT'] ) ) and ( strpos( strtolower( $_SERVER['HTTP_ACCEPT'] ), 'application/vnd.wap.xhtml+xml' ) !== false ) ) {
			$mobile_browser ++;
		}
		if ( isset( $_SERVER['HTTP_X_WAP_PROFILE'] ) ) {
			$mobile_browser ++;
		}
		if ( isset( $_SERVER['HTTP_PROFILE'] ) ) {
			$mobile_browser ++;
		}
		$mobile_ua     = strtolower( substr( $_SERVER['HTTP_USER_AGENT'], 0, 4 ) );
		$mobile_agents = [
			'w3c ',
			'acs-',
			'alav',
			'alca',
			'amoi',
			'audi',
			'avan',
			'benq',
			'bird',
			'blac',
			'blaz',
			'brew',
			'cell',
			'cldc',
			'cmd-',
			'dang',
			'doco',
			'eric',
			'hipt',
			'inno',
			'ipaq',
			'java',
			'jigs',
			'kddi',
			'keji',
			'leno',
			'lg-c',
			'lg-d',
			'lg-g',
			'lge-',
			'maui',
			'maxo',
			'midp',
			'mits',
			'mmef',
			'mobi',
			'mot-',
			'moto',
			'mwbp',
			'nec-',
			'newt',
			'noki',
			'oper',
			'palm',
			'pana',
			'pant',
			'phil',
			'play',
			'port',
			'prox',
			'qwap',
			'sage',
			'sams',
			'sany',
			'sch-',
			'sec-',
			'send',
			'seri',
			'sgh-',
			'shar',
			'sie-',
			'siem',
			'smal',
			'smar',
			'sony',
			'sph-',
			'symb',
			't-mo',
			'teli',
			'tim-',
			'tosh',
			'tsm-',
			'upg1',
			'upsi',
			'vk-v',
			'voda',
			'wap-',
			'wapa',
			'wapi',
			'wapp',
			'wapr',
			'webc',
			'winw',
			'winw',
			'xda',
			'xda-',
		];
		if ( in_array( $mobile_ua, $mobile_agents ) ) {
			$mobile_browser ++;
		}
		if ( strpos( strtolower( $_SERVER['ALL_HTTP'] ), 'operamini' ) !== false ) {
			$mobile_browser ++;
		}
		// Pre-final check to reset everything if the user is on Windows
		if ( strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'windows' ) !== false ) {
			$mobile_browser = 0;
		}
		// But WP7 is also Windows, with a slightly different characteristic
		if ( strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'windows phone' ) !== false ) {
			$mobile_browser ++;
		}
		if ( $mobile_browser > 0 ) {
			return true;
		} else {
			return false;
		}
	}
}