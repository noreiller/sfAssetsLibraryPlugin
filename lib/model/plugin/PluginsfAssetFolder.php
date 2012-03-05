<?php

/**
 * PluginsfAssetFolder
 *
 * @package sfAssetsLibraryPlugin
 * @author  Massimiliano Arione
 * @version SVN: $Id: PluginsfAssetFolder.php 33042 2011-09-16 15:05:57Z garak $
 */
class PluginsfAssetFolder extends BasesfAssetFolderNestedSet
{
  /**
   * @return string
   */
  public function getFullPath()
  {
    return sfAssetsLibraryTools::getMediaDir(true) . $this->getRelativePath();
  }

  /**
   * Gives the URL for the given folder
   *
   * @return string
   */
  public function getUrl()
  {
    return sfAssetsLibraryTools::getMediaDir() . $this->getRelativePath();
  }

  /**
   * Folder physically exists
   *
   * @return bool
   */
  public function exists()
  {
    return is_dir($this->getRelativePath()) && is_writable($this->getRelativePath());
  }

  /**
   * Checks if a name already exists in the list of subfolders to a folder
   *
   * @param string $name A folder name
   * @return bool
   */
  public function hasSubFolder($name)
  {
    foreach ($this->getChildren() as $subfolder)
    {
      if ($subfolder->getName() == $name)
      {
        return true;
      }
    }
    return false;
  }

  /**
   * @param  sfAssetFolder $folder
   * @return boolean
   */
  public function isDescendantOf(sfAssetFolder $folder)
  {
    return (
         $folder->getLeftValue()  <= $this->getLeftValue()
      && $folder->getRightValue() >= $this->getRightValue()
    );
  }

  /**
   * @param  PropelPDO     $con
   * @return sfAssetFolder
   */
  public function retrieveParentIgnoringPooling(PropelPDO $con = null)
  {
    return sfAssetFolderPeer::retrieveParent($this, $con);
  }

  /**
   * Physically creates folder
   *
   * @return bool succes
   */
  public function create()
  {
    list ($base, $name) = sfAssetsLibraryTools::splitPath($this->getRelativePath());

    return sfAssetsLibraryTools::mkdir($name, $base);
  }

  /**
   * @param  string
   * @return boolean
   */
  public function hasAsset($assetName)
  {
    $c = new Criteria();
    $c->add(sfAssetPeer::FILENAME, $assetName);

    return $this->countsfAssets($c) > 0;
  }

  /**
   * @return array
   */
  public function getAssetsWithFilenames()
  {
    $c = new Criteria();
    $c->add(sfAssetPeer::FOLDER_ID, $this->getId());
    $assets = sfAssetPeer::doSelect($c);
    $filenames = array();
    foreach ($assets as $asset)
    {
      $filenames[$asset->getFilename()] = $asset;
    }

    return $filenames;
  }

  /**
   * @return array
   */
  public function getSubfoldersWithFolderNames()
  {
    $foldernames = array();
    foreach ($this->getChildren() as $folder)
    {
      $foldernames[$folder->getName()] = $folder;
    }

    return $foldernames;
  }

