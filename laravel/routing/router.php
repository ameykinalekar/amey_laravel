<?php namespace Laravel\Routing; use Closure, Laravel\Bundle;

class Router {

	/**
	 * All of the routes that have been registered.
	 *
	 * @var array
	 */
	public static $routes = array();

	/**
	 * All of the route names that have been matched with URIs.
	 *
	 * @var array
	 */
	public static $names = array();

	/**
	 * The wildcard patterns supported by the router.
	 *
	 * @var array
	 */
	protected static $patterns = array(
		'(:num)' => '([0-9]+)',
		'(:any)' => '([a-zA-Z0-9\.\-_]+)',
	);

	/**
	 * The optional wildcard patterns supported by the router.
	 *
	 * @var array
	 */
	protected static $optional = array(
		'/(:num?)' => '(?:/([0-9]+)',
		'/(:any?)' => '(?:/([a-zA-Z0-9\.\-_]+)',
	);

	/**
	 * Register a route with the router.
	 *
	 * @param  string|array  $route
	 * @param  string        $action
	 * @return void
	 */
	public static function register($route, $action)
	{
		foreach ((array) $route as $uri)
		{
			static::$routes[$uri] = $action;
		}
	}

	/**
	 * Find a route by name.
	 *
	 *
	 * @param  string  $name
	 * @return array
	 */
	public static function find($name)
	{
		if (isset(static::$names[$name])) return static::$names[$name];

		// If no route names have been found at all, we will assume no reverse
		// routing has been done, and we will load the routes file for all of
		// the bundle that are installed for the application.
		if (count(static::$names) == 0)
		{
			foreach (Bundle::all() as $bundle)
			{
				Bundle::routes($bundle);
			}
		}

		// To find a named route, we will iterate through every route defined
		// for the application. We will cache the routes by name so we can
		// load them very quickly if we need to find them a second time.
		foreach (static::$routes as $key => $value)
		{
			if (is_array($value) and isset($value['name']) and $value['name'] == $name)
			{
				return static::$names[$name] = array($key => $value);
			}
		}
	}

	/**
	 * Search the routes for the route matching a request method and URI.
	 *
	 * @param  string   $method
	 * @param  string   $uri
	 * @return Route
	 */
	public static function route($method, $uri)
	{
		// All route URIs begin with the request method and have a leading
		// slash before the URI. We'll put the request method and URI in
		// that format so we can easily check for literal matches.
		$destination = $method.' /'.trim($uri, '/');

		// First we'll check for a literal match on the destination string
		// as this would be the most efficient way to route the request.
		// If a match is found, we'll return a Route instance.
		if (isset(static::$routes[$destination]))
		{
			return new Route($destination, static::$routes[$destination], array());
		}

		// If we can't find a literal match, we'll iterate through all of
		// the registered routes attempting to find a matching route that
		// uses wildcards or regular expressions.
		//
		// Since routes that don't use wildcards or regular expressions
		// should have been caught by the literal route check, we'll
		// only check routes that have a parentheses, indicating
		// that there are wildcards or regular expressions.
		foreach (static::$routes as $route => $action)
		{
			if (strpos($route, '(') !== false)
			{
				if (preg_match('#^'.static::wildcards($key).'$#', $destination))
				{
					$parameters = static::parameters($destination, $key);

					return new Route($route, $action, $parameters);
				}
			}
		}

		// If there are no literal matches and no routes that match the
		// request, we'll use convention to search for a controller to
		// handle the request. If no controller can be found, the 404
		// error response will be returned by the application.
		$segments = array_diff(explode('/', trim($uri, '/')), array(''));

		return static::controller(DEFAULT_BUNDLE, $method, $destination, $segments);
	}

