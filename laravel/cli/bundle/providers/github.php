<?php namespace Laravel\CLI\Bundle\Providers;

class Github implements Provider {

	/**
	 * Install the given bundle into the application.
	 *
	 * @param  string  $bundle
	 * @return void
	 */
	public function install($bundle)
	{
		$repository = "git://github.com/{$bundle['location']}.git";

		// We need to just extract the basename of the bundle path when
		// adding the submodule. Of course, we can't add a submodule to
		// a location outside of the Git repository, so we don't need
		// the full bundle path. We'll just take the basename in case
		// the bundle directory has been renamed.
		passthru('git submodule add '.$repository.' '.basename(BUNDLE_PATH).'/'.$name);

		passthru('git submodule update');
	}

}