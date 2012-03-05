<?php

if (sfConfig::get('app_sfAssetsLibraryplugin_routes_register', true) && in_array('sfAsset', sfConfig::get('sf_enabled_modules', array())))
{
  $this->dispatcher->connect('routing.load_configuration', array('sfAssetRouting', 'listenToRoutingLoadConfigurationEvent'));
}