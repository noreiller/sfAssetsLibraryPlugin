<?php

$app = 'frontend';
include dirname(__FILE__).'/../../../../test/bootstrap/functional.php';
include $configuration->getSymfonyLibDir().'/vendor/lime/lime.php';
$databaseManager = new sfDatabaseManager($configuration);
$con = Propel::getConnection();

$con->beginTransaction();
try
{
  // prepare test environment
  sfAssetFolderPeer::doDeleteAll();
  sfAssetPeer::doDeleteAll();
  sfConfig::set('app_sfAssetsLibrary_upload_dir', 'mediaTEST');
  $f = new sfAssetFolder();
  $f->setName(sfConfig::get('app_sfAssetsLibrary_upload_dir'));
  sfAssetFolderPeer::createRoot($f);
  $f->save();

  // run the test
  $t = new lime_test(13, array('options' => new lime_output_color(), 'error_reporting' => true));
  $t->diag('sfAssetFolderPeer');

  $sfAssetFolder = sfAssetFolderPeer::retrieveByPath(sfConfig::get('app_sfAssetsLibrary_upload_dir', 'mediaTEST'));
  $t->ok($sfAssetFolder->isRoot(), 'retrieveByPath() retrieves root from app_sfAssetsLibrary_upload_dir string');

  $sfAssetFolder = sfAssetFolderPeer::retrieveByPath();
  $t->ok($sfAssetFolder->isRoot(), 'retrieveByPath() retrieves root from empty string');

  $sfAssetFolder = sfAssetFolderPeer::createFromPath(sfConfig::get('app_sfAssetsLibrary_upload_dir', 'mediaTEST') . DIRECTORY_SEPARATOR . 'simple');
  $t->isa_ok($sfAssetFolder, 'sfAssetFolder', 'createFromPath() creates a sfAssetFolder from simple string');
  $t->isa_ok($sfAssetFolder->retrieveParent(), 'sfAssetFolder', 'createFromPath() from simple string has a parent');
  $t->ok($sfAssetFolder->retrieveParent()->isRoot(), 'createFromPath() creates a root child from simple string');

  $sfAssetFolder2 = sfAssetFolderPeer::createFromPath(sfConfig::get('app_sfAssetsLibrary_upload_dir', 'mediaTEST') . DIRECTORY_SEPARATOR . 'simple' . DIRECTORY_SEPARATOR . 'subfolder');
  $t->isa_ok($sfAssetFolder2, 'sfAssetFolder', 'createFromPath() creates a sfAssetFolder from simple string');
  $t->is($sfAssetFolder2->retrieveParent()->getId(), $sfAssetFolder->getId(), 'createFromPath() from simple string parent is correct');

  $sfAssetFolder = sfAssetFolderPeer::createFromPath(sfConfig::get('app_sfAssetsLibrary_upload_dir', 'mediaTEST')  . DIRECTORY_SEPARATOR . 'second' . DIRECTORY_SEPARATOR . 'subfolder');
  $t->ok($sfAssetFolder instanceof sfAssetFolder, 'createFromPath() creates a sfAssetFolder from simple string');
  $t->ok($sfAssetFolder->retrieveParent() instanceof sfAssetFolder, 'createFromPath() from composed string has a parent');
  $t->ok($sfAssetFolder->retrieveParent()->retrieveParent()->isRoot(), 'createFromPath() creates a root child from composed string');

  $sfAssetFolder = sfAssetFolderPeer::createFromPath('third' . DIRECTORY_SEPARATOR . 'subfolder');
  $t->ok($sfAssetFolder instanceof sfAssetFolder, 'createFromPath() creates a sfAssetFolder from simple string');
  $t->ok($sfAssetFolder->retrieveParent() instanceof sfAssetFolder, 'createFromPath() from composed string has a parent');
  $t->ok($sfAssetFolder->retrieveParent()->retrieveParent()->isRoot(), 'createFromPath() creates a root child from composed string');
}
catch (Exception $e)
{
  // do nothing
}

// reset DB
$con->rollBack();
