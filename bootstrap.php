<?php
define('SURIKAT',__DIR__.'/');
define('SURIKAT_CWD',getcwd().'/');
if(!defined('SURIKAT_FREEZE_DI'))
	define('SURIKAT_FREEZE_DI',false);

require_once __DIR__.'/php/Unit/Autoloader.php';
Unit\Autoloader::getInstance()->addNamespace('',[
		SURIKAT_CWD.'php',
		SURIKAT.'php'
])->splRegister();

global $SURIKAT;
$SURIKAT = Unit\Di::load([
	SURIKAT.'.config.xml',
	SURIKAT_CWD.'.config.xml'
],SURIKAT_FREEZE_DI,SURIKAT_CWD.'.tmp/SURIKAT.svar');

if($SURIKAT['dev']['php']){
	$SURIKAT->create('Unit\Debug')->handleErrors();
}
else{
	error_reporting(0);
	ini_set('display_startup_errors',false);
	ini_set('display_errors',false);
}