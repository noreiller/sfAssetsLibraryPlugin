<?php

/**
 * PluginsfAssetFolderPeer
 *
 * @package sfAssetsLibraryPlugin
 * @author  Massimiliano Arione
 * @version SVN: $Id: PluginsfAssetFolderPeer.php 30911 2010-09-15 09:37:44Z garak $
 */
class PluginsfAssetFolderPeer extends BasesfAssetFolderNestedSetPeer
{
  /**
   * Recursively creates parent folders
   *
   * @param string $path
   * @return sfAssetFolder
   */
  public static function createFromPath($path)
  {
    $path = self::cleanPath($path, DIRECTORY_SEPARATOR);
    list($parent_path, $name) = sfAssetsLibraryTools::splitPath($path);
    if (!$parent_folder = self::retrieveByPath($parent_path))
    {
      $parent_folder = self::createFromPath($parent_path);
      $parent_folder->save();
    }
    $folder = new sfAssetFolder();
    $folder->setName($name);
    $folder->setRelativePath($path);
    $folder->insertAsLastChildOf($parent_folder);
    $folder->save();

    return $folder;
  }

  /**
   * Retrieves folder by relative path
   *
   * @param string $path
   * @param string $separator
   * @return sfAssetFolder
   */
  public static function retrieveByPath($path = '', $separator = DIRECTORY_SEPARATOR)
  {
    $path = self::cleanPath($path, $separator);
    $c = new Criteria();
    $c->add(self::RELATIVE_PATH, $path ? $path : null);

    return self::doSelectOne($c);
  }

  /**
   * Gives an options array with all folders
   *
   * @param bool $includeRoot
   * @return array options array
   */
  public static function getAllPaths($includeRoot = true)
  {
    $root = self::retrieveRoot();
    $allDirs = $root->getDescendants();
    if ($includeRoot)
    {
      $allDirs[] = $root;
    }
    $allDirs = self::sortByName($allDirs);
    $options = array();
    foreach ($allDirs as $folder)
    {
      $options[$folder->getRelativePath()] = $folder->getRelativePath();
    }

    return $options;
  }

  /**
   * @param  string $folder
   * @return array
   */
  public static function getAllNonDescendantsPaths($folder)
  {
    $c = new Criteria();
    $criterion1 = $c->getNewCriterion(self::TREE_LEFT, $folder->getLeftValue(), Criteria::LESS_THAN);
    $criterion2 = $c->getNewCriterion(self::TREE_RIGHT, $folder->getRightValue(), Criteria::GREATER_THAN);
    $criterion1->addOr($criterion2);
    $c->add($criterion1);

    return self::getAllPaths(true, $c);
  }

  /**
   * @param  string   $folder
   * @return Criteria
   */
  public static function getAllNonDescendantsPathsCriteria($folder)
  {
    $c = new Criteria();
    $criterion1 = $c->getNewCriterion(self::TREE_LEFT, $folder->getLeftValue(), Criteria::LESS_THAN);
    $criterion2 = $c->getNewCriterion(self::TREE_RIGHT, $folder->getRightValue(), Criteria::GREATER_THAN);
    $criterion1->addOr($criterion2);
    $c->add($criterion1);

    return $c;
  }

   /**
    * get a criteria for all folders except one
    * @param  sfAssetFolder $folder folder to exclude
    * @return Criteria
    */
   public static function getAllPathsButOneCriteria($folder)
   {
     $c = new Criteria();
     $c->add(self::ID, $folder->getId(), Criteria::NOT_EQUAL);
     return $c;
   }

  /**
   * sort dirs by name
   * @param  array $dirs
   * @return array
   */
  public static function sortByName(array $dirs = array())
  {
    $sortedDirs = array();
    foreach ($dirs as $dir)
    {
      $key = strtolower($dir->getRelativePath());
      if (array_key_exists($key, $sortedDirs))
      {
        $key .= time();
      }
      $sortedDirs[$key] = $dir;
    }
    ksort($sortedDirs);

    return $sortedDirs;
  }

  /**
   * sort dirs by date
   * @param  array $dirs
   * @return array
   */
  public static function sortByDate(array $dirs = array())
  {
    $sortedDirs = array();
    foreach ($dirs as $dir)
    {
      $sortedDirs[$dir->getCreatedAt('U')] = $dir;
    }
    krsort($sortedDirs);

    return $sortedDirs;
  }

  /**
   * Sanitize path
   *
   * @param  string $path
   * @param  string $separator
   * @return string
   */
  public static function cleanPath($path, $separator = '/')
  {
    $path = trim($path, $separator);
    $root_name = sfConfig::get('app_sfAssetsLibrary_upload_dir', 'media');
    if (!$path)
    {
      $path = $root_name;
    }
    elseif (strpos($path, $root_name) !== 0)
    {
      $path = $root_name . $separator . $path;
    }

    return $path;
  }

  /**
   * Calculate total size of files
   * @param  array   $files
   * @return integer
   */
  public static function countFilesSize($files)
  {
    $totalSize = 0;
    foreach ($files as $file)
    {
      $totalSize += $file->getFilesize();
    }

    return $totalSize;
  }
}
