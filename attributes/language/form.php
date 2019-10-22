<?php defined('C5_EXECUTE') or die("Access Denied.");

echo $form->hidden($this->field('relationID'), $relationID);

$this->controller->inc('form_'.$akcHandle.'.php', get_defined_vars());