<?php
/*
 * Plugin Name: Perfomance plugin
 * Description: Adds a performance boost to your website using varnish or redis.
 * Author: 42functions
 * Version: 1.4.4
 * Author URI: http://42functions.nl/
 */

if(!defined('CACHE_PLUGIN_DIR'))
	define('CACHE_PLUGIN_DIR', __DIR__);

if(!defined('CACHE_DEBUG') && defined('ENV') && (stripos(ENV, 'acc') !== false || strpos(ENV, 'dev') !== false || strpos(ENV, 'test') !== false))
	define('CACHE_DEBUG', true);


require_once CACHE_PLUGIN_DIR . '/build.methods.php';

require_once CACHE_PLUGIN_DIR . '/class/pattern.singleton.php';
require_once CACHE_PLUGIN_DIR . '/class/manager.api.php';
require_once CACHE_PLUGIN_DIR . '/class/manager.module.php';
require_once CACHE_PLUGIN_DIR . '/class/facade.cache.php';

require_once CACHE_PLUGIN_DIR . '/class/admin/admin.menubar.php';
require_once CACHE_PLUGIN_DIR . '/class/admin/admin.configuration.php';
require_once CACHE_PLUGIN_DIR . '/class/admin/admin.warmer.php';


XLII_Cache_Manager::init()->setup();

