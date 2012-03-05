<?php

/**
 * sfAsset filter form.
 *
 * @package    symfony
 * @subpackage filter
 * @author     Massimiliano Arione <garakkio@gmail.com>
 */
class sfAssetFormFilter extends BasesfAssetFormFilter
{
  public function configure()
  {
    // hide some fields
    unset($this['filesize'], $this['type']);

    // allow empty folder
    $this->widgetSchema['folder_id']->setOption('add_empty', true);

    // created_at as range
    $this->widgetSchema['created_at'] = new sfWidgetFormDateRange(array(
                                                                        'from_date' => new sfWidgetFormDate(),
                                                                        'to_date'   => new sfWidgetFormDate(),
                                                                        'template'  => '%from_date%<br />%to_date%',
                                                                        ));

    // restrict description to simple field, and move it to bottom
    $this->widgetSchema['description'] = new sfWidgetFormInput;
    $this->widgetSchema->moveField('description', sfWidgetFormSchema::LAST);

    // formatter (see sfWidgetFormSchemaFormatterAsset.class.php)
    $this->widgetSchema->setFormFormatterName('asset');
  }
}
