<?php
namespace zongphp\request\build;

//请求处理
use zongphp\arr\Arr;
use zongphp\config\Config;
use zongphp\cookie\Cookie;
use zongphp\session\Session;
use app\base\system\File;

class Base {
	protected $items = [];

	/**
     * 配置参数
     * @var array
     */
    protected $config = [
        // 表单请求类型伪装变量
        'var_method'       => '_method',
        // 表单ajax伪装变量
        'var_ajax'         => '_ajax',
        // 表单pjax伪装变量
        'var_pjax'         => '_pjax',
        // PATHINFO变量名 用于兼容模式
        'var_pathinfo'     => 's',
        // 兼容PATH_INFO获取
        'pathinfo_fetch'   => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter'   => '',
        // 域名根，如zongphp.com
        'url_domain_root'  => '',
        // HTTPS代理标识
        'https_agent_name' => '',
        // IP代理获取标识
        'http_agent_ip'    => 'HTTP_X_REAL_IP',
        // URL伪静态后缀
        'url_html_suffix'  => 'html',
    ];
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
	
	/**
     * 当前FILE参数
     * @var array
     */
    protected $file = [];
	
	/**
     * 当前请求参数
     * @var array
     */
	protected $param = [];
	
	/**
     * 当前GET参数
     * @var array
     */
    protected $get = [];

    /**
     * 当前POST参数
     * @var array
     */
	protected $post = [];
	
	/**
     * 当前ROUTE参数
     * @var array
     */
    protected $route = [];

	/**
     * 请求类型
     * @var string
     */
	protected $method;
	
	/**
     * php://input内容
     * @var string
     */
	protected $input;

	/**
     * 资源类型定义
     * @var array
     */
    protected $mimeType = [
        'xml'   => 'application/xml,text/xml,application/x-xml',
        'json'  => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js'    => 'text/javascript,application/javascript,application/x-javascript',
        'css'   => 'text/css',
        'rss'   => 'application/rss+xml',
        'yaml'  => 'application/x-yaml,text/yaml',
        'atom'  => 'application/atom+xml',
        'pdf'   => 'application/pdf',
        'text'  => 'text/plain',
        'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
        'csv'   => 'text/csv',
        'html'  => 'text/html,application/xhtml+xml,*/*',
	];
	
	/**
     * 当前请求内容
     * @var string
     */
	protected $content;
	
	/**
     * 全局过滤规则
     * @var array
     */
    protected $filter;
    
    /**
     * 当前执行的文件
     * @var string
     */
    protected $baseFile;

	/**
     * 是否合并Param
     * @var bool
     */
    protected $mergeParam = false;
	
	//启动组件
	public function __construct(array $options = []) {
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

		$this->init($options);

        // 保存 php://input
        $this->input = file_get_contents('php://input');
	}

