<?php
$pageSelector = \Core::make('helper/form/page_selector');
if($isValueInferred) {
	echo $form->hidden($this->field('value'), $value);
	echo $form->select($this->field('value_disabled'), $localeOptions, $value, [
		'disabled' => 1
	]);
}
?>
<small><?php echo t('Page language is determined by its location in the sitemap.') ?></small>

<?php
foreach($availableLocales as $locale) {
	$localeCode = $locale->getLocale();
	$localeText = t($locale->getLanguageText());
	if($localeCode == $value) continue; // skip already selected locale
	if($relations[$localeCode]) {
		$relationOwnerID = $relations[$localeCode]->getCollectionID();
	} else {
		$relationOwnerID = null;
	}
	//TODO get current relationID
?>
<div class="form-group">
	<?=$form->label($this->field($localeCode), $localeText)?>
	<?=$pageSelector->selectPage($this->field($localeCode), $relationOwnerID)?>
</div>
<?php } ?>
