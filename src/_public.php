<?php
//@@licence@@

if (!defined('DC_RC_PATH')) { return; }

$core->url->register('FeeMeak','feemeak','^feed/feemeak/(.*)$',array('FeeMeak','feed'));

$core->tpl->addBlock('FeeMeakPhotos',array('FeeMeak','FeeMeakPhotos'));
$core->tpl->addBlock('FeeMeakEntryNext',array('FeeMeak','FeeMeakEntryNext'));
$core->tpl->addBlock('FeeMeakEntryPrevious',array('FeeMeak','FeeMeakEntryPrevious'));
$core->tpl->addValue('FeeMeakFeedURL',array('FeeMeak','FeeMeakFeedURL'));
$core->tpl->addValue('FeeMeakFeedIcon',array('FeeMeak','FeeMeakFeedIcon'));
$core->tpl->addValue('FeeMeakPhotoURL',array('FeeMeak','FeeMeakPhotoURL'));
$core->tpl->addValue('FeeMeakPhotoTitle',array('FeeMeak','FeeMeakPhotoTitle'));
$core->tpl->addValue('FeeMeakPhotoName',array('FeeMeak','FeeMeakPhotoName'));

$core->addBehavior('publicHeadContent',array('FeeMeak','publicHeadContent'));

class FeeMeak extends dcUrlHandlers
{

	const CONTINUE_STR = '/feemeak::continue';

	public static function feed($args)
	{
		$params = array();
			
		//$mime = 'text/plain'; // for debug
		$mime = 'application/xml';
			
		global $_ctx;
		global $core;
			
		$posts = null;
			
		$nav = true;
		if (preg_match('#^(.+)(?:'.self::CONTINUE_STR.'$)?$#U',$args,$m))
		{
			# Post from its URL
			$params['post_url'] = $m[1];
			$params['post_type'] = '';
			if(!preg_match('#'.self::CONTINUE_STR.'$#',$args)){
				$nav = false;
			}
		}
		elseif (preg_match('#^$#',$args,$m))
		{
			# No URL specified : blog first post
			$params['limit'] = 1;
			$params['post_type'] = 'post';
		}
		else
		{
			self::p404();
		}
			
			
		$posts = $core->blog->getPosts($params);
		$_ctx->posts = $posts;
		if ($posts->isEmpty()) {
			self::p404();
		}
			
		// ### On parcours les posts pour remonter les images
			
		$p_url = $core->blog->settings->system->public_url;
		$p_site = preg_replace('#^(.+?//.+?)/(.*)$#','$1',$core->blog->url);
		$p_root = $core->blog->public_path;
			
		$pattern = '(?:'.preg_quote($p_site,'/').')?'.preg_quote($p_url,'/');
		$alt_pattern = '.+?(alt="(.*?)")*';
		$pattern = sprintf('/<img.+?src="%s(.*?\.(?:jpg|gif|png))"'.$alt_pattern.'/msu',$pattern);
			
		$photos = array();
		$subject = $posts->post_excerpt_xhtml.$posts->post_content_xhtml;
		if (preg_match_all($pattern,$subject,$m) > 0)
		{
			foreach ($m[1] as $k => $i) {
				if (self::ImageLookup($p_root,$i, 'o') !== false) {
					$photos[] = array(	"root" => $p_root,
										"img" => $i, 
										"title" => html::decodeEntities($m[3][$k]),
										"name" => self::ImageName($i)
					);
				}
			}
		}
			
		$_ctx->feemeakPhotos = $photos;
			
		if(!$nav){
			$_ctx->posts = null;
		}
			
		header('X-Robots-Tag: '.context::robotsPolicy($core->blog->settings->system->robots_policy,''));
		$core->tpl->setPath($core->tpl->getPath(), dirname(__FILE__).'/default-templates');
		self::serveDocument('feemeak.xml',$mime);
	}


	public static function FeeMeakPhotos($attr,$content)
	{
		return '<?php foreach ($_ctx->feemeakPhotos as $_ctx->feemeakPhoto) : ?>'.$content.'<?php endforeach; '.
		'$_ctx->feemeakPhoto = null; ?>';
	}

	public static function FeeMeakPhotoURL($attr)
	{
		global $_ctx;
		global $core;

		$size = !empty($attr['size']) ? $attr['size'] : 'o';

		if (!preg_match('/^sq|t|s|m|o$/',$size)) {
			$size = 's';
		}

		$p_url = $core->blog->settings->system->public_url;

		$f = $core->tpl->getFilters($attr);
		$src = false;

		return '<?php echo "'.$p_url.'".dirname($_ctx->feemeakPhoto["img"])."/".'.sprintf($f, 'FeeMeak::ImageLookup($_ctx->feemeakPhoto["root"],$_ctx->feemeakPhoto["img"], \''.$size.'\')').'; ?>';
	}

