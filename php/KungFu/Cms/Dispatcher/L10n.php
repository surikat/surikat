<?php namespace KungFu\Cms\Dispatcher;
use Unit\Dispatcher;
use Unit\Route\Extension;
use Unit\Route\ByTml;
use Unit\Route\L10n as Route_L10n;
use Templix\Templix;
use KungFu\Cms\Controller\L10n as Controller_L10n;
class L10n extends Index{
	protected $Templix;
	protected $Controller_L10n;
	function __construct($config=[]){
		foreach($config as $k=>$v){
			$this->$k = $v;
		}
	}
	function Controller_L10n(){
		if(!$this->Controller_L10n){
			$this->Controller_L10n = new Controller_L10n();			
			$this->append(new ByTml('plugin'),$this);
			$this->append(new ByTml(),$this);
			if($this->backoffice)
				$this->prepend($this->backoffice,new Backoffice());
		}
		return $this->Controller_L10n;
	}
	function Templix(){
		return $this->Templix?:$this->Templix = new Templix();
	}
	function __invoke(){
		return $this->Controller_L10n();
	}
}