<?php namespace Surikat\View; 
class TML_Pre extends TML{
	protected $noParseContent = true;
	function load(){
		$text = $this->getInnerTml();
		$this->clearInner();
		$this->innerHead[] = htmlentities($text);
	}
}