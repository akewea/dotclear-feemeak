<?php
//@@licence@@

$core->addBehavior('adminBlogPreferencesForm',array('FeeMeakBehaviors','adminBlogPreferencesForm'));
$core->addBehavior('adminBeforeBlogSettingsUpdate',array('FeeMeakBehaviors','adminBeforeBlogSettingsUpdate'));

class FeeMeakBehaviors
{
	public static function adminBlogPreferencesForm($core,$settings)
	{
		echo
		'<fieldset><legend>FeeMeak</legend>'.
		'<p><label class="classic">'.
		form::checkbox('feemeak_enabled','1',$settings->feemeak->feemeak_enabled).
		__('Enable FeeMeak').'</label></p>'.
		'</fieldset>';
	}
	
	public static function adminBeforeBlogSettingsUpdate($settings)
	{
		$settings->addNameSpace('feemeak');
		$settings->feemeak->put('feemeak_enabled',!empty($_POST['feemeak_enabled']),'boolean');
	}
}
?>