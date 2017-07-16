=== Varnish Cache ===
Contributors: 42functions, Byte
Tags: varnish, cache, redis
Tested up to: 4.8.0
Stable tag: 1.4.4

Varnish cache is a powerful extension which acts as a communication layer between Varnish and WordPress.

== Installation ==

1. Upload the folder 'varnish-cache' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Known issues ==

= Multisite =

There are problems with multisite support as is. There is one singular file shared between all sites, this although the data is stored in each database separately. The singular file actually causes some weird sort of sync between the options.

= E-Tag =

The e-tag of specific pages take a generic e-tag timeout into account. Meaning if someone changes a specific page the e-tag doesn't actually change.

== Pipeline ==

The following functionality is currently in the pipeline

* Fix multisite issues
* Branch term policy to `taxonomy` specific policies
* Branch post policy to `post type` specific policies 


== Changelog ==

= 1.4.4 =

* Modified: Restructured admin page setups
* Added: Additional user documentation
* Added: TOS for cache warmer

= 1.4.3 =

* Added: Added patch for HTTPS neglecting

= 1.4.2 =

* Fixed: Minor bugfixes in flushing overloading

= 1.4.1 =

* Fixed: Filters on redis flushing 
* Added: Refrain caching password protected posts

= 1.4.0 =

* Added: State icon in configuration screen for cache status
* Added: Created page builder
* Added: File system cache engine
* Added: Support for network wide flushing
* Added: Support for must revalidate caching
* Added: Option to disable output compression
* Moved: Relocated styling for configuration page to a separate css file
* Fixed: Excluded command line requests from cache
* Modified: The setup of engines, responsible code has been moved to class
* Modified: Added preq_quote to specified regex
* Fixed: Status header check upon redirect
* Added: Vary: Cookie header to prevent browser cache (varnish)

= 1.3.2 =

* Removed: Browser caching, possible conflict with 304 headers

= 1.3.1 = 

* New: Added credis as fallback library if Redis C# Module is unavailible
* Update: Implemented activation / deactivation within plugin configuration

= 1.3.0 =

* Update plugin description / title to a more generic format
* New: Added support for Redis caching engine
* New: Added filter 'cache_engines' to allow registering custom engines
* New: Added filter 'cache_varnish_availible', replaces the 'cache_varnish_valid' modifier
* Removed: filter 'cache_varnish_valid'
* New: Added action 'cache_configuration_engine_form', allows someone to render additional configuration options in the engine form
* New: Added filter 'cache_form_process_engine', allows you to extend the stored cache configuration to store custom options

* Important: Pre-loader based cache conflicts in multisite mode!

= 1.2.2 =

* New: Added support to send no-cache headers for non-www pages

= 1.2.1 =

* Fix: Prevented fatal error in term sibling loop
* Fix: Prevented fatal error in flush all (WPML)
* Fix: Repaired configuration mismatch for general policy flush

= 1.2.0 =

* New: Added 'cache_flush' filter, parameters: keys, cache instance
* New: Added 'cache_flush_all' filter, parameter: (bool)success, cache instance
* New: Added 'cache_form_display' action, parameter: Module
* New: Added 'cache_form_process' filter, parameter: data, Module
* New: Added experimental support for WPML (v 3.1.8.4)

= 1.1.0 =

* Fix: Removed redundent flag in 'Flush all' action
* Fix: Now flushes the additional supplied urls
* New: Added support to exclude pages from caching
* New: Added support to define expire headers
* New: Added support for flushing a particular url
* New: Added support for flushing an entire object structure
* New: Added Author flushing policy