	/**
	 * Attempt to find a controller for the incoming request.
	 *
	 * @param  string  $bundle
	 * @param  string  $method
	 * @param  string  $destination
	 * @param  array   $segments
	 * @return Route
	 */
	protected static function controller($bundle, $method, $destination, $segments)
	{
		// If the request is to the root of the application, an ad-hoc route
		// will be generated to the home controller's "index" method, making
		// it the default controller method for the application.
		if (count($segments) == 0)
		{
			$uri = ($bundle == DEFAULT_BUNDLE) ? '/' : "/{$bundle}";

			return new Route($method.' '.$uri, Bundle::prefix($bundle).'home@index');
		}

		$directory = Bundle::path($bundle).'controllers/';

		// We need to determine in which directory to look for the controllers.
		// If the first segment of the request corresponds to a bundle that
		// is installed for the application, we will use that bundle's
		// controller path, otherwise we'll use the application's.
		if (Bundle::routable($segments[0]))
		{
			$bundle = $segments[0];

			// We shift the bundle name off of the URI segments because it will not
			// be used to find a controller within the bundle. If we were to leave
			// it in the segments, every bundle controller would need to be nested
			// within a sub-directory matching the bundle name.
			array_shift($segments);

			return static::controller($bundle, $method, $destination, $segments);
		}

		if ( ! is_null($key = static::controller_key($segments, $directory)))
		{
			// First, we'll extract the controller name, then, since we need
			// to extract the method and parameters, we will remove the name
			// of the controller from the URI. Then we can shift the method
			// off of the array of segments. Any remaining segments are the
			// parameters that should be passed to the controller method.
			$controller = implode('.', array_slice($segments, 0, $key));

			$segments = array_slice($segments, $key);

			$method = (count($segments) > 0) ? array_shift($segments) : 'index';

			// We need to grab the prefix to the bundle so we can prefix
			// the route identifier with it. This informs the controller
			// class out of which bundle the controller instance should
			// be resolved when it is needed by the application.
			$prefix = Bundle::prefix($bundle);

			return new Route($destination, $prefix.$controller.'@'.$method, $segments);
		}
	}

	/**
	 * Get the URI index for the controller that should handle the request.
	 *
	 * @param  string  $directory
	 * @param  array   $segments
	 * @return int
	 */
	protected static function controller_key($segments, $directory)
	{
		// To find the proper controller, we need to iterate backwards through
		// the URI segments and take the first file that matches. That file
		// should be the deepest possible controller matched by the URI.
		foreach (array_reverse($segments, true) as $key => $value)
		{
			$controller = implode('/', array_slice($segments, 0, $key + 1)).EXT;

			if (file_exists($path = $directory.$controller)) return $key + 1;
		}
	}

	/**
	 * Translate route URI wildcards into actual regular expressions.
	 *
	 * @param  string  $key
	 * @return string
	 */
	protected static function wildcards($key)
	{
		// For optional parameters, first translate the wildcards to their
		// regex equivalent, sans the ")?" ending. We'll add the endings
		// back on after we know how many replacements we made.
		$key = str_replace(array_keys(static::$optional), array_values(static::$optional), $key, $count);

		$key .= ($count > 0) ? str_repeat(')?', $count) : '';

		return str_replace(array_keys(static::$patterns), array_values(static::$patterns), $key);
	}

	/**
	 * Extract the parameters from a URI based on a route URI.
	 *
	 * Any route segment wrapped in parentheses is considered a parameter.
	 *
	 * @param  string  $uri
	 * @param  string  $route
	 * @return array
	 */
	protected static function parameters($uri, $route)
	{
		list($uri, $route) = array(explode('/', $uri), explode('/', $route));

		$count = count($route);

		$parameters = array();

		// To find the parameters that should be passed to the route, we will
		// iterate through the route segments, and if the segment is enclosed
		// in parentheses, we'll take the matching segment from the request
		// URI and add it to the array of parameters.
		for ($i = 0; $i < $count; $i++)
		{
			if (preg_match('/\(.+\)/', $route[$i]) and isset($uri[$i]))
			{
				$parameters[] = $uri[$i];
			}
		}

		return $parameters;
	}	

}