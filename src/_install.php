<?php
//@@licence@@

if (!defined('DC_CONTEXT_ADMIN')) { return; }
 
$m_version = $core->plugins->moduleInfo('feemeak','version');
 
$i_version = $core->getVersion('feemeak');
 
if (version_compare($i_version,$m_version,'>=')) {
	return;
}
 
# Création du setting (s'il existe, il ne sera pas écrasé)
$settings = new dcSettings($core,null);
$settings->addNameSpace('feemeak');
$settings->feemeak->put('feemeak_feed_icon_url','','string','feemeak feed icon URL',false,true);
$settings->feemeak->put('feemeak_enabled',false,'boolean','Enable feemeak for all pages',false,true);

$core->setVersion('feemeak',$m_version);
?>