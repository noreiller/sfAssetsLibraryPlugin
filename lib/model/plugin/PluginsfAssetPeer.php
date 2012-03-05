<?php

/**
 * PluginsfAssetPeer
 *
 * @package sfAssetsLibraryPlugin
 * @author  Massimiliano Arione
 * @version SVN: $Id: PluginsfAssetPeer.php 30911 2010-09-15 09:37:44Z garak $
 */
class PluginsfAssetPeer extends BasesfAssetPeer
{
  /**
   * check if file exists in folder
   * @param  integer $folderId
   * @param  string  $filename
   * @return boolean
   */
  public static function exists($folderId, $filename)
  {
    $c = new Criteria();
    $c->add(self::FOLDER_ID, $folderId);
    $c->add(self::FILENAME, $filename);

    return self::doCount($c) > 0 ? true : false;
  }

  /**
   * Retrieves a sfAsset object from a relative URL like
   *    /medias/foo/bar.jpg
   * i.e. the kind of URL returned by $sf_asset->getUrl()
   */
  public static function retrieveFromUrl($url)
  {
    $url = sfAssetFolderPeer::cleanPath($url, '/');
    list($relPath, $filename) = sfAssetsLibraryTools::splitPath($url, '/');

    $c = new Criteria();
    $c->add(self::FILENAME, $filename);
    $c->addJoin(self::FOLDER_ID, sfAssetFolderPeer::ID);
    $c->add(sfAssetFolderPeer::RELATIVE_PATH, $relPath ?  $relPath : null);

    return self::doSelectOne($c);
  }

  /**
   * get pager for assets
   * @param  array   $params
   * @param  string  $sort
   * @param  integer $page
   * @param  integer $size
   * @return sfPager
   */
  public static function getPager(array $params, $sort = 'name', $page = 1, $size = 20)
  {
    $c = self::search($params, $sort);

    $pager = new sfPropelPager('sfAsset', $size);
    $pager->setCriteria($c);
    $pager->setPage($page);
    $pager->setPeerMethod('doSelectJoinFolder');
    $pager->init();

    return $pager;
  }

  /**
   * process search
   * @param  array    $params
   * @param  string   $sort
   * @return Criteria
   */
  protected static function search(array $params, $sort = 'name')
  {
    $c = new Criteria();

    if (isset($params['folder_id']) && $params['folder_id'] !== '')
    {
      if (null!= $folder = sfAssetFolderPeer::retrieveByPK($params['folder_id']))
      {
        $c->addJoin(self::FOLDER_ID, sfAssetFolderPeer::ID);
        $c->add(sfAssetFolderPeer::TREE_LEFT, $folder->getTreeLeft(), Criteria::GREATER_EQUAL);
        $c->add(sfAssetFolderPeer::TREE_RIGHT, $folder->getTreeRIGHT(), Criteria::LESS_EQUAL);
      }
    }
    if (isset($params['filename']['is_empty']))
    {
      $criterion = $c->getNewCriterion(self::FILENAME, '');
      $criterion->addOr($c->getNewCriterion(self::FILENAME, null, Criteria::ISNULL));
      $c->add($criterion);
    }
    elseif (isset($params['filename']['text']) && $params['filename']['text'] !== '')
    {
      $c->add(self::FILENAME, '%' . trim($params['filename']['text'], '*%') . '%', Criteria::LIKE);
    }
    if (isset($params['author']['is_empty']))
    {
      $criterion = $c->getNewCriterion(self::AUTHOR, '');
      $criterion->addOr($c->getNewCriterion(self::AUTHOR, null, Criteria::ISNULL));
      $c->add($criterion);
    }
    elseif (isset($params['author']['text']) && $params['author']['text'] !== '')
    {
      $c->add(self::AUTHOR, '%' . trim($params['author']['text'], '*%') . '%', Criteria::LIKE);
    }
    if (isset($params['copyright']['is_empty']))
    {
      $criterion = $c->getNewCriterion(self::COPYRIGHT, '');
      $criterion->addOr($c->getNewCriterion(self::COPYRIGHT, null, Criteria::ISNULL));
      $c->add($criterion);
    }
    elseif (isset($params['copyright']['text']) && $params['copyright']['text'] !== '')
    {
      $c->add(self::COPYRIGHT, '%' . trim($params['copyright']['text'], '*%') . '%', Criteria::LIKE);
    }
    if (isset($params['created_at']))
    {
      if (isset($params['created_at']['from']) && $params['created_at']['from'] !== array())  // TODO check this
      {
        $criterion = $c->getNewCriterion(self::CREATED_AT, $params['created_at']['from'], Criteria::GREATER_EQUAL);
      }
      if (isset($params['created_at']['to']) && $params['created_at']['to'] !== array())  // TODO check this
      {
        if (isset($criterion))
        {
          $criterion->addAnd($c->getNewCriterion(self::CREATED_AT, $params['created_at']['to'], Criteria::LESS_EQUAL));
        }
        else
        {
          $criterion = $c->getNewCriterion(self::CREATED_AT, $params['created_at']['to'], Criteria::LESS_EQUAL);
        }
      }
      if (isset($criterion))
      {
        $c->add($criterion);
      }
    }
    if (isset($params['description']['is_empty']))
    {
      $criterion = $c->getNewCriterion(self::DESCRIPTION, '');
      $criterion->addOr($c->getNewCriterion(self::DESCRIPTION, null, Criteria::ISNULL));
      $c->add($criterion);
    }
    else if (isset($params['description']) && $params['description'] !== '')
    {
      $c->add(self::DESCRIPTION, '%' . trim($params['description'], '*%') . '%', Criteria::LIKE);
    }

    switch ($sort)
    {
      case 'date':
        $c->addDescendingOrderByColumn(self::CREATED_AT);
        break;
      default:
        $c->addAscendingOrderByColumn(self::FILENAME);
    }

    return $c;
  }

}
