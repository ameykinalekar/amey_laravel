<?php namespace Laravel\Cache\Drivers;

/**
 * The APC clas is a cache driver for the Alternative PHP Cache.
 *
 * @package  	Laravel
 * @author  	Taylor Otwell <taylorotwell@gmail.com>
 * @copyright  	2012 Taylor Otwell
 * @license 	MIT License <http://www.opensource.org/licenses/mit>
 */
class APC extends Driver {

	/**
	 * The cache key from the cache configuration file.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Create a new APC cache driver instance.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function __construct($key)
	{
		$this->key = $key;
	}

	/**
	 * Determine if an item exists in the cache.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function has($key)
	{
		return ( ! is_null($this->get($key)));
	}

	/**
	 * Retrieve an item from the cache driver.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	protected function retrieve($key)
	{
		if (($cache = apc_fetch($this->key.$key)) !== false)
		{
			return $cache;
		}
	}

	/**
	 * Write an item to the cache for a given number of minutes.
	 *
	 * <code>
	 *		// Put an item in the cache for 15 minutes
	 *		Cache::put('name', 'Taylor', 15);
	 * </code>
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @param  int     $minutes
	 * @return void
	 */
	public function put($key, $value, $minutes)
	{
		apc_store($this->key.$key, $value, $minutes * 60);
	}

	/**
	 * Delete an item from the cache.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function forget($key)
	{
		apc_delete($this->key.$key);
	}

}
