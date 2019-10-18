<?php
$assetLibrary = \Core::make('helper/concrete/asset_library');

echo $form->select($this->field('value'), $localeOptions, $value, [
	$isValueInferred ? 'disabled' : 'enabled'
]);
?>

<?php
foreach($availableLocales as $locale) {
	if($locale->getLocale() == $value) continue; // skip already selected locale

	//TODO get current relationID
?>
<div class="form-group">
	<?=$form->label($this->field($locale->getLocale()), t($locale->getLanguageText()))?>
	<?=$assetLibrary->file('ccm-b-file', $this->field($locale->getLocale()), t('Choose File'), $link_fID)?>
</div>
<?php } ?>