  /**
   * Synchronize with a physical folder
   *
   * @param string  $base_folder base folder path
   * @param boolean $verbose If true, every file or database operation will issue an alert in STDOUT
   * @param boolean $removeOrphanAssets If true, database assets with no associated file are removed
   * @param boolean $removeOrphanFolders If true, database folders with no associated directory are removed
   */
  public function synchronizeWith($baseFolder, $verbose = true, $removeOrphanAssets = false, $removeOrphanFolders = false)
  {
    if (!is_dir($baseFolder))
    {
      throw new sfAssetException(sprintf('%s is not a directory', $baseFolder));
    }

    $files = sfFinder::type('file')->maxdepth(0)->ignore_version_control()->in($baseFolder);
    $assets = $this->getAssetsWithFilenames();
    foreach ($files as $file)
    {
      if (!array_key_exists(basename($file), $assets))
      {
        // File exists, asset does not exist: create asset
        $sfAsset = new sfAsset();
        $sfAsset->setFolderId($this->getId());
        $sfAsset->create($file, false);
        $sfAsset->save();
        if ($verbose)
        {
          sfAssetsLibraryTools::log(sprintf("Importing file %s", $file), 'green');
        }
      }
      else
      {
        // File exists, asset exists: do nothing
        unset($assets[basename($file)]);
      }
    }

    foreach ($assets as $name => $asset)
    {
      if ($removeOrphanAssets)
      {
        // File does not exist, asset exists: delete asset
        $asset->delete();
        if ($verbose)
        {
          sfAssetsLibraryTools::log(sprintf("Deleting asset %s", $asset->getUrl()), 'yellow');
        }
      }
      else
      {
        if ($verbose)
        {
          sfAssetsLibraryTools::log(sprintf("Warning: No file for asset %s", $asset->getUrl()), 'red');
        }
      }
    }

    $dirs = sfFinder::type('dir')->maxdepth(0)->discard(sfConfig::get('app_sfAssetsLibrary_thumbnail_dir', 'thumbnail'))->ignore_version_control()->in($baseFolder);
    $folders = $this->getSubfoldersWithFolderNames();
    foreach ($dirs as $dir)
    {
      list(,$name) = sfAssetsLibraryTools::splitPath($dir);
      if (!array_key_exists($name, $folders))
      {
        // dir exists in filesystem, not in database: create folder in database
        $sfAssetFolder = new sfAssetFolder();
        $sfAssetFolder->insertAsLastChildOf($this->reload());
        $sfAssetFolder->setName($name);
        $sfAssetFolder->save();
        if ($verbose)
        {
          sfAssetsLibraryTools::log(sprintf("Importing directory %s", $dir), 'green');
        }
      }
      else
      {
        // dir exists in filesystem and database: look inside
        $sfAssetFolder = $folders[$name];
        unset($folders[$name]);
      }
      $sfAssetFolder->synchronizeWith($dir, $verbose, $removeOrphanAssets, $removeOrphanFolders);
    }

    foreach ($folders as $name => $folder)
    {
      if ($removeOrphanFolders)
      {
        $folder->delete(null, true);
        if ($verbose)
        {
          sfAssetsLibraryTools::log(sprintf("Deleting folder %s", $folder->getRelativePath()), 'yellow');
        }
      }
      else
      {
        if ($verbose)
        {
          sfAssetsLibraryTools::log(sprintf("Warning: No directory for folder %s", $folder->getRelativePath()), 'red');
        }
      }
    }

  }

  /**
   * Recursively move assets and folders from $old_path to $new_path
   *
   * @param string $old_path
   * @param string $new_path
   * @return bool success
   */
  static public function movePhysically($old_path, $new_path)
  {
    if (!is_dir($new_path) || !is_writable($new_path))
    {
      $old = umask(0);
      mkdir($new_path, 0770);
      umask($old);
    }

    $files = sfFinder::type('file')->maxdepth(0)->in($old_path);
    $success = true;
    foreach ($files as $file)
    {
      $success = rename($file, $new_path . '/' . basename($file)) && $success;
    }
    if ($success)
    {
      $folders = sfFinder::type('dir')->maxdepth(0)->in($old_path);
      foreach ($folders as $folder)
      {
        $new_name = substr($folder, strlen(realpath($old_path)));
        $success = self::movePhysically($folder, $new_path . '/' . $new_name) && $success;
      }
    }
    $success = @rmdir($old_path) && $success;

    return $success;
  }

  /**
   * Move under a new parent
   *
   * @param sfAssetFolder $new_parent
   */
  public function move(sfAssetFolder $new_parent)
  {
    // controls
    if ($this->isRoot())
    {
      throw new sfAssetException('The root folder cannot be moved');
    }
    else if ($new_parent->hasSubFolder($this->getName()))
    {
      throw new sfAssetException('The target folder "%folder%" already contains a folder named "%name%". The folder has not been moved.', array('%folder%' => $new_parent, '%name%' => $this->getName()));
    }
    else if ($new_parent->isDescendantOf($this))
    {
      throw new sfAssetException('The target folder cannot be a subfolder of moved folder. The folder has not been moved.');
    }
    else if ($this->retrieveParent() !== $new_parent->getId())
    {
      $descendants = $this->getDescendants();
      $old_path = $this->getFullPath();

      $this->moveToLastChildOf($new_parent);
      // Update relative path
      $this->save();

      // move its assets
      self::movePhysically($old_path, $this->getFullPath());

      foreach ($descendants as $descendant)
      {
        // Update relative path
        $descendant->save();
      }
    }
    // else: nothing to do
  }

