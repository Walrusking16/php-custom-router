<?php

require "RouterContext.php";

class Router {
	public array $routes = [];
	public string $id = "";
	protected string $prefixStr = "";
	protected $pathNotFound = null;
	protected $methodNotAllowed = null;
	protected $middlewares = [];
	protected static array $globalRoutes = [];
	protected static array $customParams = [];

	public function __construct() {
	    $this->id = uniqid();

	    $this->middlewares = [];

	    if(count(self::$customParams) <= 0) {
	        self::$customParams = [
                "string" => function($val) {
	                return strval($val);
                },
                "int" => function($val) {
                    return intval($val);
                },
                "float" => function($val) {
                    return floatval($val);
                },
                "bool" => function($val) {
	                if (is_bool($val))
	                    $val = boolval($val);
	                elseif (is_numeric($val))
                        $val = intval($val);
                    elseif (is_float($val))
                        $val = intval($val);
                    else
                        $val = strtolower($val);

                    echo get_debug_type($val) . " $val";

	                switch ($val) {
                        case "yes":
                        case "on":
                        case 1:
                        case true: {
                            return true;
                        }

                        case "no":
                        case "off":
                        case 0:
                        case false: {
                            return false;
                        }

                        default: {
                            return boolval($val);
                        }
                    }
                },
            ];
        }
    }

	public function prefix(string $prefix) : void {
		$this->prefixStr = $prefix;
	}

	public function merge(Router $router) : void {
		$this->routes = array_merge($this->routes, $router->routes);
	}

	public function name(string $name) : void {
		$route = end($this->routes);

		self::$globalRoutes[$name] = $route["path"];
	}

	public function middleware(callable $function) : void {
        $this->middlewares[] = $function;
    }

	public function get(string $path, callable $callback) : Router {
		$this->new($path, $callback, "get");

		return $this;
	}

	public function post(string $path, callable $callback) : Router {
		$this->new($path, $callback, "post");

		return $this;
	}

	protected function new(string $path, callable $callback, string $method) : Router {
		array_push($this->routes, [
			'path' => $this->prefixStr . $path,
			'function' => $callback,
			'method' => $method,
            'middlewares' => $this->middlewares
		]);

		return $this;
	}

	public function run(string $defaultPath = '/') : void {

		$parsed_url = parse_url($_SERVER['REQUEST_URI']);

		if(isset($parsed_url['path'])){
			$path = $parsed_url['path'];
		}else{
			$path = '/';
		}

		$temp = str_replace($_SERVER["DOCUMENT_ROOT"], "", str_replace("\\", "/", getcwd()));

		if (str_contains($path , $temp)) {
			$path = str_replace($temp, "", $path);
		}

		$method = $_SERVER['REQUEST_METHOD'];
		$path_match_found = false;
		$route_match_found = false;
		$paramArray = [];

		foreach($this->routes as $route){
			if($defaultPath != '' && $defaultPath != '/'){
				$route['path'] = "($defaultPath)" . $route["path"];
			}

			if (preg_match_all("/{.*?}/m", $route['path'], $params)) {
				foreach ($params[0] as $param) {
				    $arr = explode(":", trim($param, "{}"));

				    if (count($arr) == 1) {
				        $arr = ["string", $arr[0]];
                    }

				    if(array_key_exists($arr[0], self::$customParams)) {
                        $paramArray[] = self::$customParams[$arr[0]];
                    }

					$route["path"] = str_replace($param, "([^/]*?)", $route["path"]);
				}
			}

			$route['path'] = '^' . $route['path'] . '$';

			if(preg_match('#' . $route['path'] . '#', $path,$matches)){

				$path_match_found = true;

				if(strtolower($method) == strtolower($route['method'])){

					array_shift($matches);

					if($defaultPath != '' && $defaultPath != '/'){
						array_shift($matches);
					}

					$num = 0;
					foreach ($matches as &$match) {
					    $match = $paramArray[$num]($match);

					    //echo $match;

                        $num++;
                    }

					if(count($route["middlewares"]) > 0) {
					    $lastMiddleware = null;
					    $pass = false;

                        foreach ($route["middlewares"] as $middleware) {
                            $lastMiddleware = $middleware(new RouterContext());
                            $pass = $lastMiddleware->pass;
                            if(!$pass)
                                break;
                        }

                        if ($pass) {
                            call_user_func_array($route['function'], $matches);
                        }
                        else {
                            foreach ($lastMiddleware->failFunctions as $fail) {
                                $fail();
                            }
                        }
                    } else {
                        call_user_func_array($route['function'], $matches);
                    }

					$route_match_found = true;
					break;
				}
			}
		}

		if(!$route_match_found){
			if($path_match_found){
				http_response_code(405);
				if($this->methodNotAllowed){
					call_user_func_array($this->methodNotAllowed, [$path,$method]);
				}
			} else {
                http_response_code(404);
				if($this->pathNotFound){
					call_user_func_array($this->pathNotFound, [$path]);
				}
			}
		}
	}
}