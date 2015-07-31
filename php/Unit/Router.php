<?php namespace Unit;
class Router implements \ArrayAccess{
	private $routes = [];
	private $route;
	private $routeParams;
	private $di;
	private $index = 0;
	function __construct(Di $di){
		$this->di = $di;
	}
	function map($map,$index=null,$prepend=false){
		foreach($map as list($match,$route)){
			$this->route($match,$route,$index,$prepend);
		}
		return $this;
	}
	function append($match,$route,$index=null){
		return $this->route($match,$route,$index);
	}
	function prepend($match,$route,$index=null){
		return $this->route($match,$route,$index,true);
	}
	function find($uri,$server=null){
		$uri = ltrim($uri,'/');
		ksort($this->routes);
		foreach($this->routes as $group){
			foreach($group as list($match,$route)){
				$routeParams = call_user_func($this->di->objectify($match),$uri,$server);
				if($routeParams!==null){
					$this->route = $route;
					$this->routeParams = $routeParams;
					return true;
				}
			}
		}
	}
	function display(){
		$route = $this->route;
		while(is_callable($route=$this->di->objectify($route))){
			$route = call_user_func($route,$this->routeParams);
		}
	}
	function route($match,$route,$index=null,$prepend=false,$subindex=null){
		if(is_null($index))
			$index = $this->index;
		$pair = [$this->matchType($match),$route];
		if(!isset($this->routes[$index]))
			$this->routes[$index] = [];
		if(!is_null($subindex))
			$this->routes[$index][$subindex] = $pair;
		elseif($prepend)
			array_unshift($this->routes[$index],$pair);
		else
			$this->routes[$index][] = $pair;
		return $this;
	}
	private function matchType($match){
		if(is_string($match)){
			if(strpos($match,'/^')===0&&strrpos($match,'$/')-strlen($match)===-2){
				return ['new:Unit\RouteMatch\Regex',$match];
			}
			else{
				return ['new:Unit\RouteMatch\Prefix',$match];
			}
		}
		return $match;
	}
	function setIndex($index=0){
		$this->index = $index;
	}
	function offsetSet($k,$v){
		list($match,$route) = $v;
		$this->route($match,$route,$this->index,false,$k);
	}
	function offsetGet($k){
		if(!isset($this->routes[$this->index][$k]))
			$this->routes[$this->index][$k] = [];
		return $this->routes[$this->index][$k];
	}
	function offsetExists($k){
		return isset($this->routes[$this->index][$k]);
	}
	function offsetUnset($k){
		if(isset($this->routes[$this->index][$k]))
			unset($this->routes[$this->index][$k]);
	}
}