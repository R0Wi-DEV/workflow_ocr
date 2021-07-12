<?php

use OCA\WorkflowOcr\AppInfo;

require_once __DIR__ . '/../../../tests/bootstrap.php';

\OC_App::loadApp(AppInfo\Application::APP_NAME);

if (!class_exists('PHPUnit\Framework\TestCase')) {
	require_once('PHPUnit/Autoload.php');
}

OC_Hook::clear();
