<?php

/**
 * sfAssetFolder move form.
 *
 * @package    symfony
 * @subpackage form
 * @author     Massimiliano Arione <garakkio@gmail.com>
 */
class sfAssetFolderMoveForm extends BasesfAssetFolderForm
{
  public function configure()
  {
    // remove unneeded fields
    unset($this['name'], $this['tree_left'], $this['tree_right'], $this['relative_path'],
          $this['created_at'], $this['updated_at']);

    // add parent folder select
    $this->widgetSchema['parent_folder'] = new sfWidgetFormPropelChoice(array('model' => 'sfAssetFolder',
                                                                              'criteria' => sfAssetFolderPeer::getAllNonDescendantsPathsCriteria($this->getObject())));
    $this->validatorSchema['parent_folder'] = new sfValidatorPropelChoice(array('model' => 'sfAssetFolder',
                                                                                'column' => 'id',
                                                                                'required' => true));

    // avoid id conflict for id
    $this->widgetSchema['id']->setIdFormat('move_%s');
  }
}
