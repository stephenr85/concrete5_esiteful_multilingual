<?php
$pageSelector = \Core::make('helper/form/page_selector');


echo $form->select($this->field('value'), $localeOptions, $value, [
	$isValueInferred ? 'disabled' : 'enabled'
]);
?>
<small><?php echo t('Page language is determined by its location in the sitemap.') ?></small>

<?php
foreach($availableLocales as $locale) {
	if($locale->getLocale() == $value) continue; // skip already selected locale

	//TODO get current relationID
?>
<div class="form-group">
	<?=$form->label($this->field($locale->getLocale()), t($locale->getLanguageText()))?>
	<?=$pageSelector->selectPage($this->field($locale->getLocale()), isset($link_cID) ? $link_cID : null)?>
</div>
<?php } ?>
