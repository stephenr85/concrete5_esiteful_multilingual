<?php
$assetLibrary = \Core::make('helper/concrete/asset_library');

echo $form->select($this->field('value'), $localeOptions, $value, [
	($isValueInferred ? 'disabled' : 'enabled') => 1
]);
?>

<?php
foreach($availableLocales as $locale) {
	$localeCode = $locale->getLocale();
	$localeText = t($locale->getLanguageText());
	if($localeCode == $value) continue; // skip already selected locale
	if($relations[$localeCode]) {
		$relationOwnerID = $relations[$localeCode]->getFileID();
	} else {
		$relationOwnerID = null;
	}
	//TODO get current relationID
?>
<div class="form-group">
	<?=$form->label($this->field($localeCode), $localeText)?>
	<?=$assetLibrary->file($this->field($localeCode), $this->field($localeCode), t('Choose File'), $relationOwnerID)?>
</div>
<?php } ?>