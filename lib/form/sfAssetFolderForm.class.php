<?php

/**
 * sfAssetFolder form.
 *
 * @package    symfony
 * @subpackage form
 * @author     Massimiliano Arione <garakkio@gmail.com>
 */
class sfAssetFolderForm extends BasesfAssetFolderForm
{
  public function configure()
  {
    // hide some fields
    unset($this['tree_left'], $this['tree_right'], $this['relative_path'],
          $this['created_at'], $this['updated_at']);

    // add hidden parent folder
    $this->widgetSchema['parent_folder'] = new sfWidgetFormInputHidden();
    if (!empty($this->options['parent_id']))
    {
      $this->setDefault('parent_folder', $this->options['parent_id']);
    }
    $this->validatorSchema['parent_folder'] = new sfValidatorPropelChoice(array(
      'model' => 'sfAssetFolder',
    ));

    // avoid id conflict for name and parent_folder
    $this->widgetSchema['name']->setIdFormat('create_%s');
    $this->widgetSchema['parent_folder']->setIdFormat('create_%s');

    // check for: correct name, name not equal to "thumbnail"
    $this->validatorSchema['name'] = new sfValidatorAnd(array(
      new sfValidatorRegex(array('pattern' => '/^[a-zA-Z0-9\-\_\.]+$/')),
      new sfValidatorRegex(array(
        'pattern'    => '/^' . sfConfig::get('app_sfAssetsLibrary_thumbnail_dir', 'thumbnail') . '$/',
        'must_match' => false,
      )),
    ));
  }

  /**
   * save
   * @param PropelPDO $con
   */
  protected function doSave($con = null)
  {
    if (null === $con)
    {
      $con = $this->getConnection();
    }
    $this->updateObject();
    $parent = sfAssetFolderPeer::retrieveByPK($this->getValue('parent_folder'));
    $this->getObject()->insertAsLastChildOf($parent);
    $this->getObject()->save($con);
    // embedded forms
    $this->saveEmbeddedForms($con);
  }
}
