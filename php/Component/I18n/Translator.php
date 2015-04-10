<?php namespace Surikat\Component\I18n;
use Surikat\Component\DependencyInjection\MutatorMagicTrait;
use Surikat\Component\DependencyInjection\Facade;
class Translator {
	use Facade;
	use MutatorMagicTrait;
	protected static $systemLocales;
	protected static $bindStack = [];
	private $locale;
	private $domain;
	private $originLocale;
	private $realLocale;
	private $localesRoot;
	private $gettext;
	private $altLocales;
	protected $countryAuto = true;
	protected $defaultLocale = 'en_US';
	protected $defaultDomain = 'messages';
	protected $defaultLocalesRoot;
	protected $suffixLocales = '.utf8';
	protected $text_domains = [];
	protected $default_domain = 'messages';
	protected $LC_CATEGORIES = ['LC_CTYPE', 'LC_NUMERIC', 'LC_TIME', 'LC_COLLATE', 'LC_MONETARY', 'LC_MESSAGES', 'LC_ALL'];
	protected $EMULATEGETTEXT = 1;
	function __construct($locale=null,$domain=null){
		$this->defaultLocalesRoot = SURIKAT_PATH.'langs';
		$tz = Container::get()->Config('langs')->timezone;
		if(!$tz)
			$tz = @date_default_timezone_get();
		date_default_timezone_set($tz);
		exec('locale -a',self::$systemLocales);
		
		$this->localesRoot = $this->defaultLocalesRoot;
		$this->originLocale = $locale;
		$this->locale = $locale;
		$this->domain = $domain;
		if($this->Dev_Level->I18N)
			$this->realDomain = $this->getLastMoFile();
		else
			$this->realDomain = $this->domain;
		$this->realLocale = $this->locale.$this->suffixLocales;
		$this->altLocales = $this->__GettextEmulator($this->realLocale)->get_list_of_locales($this->realLocale);
		if(function_exists('setlocale')){
			foreach($this->altLocales as $lc){
				if(in_array($lc,self::$systemLocales)){
					$this->EMULATEGETTEXT = 0;
					break;
				}
			}
			if(	$this->EMULATEGETTEXT
				&&$this->countryAuto
				&&strpos($this->locale,'_')===false
				&&is_dir($this->localesRoot.'/'.$this->locale)
			){
				foreach(self::$systemLocales as $lc){
					if(strpos($lc,$this->locale.'_')===0){
						if(!is_dir($this->localesRoot.'/'.$lc)){
							$cwd = getcwd();
							chdir($this->localesRoot);
							symlink($this->locale,$this->localesRoot.'/'.$lc);
							chdir($cwd);
						}
						if(false!==$p=strpos($lc,'.'))
							$lc = substr($lc,0,$p);
						if(false!==$p=strpos($lc,'@'))
							$lc = substr($lc,0,$p);
						$this->locale = $lc;
						$this->realLocale = $this->locale.$this->suffixLocales;
						$this->EMULATEGETTEXT = 0;
						break;
					}
				}
			}
		}
		$this->bind();
	}
	function _n__($singular,$plural,$number,$lang=null,$domain=null){
		if(isset($lang)||isset($domain)){
			if(!isset($lang))
				$lang = self::current()->locale;
			if(!isset($domain))
				$domain = self::current()->domain;
			$o = self::factory($lang,$domain);
		}
		else
			$o = self::current();
		return $o->ngettext($singular, $plural, $number);
	}
	function ___($msgid,$lang=null,$domain=null){
		if(isset($lang)||isset($domain)){
			if(!isset($lang))
				$lang = self::current()->locale;
			if(!isset($domain))
				$domain = self::current()->domain;
			$o = self::factory($lang,$domain);
		}
		else
			$o = self::current();
		return $o->gettext($msgid);
	}
	function _unbind(){
		array_pop(self::$bindStack);
		$last = end(self::$bindStack);
		if(!$last){
			$last = [
				$this->defaultLocale,
				$this->defaultLocale.$this->suffixLocales,
				$this->defaultDomain,
				$this->defaultLocalesRoot
			];
		}
		putenv('LANG='.$last[0]);
		putenv('LANGUAGE='.$last[0]);
		putenv('LC_ALL='.$last[0]);
		$this->setlocale(LC_ALL,$last[1]);
		$this->bindtextdomain($last[2],$last[3]);
		$this->textdomain($last[2]);
		$this->bind_textdomain_codeset($last[2], "UTF-8");
	}
	function _bind(){
		self::$bindStack[] = [$this->locale,$this->realLocale,$this->realDomain,$this->localesRoot];
		$lang = $this->getLangCode();
		putenv('LANG='.$this->locale);
		putenv('LANGUAGE='.$this->locale);
		putenv('LC_ALL='.$this->locale);
		$this->setlocale(LC_ALL,$this->realLocale);
		$this->bindtextdomain($this->realDomain,$this->localesRoot);
		$this->textdomain($this->realDomain);
		$this->bind_textdomain_codeset($this->realDomain, "UTF-8");
	}
	function _getLocale(){
		return $this->locale;
	}
	function _getLangCode(){
		if(false!==$p=strpos($this->locale,'_')){
			return substr($this->locale,0,$p);
		}
		return $this->locale;	
	}
	function _getLastMoFile(){
		$mo = glob($this->localesRoot.'/'.$this->locale.'/LC_MESSAGES/'.$this->domain.'.*.mo');
		return !empty($mo)?substr(basename(end($mo)),0,-3):$this->domain;
	}
	function ___call($f,$args){
		switch($f){
			case 'setlocale':
			case 'bindtextdomain':
			case 'textdomain':
			case 'bind_textdomain_codeset':
				if($this->EMULATEGETTEXT){
					$r = call_user_func_array([$this->__GettextEmulator($this->realLocale),$f],$args);
				}
				else{
					$r = call_user_func_array($f,$args);
				}					
			break;
			case 'gettext':
			case 'ngettext':
			case 'dgettext':
			case 'dngettext':
			case 'dcgettext':
			case 'dcngettext':
			case 'pgettext':
			case 'dpgettext':
			case 'dcpgettext':
			case 'npgettext':
			case 'dnpgettext':
			case 'dcnpgettext':
				$this->bind();
				if($this->EMULATEGETTEXT){
					$r = call_user_func_array([$this->__GettextEmulator($this->realLocale),$f],$args);
				}
				else{
					$r = call_user_func_array($f,$args);
				}					
				$this->unbind();
			break;
			default:
				throw new \Exception(sprintf('Call to undefined Method %s',$f));
			break;
		}
		return $r;
	}
}