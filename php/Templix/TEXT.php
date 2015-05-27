<?php namespace Templix;
class TEXT extends Tml{
	var $nodeName = 'TEXT';
	protected $hiddenWrap = true;
	function parse($text){
		if($this->parent&&$this->parent->templix&&$this->parent->templix->isXhtml)
			$text = preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]+);/S',['self','convert_entity'], $text);
		$text = self::phpImplode($text,$this->constructor);
		$this->textInject($text);
		//$this->biohazard();
	}
	function biohazard(){
		if(!$this->parent||!$this->parent->antibiotique)
			$this->write(new Tml('<loremipsum mini>'));
	}
	function textInject($text){
		if(strpos($text,'<?php ')===false)
			$this->innerHead($text);
		else{
			$open = 0;
			$php = '';
			$xml = '';
			$tokens = token_get_all($text);
			foreach($tokens as $token){
				if(is_array($token)){
					switch($token[0]){
						case T_OPEN_TAG:
							$open = 1;
							if($xml){
								$textNode = new TEXT();
								$textNode->setParent($this);
								$textNode->setBuilder($this);
								$textNode->parse($xml);
								$this->childNodes[] = $textNode;
							}
							$xml = '';
							$php = '<?php ';
						break;
						case T_OPEN_TAG_WITH_ECHO:
							$open = 2;
							if($xml){
								$textNode = new TEXT();
								$textNode->setParent($this);
								$textNode->setBuilder($this);
								$textNode->parse($xml);
								$this->childNodes[] = $textNode;
							}
							$xml = '';
							$php = '<?php echo ';
						break;
						case T_CLOSE_TAG:
							$open = 0;
							$phpNode = new PHP();
							$phpNode->setParent($this);
							$phpNode->setBuilder($this->constructor);
							$phpNode->parse($php.($open===2&&substr(trim($php),-1)!=';'?';':'').'?>');
							$this->childNodes[] = $phpNode;
							$php = '';
						break;
						default:
							if($open)
								$php .= $token[1];
							else
								$xml .= $token[1];
						break;
					}
				}
				else{
					if($open)
						$php .= $token;
					else
						$xml .= $token;
				}
			}
			if($php){
				$phpNode = new PHP();
				$phpNode->setParent($this);
				$phpNode->setBuilder($this->constructor);
				$phpNode->parse($php.($open===2?';'&&substr(trim($php),-1)!=';':'').'?>');
				$this->childNodes[] = $phpNode;
			}
			if($xml){
				$textNode = new TEXT();
				$textNode->setParent($this);
				$textNode->setBuilder($this);
				$textNode->parse($xml);
				$this->childNodes[] = $textNode;
			}
		}
	}
	/* Swap HTML named entity with its numeric equivalent. If the entity
	* isn't in the lookup table, this function returns a blank, which
	* destroys the character in the output - this is probably the
	* desired behaviour when producing XML. */
	static function convert_entity($matches) {
		static $table = ['quot' => '&#34;',
			'amp' => '&#38;',
			'lt' => '&#60;',
			'gt' => '&#62;',
			'OElig' => '&#338;',
			'oelig' => '&#339;',
			'Scaron' => '&#352;',
			'scaron' => '&#353;',
			'Yuml' => '&#376;',
			'circ' => '&#710;',
			'tilde' => '&#732;',
			'ensp' => '&#8194;',
			'emsp' => '&#8195;',
			'thinsp' => '&#8201;',
			'zwnj' => '&#8204;',
			'zwj' => '&#8205;',
			'lrm' => '&#8206;',
			'rlm' => '&#8207;',
			'ndash' => '&#8211;',
			'mdash' => '&#8212;',
			'lsquo' => '&#8216;',
			'rsquo' => '&#8217;',
			'sbquo' => '&#8218;',
			'ldquo' => '&#8220;',
			'rdquo' => '&#8221;',
			'bdquo' => '&#8222;',
			'dagger' => '&#8224;',
			'Dagger' => '&#8225;',
			'permil' => '&#8240;',
			'lsaquo' => '&#8249;',
			'rsaquo' => '&#8250;',
			'euro' => '&#8364;',
			'fnof' => '&#402;',
			'Alpha' => '&#913;',
			'Beta' => '&#914;',
			'Gamma' => '&#915;',
			'Delta' => '&#916;',
			'Epsilon' => '&#917;',
			'Zeta' => '&#918;',
			'Eta' => '&#919;',
			'Theta' => '&#920;',
			'Iota' => '&#921;',
			'Kappa' => '&#922;',
			'Lambda' => '&#923;',
			'Mu' => '&#924;',
			'Nu' => '&#925;',
			'Xi' => '&#926;',
			'Omicron' => '&#927;',
			'Pi' => '&#928;',
			'Rho' => '&#929;',
			'Sigma' => '&#931;',
			'Tau' => '&#932;',
			'Upsilon' => '&#933;',
			'Phi' => '&#934;',
			'Chi' => '&#935;',
			'Psi' => '&#936;',
			'Omega' => '&#937;',
			'alpha' => '&#945;',
			'beta' => '&#946;',
			'gamma' => '&#947;',
			'delta' => '&#948;',
			'epsilon' => '&#949;',
			'zeta' => '&#950;',
			'eta' => '&#951;',
			'theta' => '&#952;',
			'iota' => '&#953;',
			'kappa' => '&#954;',
			'lambda' => '&#955;',
			'mu' => '&#956;',
			'nu' => '&#957;',
			'xi' => '&#958;',
			'omicron' => '&#959;',
			'pi' => '&#960;',
			'rho' => '&#961;',
			'sigmaf' => '&#962;',
			'sigma' => '&#963;',
			'tau' => '&#964;',
			'upsilon' => '&#965;',
			'phi' => '&#966;',
			'chi' => '&#967;',
			'psi' => '&#968;',
			'omega' => '&#969;',
			'thetasym' => '&#977;',
			'upsih' => '&#978;',
			'piv' => '&#982;',
			'bull' => '&#8226;',
			'hellip' => '&#8230;',
			'prime' => '&#8242;',
			'Prime' => '&#8243;',
			'oline' => '&#8254;',
			'frasl' => '&#8260;',
			'weierp' => '&#8472;',
			'image' => '&#8465;',
			'real' => '&#8476;',
			'trade' => '&#8482;',
			'alefsym' => '&#8501;',
			'larr' => '&#8592;',
			'uarr' => '&#8593;',
			'rarr' => '&#8594;',
			'darr' => '&#8595;',
			'harr' => '&#8596;',
			'crarr' => '&#8629;',
			'lArr' => '&#8656;',
			'uArr' => '&#8657;',
			'rArr' => '&#8658;',
			'dArr' => '&#8659;',
			'hArr' => '&#8660;',
			'forall' => '&#8704;',
			'part' => '&#8706;',
			'exist' => '&#8707;',
			'empty' => '&#8709;',
			'nabla' => '&#8711;',
			'isin' => '&#8712;',
			'notin' => '&#8713;',
			'ni' => '&#8715;',
			'prod' => '&#8719;',
			'sum' => '&#8721;',
			'minus' => '&#8722;',
			'lowast' => '&#8727;',
			'radic' => '&#8730;',
			'prop' => '&#8733;',
			'infin' => '&#8734;',
			'ang' => '&#8736;',
			'and' => '&#8743;',
			'or' => '&#8744;',
			'cap' => '&#8745;',
			'cup' => '&#8746;',
			'int' => '&#8747;',
			'there4' => '&#8756;',
			'sim' => '&#8764;',
			'cong' => '&#8773;',
			'asymp' => '&#8776;',
			'ne' => '&#8800;',
			'equiv' => '&#8801;',
			'le' => '&#8804;',
			'ge' => '&#8805;',
			'sub' => '&#8834;',
			'sup' => '&#8835;',
			'nsub' => '&#8836;',
			'sube' => '&#8838;',
			'supe' => '&#8839;',
			'oplus' => '&#8853;',
			'otimes' => '&#8855;',
			'perp' => '&#8869;',
			'sdot' => '&#8901;',
			'lceil' => '&#8968;',
			'rceil' => '&#8969;',
			'lfloor' => '&#8970;',
			'rfloor' => '&#8971;',
			'lang' => '&#9001;',
			'rang' => '&#9002;',
			'loz' => '&#9674;',
			'spades' => '&#9824;',
			'clubs' => '&#9827;',
			'hearts' => '&#9829;',
			'diams' => '&#9830;',
			'nbsp' => '&#160;',
			'iexcl' => '&#161;',
			'cent' => '&#162;',
			'pound' => '&#163;',
			'curren' => '&#164;',
			'yen' => '&#165;',
			'brvbar' => '&#166;',
			'sect' => '&#167;',
			'uml' => '&#168;',
			'copy' => '&#169;',
			'ordf' => '&#170;',
			'laquo' => '&#171;',
			'not' => '&#172;',
			'shy' => '&#173;',
			'reg' => '&#174;',
			'macr' => '&#175;',
			'deg' => '&#176;',
			'plusmn' => '&#177;',
			'sup2' => '&#178;',
			'sup3' => '&#179;',
			'acute' => '&#180;',
			'micro' => '&#181;',
			'para' => '&#182;',
			'middot' => '&#183;',
			'cedil' => '&#184;',
			'sup1' => '&#185;',
			'ordm' => '&#186;',
			'raquo' => '&#187;',
			'frac14' => '&#188;',
			'frac12' => '&#189;',
			'frac34' => '&#190;',
			'iquest' => '&#191;',
			'Agrave' => '&#192;',
			'Aacute' => '&#193;',
			'Acirc' => '&#194;',
			'Atilde' => '&#195;',
			'Auml' => '&#196;',
			'Aring' => '&#197;',
			'AElig' => '&#198;',
			'Ccedil' => '&#199;',
			'Egrave' => '&#200;',
			'Eacute' => '&#201;',
			'Ecirc' => '&#202;',
			'Euml' => '&#203;',
			'Igrave' => '&#204;',
			'Iacute' => '&#205;',
			'Icirc' => '&#206;',
			'Iuml' => '&#207;',
			'ETH' => '&#208;',
			'Ntilde' => '&#209;',
			'Ograve' => '&#210;',
			'Oacute' => '&#211;',
			'Ocirc' => '&#212;',
			'Otilde' => '&#213;',
			'Ouml' => '&#214;',
			'times' => '&#215;',
			'Oslash' => '&#216;',
			'Ugrave' => '&#217;',
			'Uacute' => '&#218;',
			'Ucirc' => '&#219;',
			'Uuml' => '&#220;',
			'Yacute' => '&#221;',
			'THORN' => '&#222;',
			'szlig' => '&#223;',
			'agrave' => '&#224;',
			'aacute' => '&#225;',
			'acirc' => '&#226;',
			'atilde' => '&#227;',
			'auml' => '&#228;',
			'aring' => '&#229;',
			'aelig' => '&#230;',
			'ccedil' => '&#231;',
			'egrave' => '&#232;',
			'eacute' => '&#233;',
			'ecirc' => '&#234;',
			'euml' => '&#235;',
			'igrave' => '&#236;',
			'iacute' => '&#237;',
			'icirc' => '&#238;',
			'iuml' => '&#239;',
			'eth' => '&#240;',
			'ntilde' => '&#241;',
			'ograve' => '&#242;',
			'oacute' => '&#243;',
			'ocirc' => '&#244;',
			'otilde' => '&#245;',
			'ouml' => '&#246;',
			'divide' => '&#247;',
			'oslash' => '&#248;',
			'ugrave' => '&#249;',
			'uacute' => '&#250;',
			'ucirc' => '&#251;',
			'uuml' => '&#252;',
			'yacute' => '&#253;',
			'thorn' => '&#254;',
			'yuml' => '&#255;'
		];
		return isset($table[$matches[1]]) ? $table[$matches[1]] : ''; // Entity not found? Destroy it.
	}
}