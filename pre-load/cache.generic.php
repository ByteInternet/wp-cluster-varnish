<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Pre loader used to setup/serve the cache 
 */

define('CACHE_PRE_LOADED', true);
define('CACHE_PLUGIN_DIR', dirname(__DIR__));

if(!file_exists(__DIR__ . '/config.php'))
	return;

require_once CACHE_PLUGIN_DIR . '/class/pattern.singleton.php';
require_once CACHE_PLUGIN_DIR . '/class/manager.module.php';
require_once CACHE_PLUGIN_DIR . '/class/facade.cache.php';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class.request.php';

if(!defined('CACHE_ENGINE_ENABLED') || !CACHE_ENGINE_ENABLED)
	return;

XLII_Cache_Manager::init();

@date_default_timezone_set('UTC');

$_cache = new XLII_Cache_Request();

if($_cache->shouldRun())
{
	$_cache->serve();
	$_cache->track();
}