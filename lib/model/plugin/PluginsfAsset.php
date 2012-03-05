<?php

/**
 * PluginsfAsset
 *
 * @package sfAssetsLibraryPlugin
 * @author  Massimiliano Arione
 * @version SVN: $Id: PluginsfAsset.php 30911 2010-09-15 09:37:44Z garak $
 */
class PluginsfAsset extends BasesfAsset
{
  /**
   * Get folder relative path
   *
   * @return string
   */
  public function getFolderPath()
  {
    $folder = $this->getFolder();
    if (!$folder)
    {
      throw new Exception(sprintf('You must set define the folder for an asset prior to getting its path. Asset %d doesn\'t have a folder yet.', $this->getFilename()));
    }
    return $folder->getRelativePath();
  }

  /**
   * Gives the file relative path
   *
   * @return string
   */
  public function getRelativePath()
  {
    return $this->getFolderPath() . '/' . $this->getFilename();
  }

  /**
   * Gives full filesystem path
   *
   * @param string $thumbnail_type
   * @return string
   */
  public function getFullPath($thumbnail_type = 'full')
  {
    return sfAssetsLibraryTools::getThumbnailPath($this->getFolderPath(), $this->getFilename(), $thumbnail_type);
  }

  public function setFilename($filename)
  {
    $filename = sfAssetsLibraryTools::sanitizeName($filename);

    return parent::setFilename($filename);
  }

  /**
   * Gives the URL for the given thumbnail
   *
   * @param  string  $thumbnail_type
   * @param  string  $relative_path
   * @param  boolean $pdf
   * @return string
   */
  public function getUrl($thumbnail_type = 'full', $relative_path = null, $pdf = false)
  {
    if (is_null($relative_path))
    {
      if (!$folder = $this->getFolder())
      {
        throw new Exception(sprintf('You must set define the folder for an asset prior to getting its path. Asset %d doesn\'t have a folder yet.', $this->getFilename()));
      }
      $relative_path = $folder->getRelativePath();
    }
    $url = sfAssetsLibraryTools::getMediaDir();
    if ($thumbnail_type == 'full')
    {
      $url .= $relative_path . '/' . $this->getFilename();
    }
    else
    {
      $url .= sfAssetsLibraryTools::getThumbnailDir($relative_path, '/') . $thumbnail_type . '_' . $this->getFilename();
    }
    if ($pdf)
    {
      $url = substr($url, 0, -3) . 'jpg';
    }

    return $url;
  }

  public function autoSetType()
  {
    $this->setType(sfAssetsLibraryTools::getType($this->getFullPath()));
  }

  /**
   * @return boolean
   */
  public function isImage()
  {
    return $this->getType() === 'image';
  }

  /**
   * @return boolean
   */
  public function isPdf()
  {
    return $this->getType() === 'pdf';
  }

  /**
   * @return boolean
   */
  public function supportsThumbnails()
  {
    return ($this->isImage() || $this->isPdf()) && class_exists('sfThumbnail');
  }

  /**
   * Physically creates asset
   *
   * @param string  $assetPath path to the asset original file
   * @param boolean $move      do move or just copy ?
   * @param boolean $move      check duplicate?
   */
  public function create($assetPath, $move = true, $checkDuplicate = true)
  {
    if (!is_file($assetPath))
    {
      throw new sfAssetException('Asset "%asset%" not found', array('%asset%' => $assetPath));
    }
    // calculate asset properties
    if (!$this->getFilename())
    {
      list(, $filename) = sfAssetsLibraryTools::splitPath($assetPath);
      $this->setFilename($filename);
    }

    // check folder
    if (!$this->getFolder()->exists())
    {
      $this->getFolder()->create();
    }
    // check if a file with this name already exists
    elseif ($checkDuplicate && sfAssetPeer::exists($this->getFolder()->getId(), $this->getFilename()))
    {
      $this->setFilename(time() . $this->getFilename());
    }

    $this->setFilesize((int) filesize($assetPath) / 1024);
    $this->autoSetType();
    if (sfConfig::get('app_sfAssetsLibrary_check_type', false) && !in_array($this->getType(), sfConfig::get('app_sfAssetsLibrary_types', array('image', 'txt', 'archive', 'pdf', 'xls', 'doc', 'ppt'))))
    {
      throw new sfAssetException('Filetype "%type%" not allowed', array('%type%' => $this->getType()));
    }

    $ok = $move ? @rename($assetPath, $this->getFullPath()) : @copy($assetPath, $this->getFullPath());
    if (!$ok)
    {
      throw new sfAssetException('A problem occurred during while saving "%file%"', array('%file%' =>  $this->getFullPath()));
    }

    if ($this->supportsThumbnails())
    {
      sfAssetsLibraryTools::createThumbnails($this->getFolderPath(), $this->getFilename(), $this->isPdf());
    }
  }

  /**
   * @return array
   */
  public function getFilepaths()
  {
    $filepaths = array('full' => $this->getFullPath());
    if ($this->isImage() && $this->supportsThumbnails())
    {
      // Add path to the thumbnails
      foreach (sfConfig::get('app_sfAssetsLibrary_thumbnails', array(
        'small' => array('width' => 84, 'height' => 84, 'shave' => true),
        'large' => array('width' => 194, 'height' => 152)
        )) as $key => $params)
      {
        $filepaths[$key] = $this->getFullPath($key);
      }
    }

    return $filepaths;
  }

  /**
   * Change asset directory and/or name
   *
   * @param sfAssetFolder $newFolder
   * @param string        $newFilename
   */
  public function move(sfAssetFolder $newFolder, $newFilename = null)
  {
    if (sfAssetPeer::exists($newFolder->getId(), $newFilename ? $newFilename : $this->getFilename()))
    {
      throw new sfAssetException('The target folder "%folder%" already contains an asset named "%name%". The asset has not been moved.', array('%folder%' => $newFolder->getName(), '%name%' => $newFilename ? $newFilename : $this->getFilename()));
    }
    $oldFilepaths = $this->getFilepaths();
    if ($newFilename)
    {
      if (sfAssetsLibraryTools::sanitizeName($newFilename) != $newFilename)
      {
        throw new sfAssetException('The filename "%name%" contains incorrect characters. The asset has not be altered.', array('%name%' => $newFilename));
      }
      $this->setFilename($newFilename);
    }
    $this->setFolderId($newFolder->getId());
    $success = true;
    foreach ($oldFilepaths as $type => $filepath)
    {
      $success = rename($filepath, $this->getFullPath($type)) && $success;
    }
    if (!$success)
    {
      throw new sfAssetException('Some or all of the file operations failed. It is possible that the moved asset or its thumbnails are missing.');
    }
  }

  /**
   * Physically remove assets
   * @return boolean
   */
  public function destroy()
  {
    $success = true;
    foreach ($this->getFilepaths() as $filepath)
    {
      $success = unlink($filepath) && $success;
    }

    return $success;
  }

  /**
   * @param  PropelPDO $con
   * @return boolean
   */
  public function delete(PropelPDO $con = null)
  {
    $success = $this->destroy();
    parent::delete($con);

    return $success;
  }

}