	public function init(array $options = [])
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }
    }

	/**
     * 当前是否Ajax请求
     * @access public
     * @param  bool $ajax  true 获取原始ajax请求
     * @return bool
     */
    public function isAjax($ajax = false)
    {
        $value  = $this->server('HTTP_X_REQUESTED_WITH');
        $result = 'xmlhttprequest' == strtolower($value) ? true : false;

        if (true === $ajax) {
            return $result;
        }

        $result           = $this->param($this->config['var_ajax']) ? true : $result;
        $this->mergeParam = false;
        return $result;
    }

    /**
     * 当前是否Pjax请求
     * @access public
     * @param  bool $pjax  true 获取原始pjax请求
     * @return bool
     */
    public function isPjax($pjax = false)
    {
        $result = !is_null($this->server('HTTP_X_PJAX')) ? true : false;

        if (true === $pjax) {
            return $result;
        }

        $result           = $this->param($this->config['var_pjax']) ? true : $result;
        $this->mergeParam = false;
        return $result;
    }

    /**
     * 是否为GET请求
     * @access public
     * @return bool
     */
    public function isGet()
    {
        return $this->method() == 'GET';
    }

    /**
     * 是否为POST请求
     * @access public
     * @return bool
     */
    public function isPost()
    {
        return $this->method() == 'POST';
    }

    /**
     * 是否为PUT请求
     * @access public
     * @return bool
     */
    public function isPut()
    {
        return $this->method() == 'PUT';
    }

    /**
     * 是否为DELTE请求
     * @access public
     * @return bool
     */
    public function isDelete()
    {
        return $this->method() == 'DELETE';
    }

    /**
     * 是否为HEAD请求
     * @access public
     * @return bool
     */
    public function isHead()
    {
        return $this->method() == 'HEAD';
    }

    /**
     * 是否为PATCH请求
     * @access public
     * @return bool
     */
    public function isPatch()
    {
        return $this->method() == 'PATCH';
    }

    /**
     * 是否为OPTIONS请求
     * @access public
     * @return bool
     */
    public function isOptions()
    {
        return $this->method() == 'OPTIONS';
    }

    /**
     * 是否为cli
     * @access public
     * @return bool
     */
    public function isCli()
    {
        return PHP_SAPI == 'cli';
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

	    /**
     * 获取客户端IP地址
     * @access public
     * @param  integer   $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param  boolean   $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public function ip($type = 0, $adv = true)
    {
        $type      = $type ? 1 : 0;
        static $ip = null;

        if (null !== $ip) {
            return $ip[$type];
        }

        $httpAgentIp = $this->config['http_agent_ip'];

        if ($httpAgentIp && $this->server($httpAgentIp)) {
            $ip = $this->server($httpAgentIp);
        } elseif ($adv) {
            if ($this->server('HTTP_X_FORWARDED_FOR')) {
                $arr = explode(',', $this->server('HTTP_X_FORWARDED_FOR'));
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim(current($arr));
            } elseif ($this->server('HTTP_CLIENT_IP')) {
                $ip = $this->server('HTTP_CLIENT_IP');
            } elseif ($this->server('REMOTE_ADDR')) {
                $ip = $this->server('REMOTE_ADDR');
            }
        } elseif ($this->server('REMOTE_ADDR')) {
            $ip = $this->server('REMOTE_ADDR');
        }

        // IP地址类型
        $ip_mode = (strpos($ip, ':') === false) ? 'ipv4' : 'ipv6';

        // IP地址合法验证
        if (filter_var($ip, FILTER_VALIDATE_IP) !== $ip) {
            $ip = ('ipv4' === $ip_mode) ? '0.0.0.0' : '::';
        }

        // 如果是ipv4地址，则直接使用ip2long返回int类型ip；如果是ipv6地址，暂时不支持，直接返回0
        $long_ip = ('ipv4' === $ip_mode) ? sprintf("%u", ip2long($ip)) : 0;

        $ip = [$ip, $long_ip];

        return $ip[$type];
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
        return $this->isSsl() ? 'https' : 'http';
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
     * 设置或获取当前执行的文件 SCRIPT_NAME
     * @access public
     * @param  bool     $domain 是否包含域名
     * @return string|$this
     */
    public function baseFile($domain = false)
    {
        if (!$this->baseFile) {
            $url = '';
            if (!$this->isCli()) {
                $script_name = basename($this->server('SCRIPT_FILENAME'));
                if (basename($this->server('SCRIPT_NAME')) === $script_name) {
                    $url = $this->server('SCRIPT_NAME');
                } elseif (basename($this->server('PHP_SELF')) === $script_name) {
                    $url = $this->server('PHP_SELF');
                } elseif (basename($this->server('ORIG_SCRIPT_NAME')) === $script_name) {
                    $url = $this->server('ORIG_SCRIPT_NAME');
                } elseif (($pos = strpos($this->server('PHP_SELF'), '/' . $script_name)) !== false) {
                    $url = substr($this->server('SCRIPT_NAME'), 0, $pos) . '/' . $script_name;
                } elseif ($this->server('DOCUMENT_ROOT') && strpos($this->server('SCRIPT_FILENAME'), $this->server('DOCUMENT_ROOT')) === 0) {
                    $url = str_replace('\\', '/', str_replace($this->server('DOCUMENT_ROOT'), '', $this->server('SCRIPT_FILENAME')));
                }
            }
            $this->baseFile = $url;
        }

        return $domain ? $this->domain() . $this->baseFile : $this->baseFile;
    }

	/**
     * 设置或者获取当前请求的content
     * @access public
     * @return string
     */
    public function getContent()
    {
        if (is_null($this->content)) {
            $this->content = $this->input;
        }

        return $this->content;
    }

    /**
     * 获取当前请求的php://input
     * @access public
     * @return string
     */
    public function getInput()
    {
        return $this->input;
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
	
	/**
     * 获取server参数
     * @access public
     * @param  string        $name 数据名称
     * @param  string        $default 默认值
     * @return mixed
     */
    public function server($name = '', $default = null)
    {
        if (empty($name)) {
            return $_SERVER;
        } else {
            $name = strtoupper($name);
        }

        return isset($_SERVER[$name]) ? $_SERVER[$name] : $default;
	}
	
	/**
     * 递归重置数组指针
     * @access public
     * @param array $data 数据源
     * @return void
     */
    public function arrayReset(array &$data)
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->arrayReset($value);
            }
        }
        reset($data);
	}
	
	/**
     * 获取变量 支持过滤和默认值
     * @access public
     * @param  array         $data 数据源
     * @param  string|false  $name 字段名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤函数
     * @return mixed
     */
    public function input($data = [], $name = '', $default = null, $filter = '')
    {
        if (false === $name) {
            // 获取原始数据
            return $data;
        }

        $name = (string) $name;
        if ('' != $name) {
            // 解析name
            if (strpos($name, '/')) {
                list($name, $type) = explode('/', $name);
            }

            $data = $this->getData($data, $name);

            if (is_null($data)) {
                return $default;
            }

            if (is_object($data)) {
                return $data;
            }
        }

        // 解析过滤器
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            if (version_compare(PHP_VERSION, '7.1.0', '<')) {
                // 恢复PHP版本低于 7.1 时 array_walk_recursive 中消耗的内部指针
                $this->arrayReset($data);
            }
        } else {
            $this->filterValue($data, $name, $filter);
        }

        if (isset($type) && $data !== $default) {
            // 强制类型转换
            $this->typeCast($data, $type);
        }

        return $data;
    }

    /**
     * 获取数据
     * @access public
     * @param  array         $data 数据源
     * @param  string|false  $name 字段名
     * @return mixed
     */
    protected function getData(array $data, $name)
    {
        foreach (explode('.', $name) as $val) {
            if (isset($data[$val])) {
                $data = $data[$val];
            } else {
                return;
            }
        }

        return $data;
	}
	
	/**
     * 设置或获取当前的过滤规则
     * @access public
     * @param  mixed $filter 过滤规则
     * @return mixed
     */
    public function filter($filter = null)
    {
        if (is_null($filter)) {
            return $this->filter;
        }

        $this->filter = $filter;
    }

    protected function getFilter($filter, $default)
    {
        if (is_null($filter)) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && false === strpos($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array) $filter;
            }
        }

        $filter[] = $default;

        return $filter;
    }

    /**
     * 递归过滤给定的值
     * @access public
     * @param  mixed     $value 键值
     * @param  mixed     $key 键名
     * @param  array     $filters 过滤方法+默认值
     * @return mixed
     */
    private function filterValue(&$value, $key, $filters)
    {
        $default = array_pop($filters);

        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                // 调用函数或者方法过滤
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (false !== strpos($filter, '/')) {
                    // 正则过滤
                    if (!preg_match($filter, $value)) {
                        // 匹配不成功返回默认值
                        $value = $default;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter函数不存在时, 则使用filter_var进行过滤
                    // filter为非整形值时, 调用filter_id取得过滤id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = $default;
                        break;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * 强制类型转换
     * @access public
     * @param  string $data
     * @param  string $type
     * @return mixed
     */
    private function typeCast(&$data, $type)
    {
        switch (strtolower($type)) {
            // 数组
            case 'a':
                $data = (array) $data;
                break;
            // 数字
            case 'd':
                $data = (int) $data;
                break;
            // 浮点
            case 'f':
                $data = (float) $data;
                break;
            // 布尔
            case 'b':
                $data = (boolean) $data;
                break;
            // 字符串
            case 's':
                if (is_scalar($data)) {
                    $data = (string) $data;
                } else {
                    throw new \InvalidArgumentException('variable type error：' . gettype($data));
                }
                break;
        }
	}

	/**
     * 是否存在某个请求参数
     * @access public
     * @param  string    $name 变量名
     * @param  string    $type 变量类型
     * @param  bool      $checkEmpty 是否检测空值
     * @return mixed
     */
    public function has($name, $type = 'param', $checkEmpty = false)
    {
        if (!in_array($type, ['param', 'get', 'post', 'request', 'put', 'patch', 'file', 'session', 'cookie', 'env', 'header', 'route'])) {
            return false;
        }

        if (empty($this->$type)) {
            $param = $this->$type();
        } else {
            $param = $this->$type;
        }

        // 按.拆分成多维数组进行判断
        foreach (explode('.', $name) as $val) {
            if (isset($param[$val])) {
                $param = $param[$val];
            } else {
                return false;
            }
        }

        return ($checkEmpty && '' === $param) ? false : true;
    }
	
	/**
     * 当前请求的资源类型
     * @access public
     * @return false|string
     */
    public function type()
    {
        $accept = $this->server('HTTP_ACCEPT');

        if (empty($accept)) {
            return false;
        }

        foreach ($this->mimeType as $key => $val) {
            $array = explode(',', $val);
            foreach ($array as $k => $v) {
                if (stristr($accept, $v)) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * 设置资源类型
     * @access public
     * @param  string|array  $type 资源类型名
     * @param  string        $val 资源类型
     * @return void
     */
    public function mimeType($type, $val = '')
    {
        if (is_array($type)) {
            $this->mimeType = array_merge($this->mimeType, $type);
        } else {
            $this->mimeType[$type] = $val;
        }
	}
	
	/**
     * 当前的请求类型
     * @access public
     * @param  bool $origin  是否获取原始请求类型
     * @return string
     */
    public function method($origin = false)
    {
        if ($origin) {
            // 获取原始请求类型
            return $this->server('REQUEST_METHOD') ?: 'GET';
        } elseif (!$this->method) {
            if (isset($_POST[$this->config['var_method']])) {
                $method = strtolower($_POST[$this->config['var_method']]);
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    $this->method    = strtoupper($method);
                    $this->{$method} = $_POST;
                } else {
                    $this->method = 'POST';
                }
                unset($_POST[$this->config['var_method']]);
            } elseif ($this->server('HTTP_X_HTTP_METHOD_OVERRIDE')) {
                $this->method = strtoupper($this->server('HTTP_X_HTTP_METHOD_OVERRIDE'));
            } else {
                $this->method = $this->server('REQUEST_METHOD') ?: 'GET';
            }
        }

        return $this->method;
	}
	
	    /**
     * 获取当前请求的参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function param($name = '', $default = null, $filter = '')
    {
        if (!$this->mergeParam) {
            $method = $this->method(true);

            // 自动获取请求变量
            switch ($method) {
                case 'POST':
                    $vars = $this->_post(false);
                    break;
                case 'PUT':
                case 'DELETE':
                case 'PATCH':
                    $vars = $this->put(false);
                    break;
                default:
                    $vars = [];
            }

            // 当前请求参数和URL地址中的参数合并
            $this->param = array_merge($this->param, $this->_get(false), $vars, $this->_route(false));

            $this->mergeParam = true;
        }

        if (true === $name) {
            // 获取包含文件上传信息的数组
            $file = $this->file();
            $data = is_array($file) ? array_merge($this->param, $file) : $this->param;

            return $this->input($data, '', $default, $filter);
        }

        return $this->input($this->param, $name, $default, $filter);
	}
	
	/**
     * 获取路由参数
     * @access public
     * @param  string|false  $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function _route($name = '', $default = null, $filter = '')
    {
        return $this->input($this->route, $name, $default, $filter);
    }

    /**
     * 获取GET参数
     * @access public
     * @param  string|false  $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function _get($name = '', $default = null, $filter = '')
    {
        if (empty($this->get)) {
            $this->get = $_GET;
        }

        return $this->input($this->get, $name, $default, $filter);
	}

	/**
     * 获取POST参数
     * @access public
     * @param  string|false  $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function _post($name = '', $default = null, $filter = '')
    {
        if (empty($this->post)) {
            $this->post = !empty($_POST) ? $_POST : $this->getInputData($this->input);
        }

        return $this->input($this->post, $name, $default, $filter);
	}
	
	protected function getInputData($content)
    {
        if ($this->isJson()) {
            return (array) json_decode($content, true);
        } elseif (strpos($content, '=')) {
            parse_str($content, $data);
            return $data;
        }

        return [];
	}
	
	/**
     * 获取上传的文件信息
     * @access public
     * @param  string   $name 名称
     * @return null|array|\zongphp\File
     */
    public function file($name = '')
    {
        if (empty($this->file)) {
            $this->file = isset($_FILES) ? $_FILES : [];
        }

        $files = $this->file;
        if (!empty($files)) {
            if (strpos($name, '.')) {
                list($name, $sub) = explode('.', $name);
            }

            // 处理上传文件
            $array = $this->dealUploadFile($files, $name);

            if ('' === $name) {
                // 获取全部文件
                return $array;
            } elseif (isset($sub) && isset($array[$name][$sub])) {
                return $array[$name][$sub];
            } elseif (isset($array[$name])) {
                return $array[$name];
            }
        }

        return;
    }

    protected function dealUploadFile($files, $name)
    {
        $array = [];
        foreach ($files as $key => $file) {
            if ($file instanceof File) {
                $array[$key] = $file;
            } elseif (is_array($file['name'])) {
                $item  = [];
                $keys  = array_keys($file);
                $count = count($file['name']);

                for ($i = 0; $i < $count; $i++) {
                    if ($file['error'][$i] > 0) {
                        if ($name == $key) {
                            $this->throwUploadFileError($file['error'][$i]);
                        } else {
                            continue;
                        }
                    }

                    $temp['key'] = $key;

                    foreach ($keys as $_key) {
                        $temp[$_key] = $file[$_key][$i];
                    }

                    $item[] = (new File($temp['tmp_name']))->setUploadInfo($temp);
                }

                $array[$key] = $item;
            } else {
                if ($file['error'] > 0) {
                    if ($key == $name) {
                        $this->throwUploadFileError($file['error']);
                    } else {
                        continue;
                    }
                }

                $array[$key] = (new File($file['tmp_name']))->setUploadInfo($file);
            }
        }

        return $array;
    }

    protected function throwUploadFileError($error)
    {
        static $fileUploadErrors = [
            1 => 'upload File size exceeds the maximum value',
            2 => 'upload File size exceeds the maximum value',
            3 => 'only the portion of file is uploaded',
            4 => 'no file to uploaded',
            6 => 'upload temp dir not found',
            7 => 'file write error',
        ];

        $msg = $fileUploadErrors[$error];

        throw new \zongphp\exception\Exception($msg);
    }
	
	/**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType()
    {
        $contentType = $this->server('CONTENT_TYPE');

        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }

        return '';
	}
	
	/**
     * 排除指定参数获取
     * @access public
     * @param  string|array  $name 变量名
     * @param  string        $type 变量类型
     * @return mixed
     */
    public function except($name, $type = 'param')
    {
        $param = $this->$type();
        if (is_string($name)) {
            $name = explode(',', $name);
        }

        foreach ($name as $key) {
            if (isset($param[$key])) {
                unset($param[$key]);
            }
        }

        return $param;
	}
	
	/**
     * 生成请求令牌
     * @access public
     * @param  string $name 令牌名称
     * @param  mixed  $type 令牌生成方法
     * @return string
     */
    public function token($name = '__token__', $type = null)
    {
        $type  = is_callable($type) ? $type : 'md5';
        $token = call_user_func($type, $this->server('REQUEST_TIME_FLOAT'));

        if ($this->isAjax()) {
            header($name . ': ' . $token);
        }

        Session::set($name, $token);

        return $token;
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl()
    {
        if ($this->server('HTTPS') && ('1' == $this->server('HTTPS') || 'on' == strtolower($this->server('HTTPS')))) {
            return true;
        } elseif ('https' == $this->server('REQUEST_SCHEME')) {
            return true;
        } elseif ('443' == $this->server('SERVER_PORT')) {
            return true;
        } elseif ('https' == $this->server('HTTP_X_FORWARDED_PROTO')) {
            return true;
        } elseif ($this->config['https_agent_name'] && $this->server($this->config['https_agent_name'])) {
            return true;
        }

        return false;
    }


	/**
     * 当前是否JSON请求
     * @access public
     * @return bool
     */
    public function isJson()
    {
        $contentType = $this->contentType();
        $acceptType  = $this->type();

        return false !== strpos($contentType, 'json') || false !== strpos($acceptType, 'json');
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