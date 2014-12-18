<?php namespace Surikat\Service;
use Surikat\Tool\GitDeploy\GitDeploy;
use Surikat\Core\ConfigINI;
class Service_Deploy{
	static function directOutput(){
		set_time_limit(0);ob_implicit_flush(true);ob_end_flush();
	}
	static function method(){
		self::directOutput();
		echo '<pre>';
		GitDeploy::main();
		echo '</pre>';
	}
	static function surikatIn(){
		self::directOutput();
		echo '<pre>';
		GitDeploy::main([
			'repo_path'=>SURIKAT_SPATH,
		],false,true);
		echo '</pre>';
		
	}
	static function oneShot(){
		self::autocommit();
		self::surikatIn();
		self::method();
	}
	static function surikatShared(){
		self::directOutput();
		echo '<pre>';
		GitDeploy::main([
			'repo_path'=>SURIKAT_SPATH,
		],true);
		echo '</pre>';
	}
	static function autocommit(){ //need the .git have recursively full permission (www-data have to be able to write)
		self::directOutput();
		$email = ConfigINI::deploy('user.email');
		$name = ConfigINI::deploy('user.name');
		if(!$email||!$name)
			trigger_error('You have to define user.email and user.name in config/deploy.ini',256);
		echo '<pre>';
		self::exec('cd '.SURIKAT_PATH);
		self::exec('git config --local user.email "'.$email.'"');
		self::exec('git config --local user.name "'.$name.'"');
		self::exec('git add --all .');
		$message = "auto commit by service deploy - ".@strftime('%A %e %B %G - %k:%M:%S',time());
		self::exec('git commit -m "'.$message.'"');
		shell_exec('chmod -R 777 '.SURIKAT_PATH.'.git 2>&1');
		GitDeploy::main();
		echo '</pre>';
	}
	protected static function exec($cmd){
		echo $cmd."\r\n";
		echo shell_exec($cmd.' 2>&1');
	}
}

/*
create a deploy.ini file at root of your project
example:
user.email = me@mydomain.com
user.name = Me

[team-ossature-bois]
skip = false
user = ftplogin
pass = ftppassword
host = myhost.com
port = 21
path = /www/dev/project
passive = true
;[ftp://ftplogin:ftppassword@myhost.com:21/www/dev/project]

clean_directories[] = folder/to/clean
clean_directories[] = another/folder

ignore_files[] = file/toignore.txt
ignore_files[] = another/file/toignore.php

upload_untracked[] = folder/to/upload
upload_untracked[] = another/file/toignore.php

maintenance_file = 'maintenance.php'
maintenance_on_value = '<?php $under_maintenance = true;?>'
maintenance_off_value = '<?php $under_maintenance = false;?>'

*/