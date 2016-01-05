# Local WordPress Theme Repository
### A Composer Plugin
The purpose of this composer plugin is to allow you to easily set up and maintain a local
composer repository for a wordpress-theme package on a local file system, and then
be able to require that wordpress-theme package into your root site package allowing
you to run `composer install` or `composer update` and symlink that theme to
`wp-content/themes` and manage any theme dependencies.

This is useful in development so you can separate out your theme development
from the wordpress environment. You can blow away the wordpress install without 
cloberring your theme.

This can also be useful on the server as well, if you only deploy the theme.
Especially if you do rolling deploys, you can set the location of the local
wordpress-theme repostiory to the path of the symlinked "current" theme deploy.


## Installation
To install this composer plugin, simply require it in your composer.json file

```javascript
"require": {
  ...
  "stoutlogic/local-wordpress-theme-repository": "^1.0",
  ...
}
```
or run

`composer require stoutlogic/local-wordpress-theme-repository`

## Using
This plugin provides 3 scripts: init-theme,  change-theme-name, and change-theme-version

### init-theme
`composer run-script init-theme`

This is the first command that should be performed. It will walk you through the
steps of setting up your local theme repository. It will ask you for a series of
inputs. The defaults will be shown in [brackets], and you can just hit enter
to accept the defaults.

```
Package name of the theme you wish to init [stoutlogic/understory-theme]:
Relative path of the theme package is or will be located [./theme]:
Relative path to the WordPress themes directory [wp-content/themes]:
```

Then you'll be ask to input the relative path to the WordPress install's theme directory

```
Relative path to the WordPress themes directory [wp-content/themes]:
```

If the theme package isn't found, you will get the chance to download it from
the packagist if it exists. Type y or yes to do so, the default behavior is not to

```
Download stoutlogic/understory-theme using `composer create-project --no-install` [n]?
```

If you don't, please copy, download or create the theme manually in the the specified path.
Be sure that the version is set in the theme's composer.json file. If it doesn't you can
manually add it. Make sure it matches the version required in teh site's composer.json file
and run `composer update`

It may be easier just to run the init-theme script again. It will pull the values you already
set in your theme and site composer.json files as defaults so it should go quickly.

### change-theme-name
`composer run-script change-theme-name`

If the name you specified in either site or theme composer.json differ, or if you want
to easily change both, simply run the change-theme-name script to change it in 
both places.

### change-theme-version
`composer run-script change-theme-version`

Much like the change-theme-name script, if the version you specified in either site or 
theme composer.json differ, or if you want to easily change both, simply run the 
change-theme-version script to change it in both places.