	public static function FeeMeakPhotoTitle($attr)
	{
		global $_ctx;
		global $core;

		$f = $core->tpl->getFilters($attr);

		return '<?php echo '.sprintf($f, '$_ctx->feemeakPhoto["title"]').'; ?>';
	}

	public static function FeeMeakPhotoName($attr)
	{
		global $_ctx;
		global $core;

		$f = $core->tpl->getFilters($attr);
		return '<?php echo '.sprintf($f, '$_ctx->feemeakPhoto["name"]').'; ?>';
	}

	public static function FeeMeakFeedIcon($attr)
	{
		global $_ctx;
		global $core;

		$f = $core->tpl->getFilters($attr);
		return '<?php echo '.sprintf($f, '$core->blog->settings->feemeak->feemeak_feed_icon_url').'; ?>';
	}

	public static function FeeMeakFeedURL($attr)
	{
		global $_ctx;
		global $core;

		$f = $core->tpl->getFilters($attr);

		return 	'<?php $url = ""; '.
				'if('.sprintf($f,'$_ctx->posts').' != null && '.sprintf($f,'$_ctx->posts->count()').' == 1) { '.
				'$url = '.sprintf($f,'$_ctx->posts->post_url').'; } '.
				'echo '.sprintf($f,'$core->blog->url."feed/feemeak/$url'.(($attr['continue'] == 1)? self::CONTINUE_STR : '').'"').'; ?> ';
	}

	public static function FeeMeakEntryNext($attr,$content)
	{
		$restrict_to_category = !empty($attr['restrict_to_category']) ? '1' : '0';
		$restrict_to_lang = !empty($attr['restrict_to_lang']) ? '1' : '0';

		return
		'<?php if ($_ctx->posts !== null) : ?>'.
			'<?php $next_post = $core->blog->getNextPost($_ctx->posts,1,'.$restrict_to_category.','.$restrict_to_lang.'); ?>'."\n".
			'<?php if ($next_post !== null) : ?>'.
				
				'<?php $_ctx->posts = $next_post; unset($next_post);'."\n".
				'while ($_ctx->posts->fetch()) : ?>'.
			$content.
				'<?php endwhile; $_ctx->posts = null; ?>'.
			'<?php endif; ?>'.
		'<?php endif; ?>';
	}

	public static function FeeMeakEntryPrevious($attr,$content)
	{
		$restrict_to_category = !empty($attr['restrict_to_category']) ? '1' : '0';
		$restrict_to_lang = !empty($attr['restrict_to_lang']) ? '1' : '0';

		return
		'<?php if ($_ctx->posts !== null) : ?>'.
			'<?php $prev_post = $core->blog->getNextPost($_ctx->posts,-1,'.$restrict_to_category.','.$restrict_to_lang.'); ?>'."\n".
			'<?php if ($prev_post !== null) : ?>'.
				
				'<?php $_ctx->posts = $prev_post; unset($prev_post);'."\n".
				'while ($_ctx->posts->fetch()) : ?>'.
			$content.
				'<?php endwhile; $_ctx->posts = null; ?>'.
			'<?php endif; ?>'.
		'<?php endif; ?>';
	}

	public static function publicHeadContent($core)
	{
		if (!$core->blog->settings->feemeak->feemeak_enabled) {
			return;
		}

		global $_ctx;

		$url = $core->blog->url.'feed/feemeak/';
		if($_ctx->posts != null && $_ctx->posts->count() == 1) {
			$url.= $_ctx->posts->post_url;
		}

		echo '<link rel="alternate" href="'.$url.'" type="application/rss+xml" title="Media RSS" id="media-gallery" />';
	}

	public static function ImageLookup($root,$img,$size='o')
	{
		# Get base name and extension
		$info = path::info($img);
		$base = $info['base'];

		if (preg_match('/^\.(.+)_(sq|t|s|m)$/',$base,$m)) {
			$base = $m[1];
		}

		$res = false;
		if ($size != 'o' && file_exists($root.'/'.$info['dirname'].'/.'.$base.'_'.$size.'.jpg'))
		{
			$res = '.'.$base.'_'.$size.'.jpg';
		}
		else
		{
			$f = $root.'/'.$info['dirname'].'/'.$base;
			if (file_exists($f.'.'.$info['extension'])) {
				$res = $base.'.'.$info['extension'];
			} elseif (file_exists($f.'.jpg')) {
				$res = $base.'.jpg';
			} elseif (file_exists($f.'.png')) {
				$res = $base.'.png';
			} elseif (file_exists($f.'.gif')) {
				$res = $base.'.gif';
			}
		}

		if ($res) {
			return $res;
		}
		return false;
	}

	private static function ImageName($img)
	{
		# Get base name and extension
		$info = path::info($img);
		$base = $info['base'];

		if (preg_match('/^\.(.+)_(sq|t|s|m)$/',$base,$m)) {
			$base = $m[1];
		}

		return $base;
	}
}
?>