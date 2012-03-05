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

  $t = new lime_test(5, array('options' => new lime_output_color(), 'error_reporting' => true));
  $t->diag('sfAssetPeer');

  $t->is(sfAssetPeer::retrieveFromUrl(sfAssetFolderPeer::retrieveRoot()->getRelativePath() . '/filename.jpg'), null, 'sfAssetPeer::retrieveFromUrl() returns null when a URL is not found');
  $t->is(sfAssetPeer::exists(sfAssetFolderPeer::retrieveRoot()->getId(), 'filename.jpg'), false, 'sfAssetPeer::exists() returns false when an asset is not found');

  $sfAsset = new sfAsset();
  $sfAsset->setFolder(sfAssetFolderPeer::retrieveRoot());
  $sfAsset->setFilename('filename.jpg');
  $sfAsset->save($con);

  $t->is(sfAssetPeer::retrieveFromUrl(sfAssetFolderPeer::retrieveRoot()->getRelativePath() . '/filename.jpg')->getId(), $sfAsset->getId(), 'sfAssetPeer::retrieveFromUrl() finds an asset from its URL');
  $t->is(sfAssetPeer::retrieveFromUrl($sfAsset->getUrl())->getId(), $sfAsset->getId(), 'sfAssetPeer::retrieveFromUrl() finds an asset from the result of `getUrl()`');
  $t->is(sfAssetPeer::exists(sfAssetFolderPeer::retrieveRoot()->getId(), 'filename.jpg'), true, 'sfAssetPeer::exists() returns true when an asset is found');
}
catch (Exception $e)
{
  echo $e->getMessage();
}

// reset DB
$con->rollBack();
