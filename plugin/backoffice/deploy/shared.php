<?php
use Git\GitDeploy\GitDeploy;

set_time_limit(0);
$this->Http_Request()->nocacheHeaders();
ob_implicit_flush(true);
@ob_end_flush();

echo '<pre>';
GitDeploy::factory(SURIKAT_PATH)
	->maintenanceOn()
	->autocommit()
	->deploy()
	->getParent(SURIKAT_SPATH)
		->deploy()
	->getOrigin()
		->maintenanceOff()
;
echo '</pre>';