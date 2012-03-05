<?php

// guess current application
if (!isset($app))
{
  $app = 'backend';
}

require_once dirname(__FILE__).'/../../../../config/ProjectConfiguration.class.php';

$configuration = ProjectConfiguration::getApplicationConfiguration($app, 'test', isset($debug) ? $debug : true);
sfContext::createInstance($configuration);

// remove all cache
sfToolkit::clearDirectory(sfConfig::get('sf_app_cache_dir'));

// cp data files - why are they deleted during tests? :-|
copy(dirname(__FILE__) . '/../data/demo1.png', dirname(__FILE__) . '/../data/demo.png');
copy(dirname(__FILE__) . '/../data/propel1.gif', dirname(__FILE__) . '/../data/propel.gif');
copy(dirname(__FILE__) . '/../data/demo1.png', dirname(__FILE__) . '/../data/demo2.png');
copy(dirname(__FILE__) . '/../data/propel1.gif', dirname(__FILE__) . '/../data/propel2.gif');

// load fixtures
$data = new sfPropelData();
$data->loadData(dirname(__FILE__) . '/../data/fixtures/');