  /**
   * Change folder name
   *
   * @param string $name
   */
  public function rename($name)
  {
    if ($this->retrieveParent()->hasSubFolder($name))
    {
      throw new sfAssetException('The parent folder already contains a folder named "%name%". The folder has not been renamed.', array('%name%' => $name));
    }
    else if ($name !== $this->getName())
    {
      if (sfAssetsLibraryTools::sanitizeName($name) != $name)
      {
        throw new sfAssetException('The target folder name "%name%" contains incorrect characters. The folder has not be renamed.', array('%name%' => $name));
      }
      $old_path = $this->getFullPath();
      $this->setName($name);
      $this->save();

      // move its assets
      self::movePhysically($old_path, $this->getFullPath());

      foreach ($this->getDescendants() as $descendant)
      {
        $descendant->save();
      }
    }
    // else: nothing to do
  }

  /**
   * Also delete all contents
   *
   * @param  PropelPDO $con
   * @param  boolean   $force If true, do not throw an exception if the physical directories cannot be removed
   * @return boolean
   */
  public function delete(PropelPDO $con = null, $force = false)
  {
    $success = true;

    foreach ($this->getDescendants() as $descendant)
    {
      $success = $descendant->delete($con, $force) && $success;
    }

    foreach ($this->getAssets() as $asset)
    {
      $success = $asset->delete() && $success;
    }

    // Remove thumbnail subdir
    $success = rmdir(sfAssetsLibraryTools::getThumbnailDir($this->getFullPath())) && $success;
    // Remove dir itself
    $success = rmdir($this->getFullPath()) && $success;
    if ($success || $force)
    {
      parent::delete($con);
    }
    else
    {
      throw new sfAssetException('Impossible to delete folder "%name%"', array('%name%' => $this->getName()));
    }

    return $success;
  }

  /**
   * @return string
   */
  public function getParentPath()
  {
    if ($this->isRoot())
    {
      throw new sfException('Root node has no parent path');
    }
    $path = $this->getRelativePath();

    return trim(substr($path, 0, strrpos($path, '/')), '/');
  }

  /**
   * @param  PropelPDO $con
   * @return integer
   */
  public function save(PropelPDO $con = null)
  {
    if (!$this->isColumnModified(sfAssetFolderPeer::RELATIVE_PATH))
    {
      if ($this->hasParent())
      {
        $this->setRelativePath($this->retrieveParentIgnoringPooling()->getRelativePath().'/'.$this->getName());
      }
      else
      {
        $this->setRelativePath($this->getName());
      }
    }
    // physical existence
    if (!$this->exists())
    {
      if (!$this->create())
      {
        throw new sfAssetException('Impossible to create folder "%name%"', array('%name%' => $this->getRelativePath()));
      }
    }

    return parent::save($con);
  }

  /**
   * get files of folder, sorted
   * @param  array   $dirs
   * @param  string  $sortOrder
   * @param  boolean $imgOnly
   * @return array
   */
  public function getSortedFiles(array $dirs, $sortOrder, $imgOnly = false)
  {
    $c = new Criteria();
    $c->add(sfAssetPeer::FOLDER_ID, $this->getId());
    switch ($sortOrder)
    {
      case 'date':
        $dirs = sfAssetFolderPeer::sortByDate($dirs);
        $c->addDescendingOrderByColumn(sfAssetPeer::CREATED_AT);
        break;
      default:
        $dirs = sfAssetFolderPeer::sortByName($dirs);
        $c->addAscendingOrderByColumn(sfAssetPeer::FILENAME);
    }
    if ($imgOnly)
    {
      $c->add(sfAssetPeer::TYPE, 'image');
    }

    return sfAssetPeer::doSelect($c);
  }

}
