<?php namespace Surikat\View;
class TML_Script extends TML{
	protected $noParseContent = true;
	function loaded(){
		if($this->getDependency('Dev\Level')->JS&&$this->src&&strpos($this->src,'://')===false&&strpos($this->src,'_t=')===false){
			if(strpos($this->src,'?')===false)
				$this->src .= '?';
			else
				$this->src .= '&';
			$this->src .= '_t='.time();
		}
	}
}
