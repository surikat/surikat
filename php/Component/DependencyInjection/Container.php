<?php namespace Surikat\DependencyInjection;
class Container{
	use MutatorMagic;
	use Registry;
	static function get(){
		$args = func_get_args();
		if(empty($args))
			return static::getStatic();
		$key = array_shift($args);
		return static::getStatic()->getDependency($key,$args);
	}
	static function set($key,$value){
		return static::getStatic()->setDependency($key,$value);
	}
	function defaultDependency($key,$args=null){
		return $this->factoryDependency($key,$args);
	}
}