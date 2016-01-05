<?php

namespace StoutLogic\LocalWordPressThemeRepository;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Script\Event;

class Installer
{
    private $io;

    private $siteConfigFile;
    private $themeConfigFile;

    public function __construct($io)
    {        
        $this->io = $io;
    }

    public static function initTheme(Event $event)
    {
        $installer = new Installer($event->getIo());
        $installer->doInitTheme();       
    }

    public static function changeThemeVersion(Event $event)
    {
        $installer = new Installer($event->getIo());
        if ($installer->doChangeThemeVersion())
        {
            $installer->getIo()->write('  * Running `composer update`');
            exec('composer update');
        }       
    }

    public static function changeThemeName(Event $event)
    {
        $installer = new Installer($event->getIo());
        if ($installer->doChangeThemeName())
        {
            $installer->getIo()->write('  * Running `composer update`');
            exec('composer update');
        }  
    }

    public function doInitTheme()
    {
        // Retrieve existing values or set defaults
        $defaultThemeRepositoryPath = $this->getSiteConfigValue('repositories', 'theme', 'url') ?: './theme';
        $defaultThemeName = $this->getThemeConfigValue('name') ?: 'stoutlogic/understory-theme';
        $defaultThemeVersion = $this->getThemeConfigValue('version') ?: 'dev-master';

        $packageName = $this->getIo()->ask("Package name of the theme you wish to init [$defaultThemeName]: ", $defaultThemeName);
        $packageVersion = $this->getIo()->ask("Package version of the theme you wish to init [$defaultThemeVersion]: ", $defaultThemeVersion);
        $location = $this->getIo()->ask("Relative path of the theme package is or will be located [$defaultThemeRepositoryPath]: ",  $defaultThemeRepositoryPath);
    
        $this->modifyComposerJson($packageName, $packageVersion, $location);
        $this->createLocalRepositoryPath($packageName, $packageVersion, $location);
    }

    public function doChangeThemeVersion($packageVersion = null)
    {
        if (!$this->getSiteConfigFile()) return false;
        $siteConfigJson = $this->getSiteConfigFile()->read();

        if (!$this->getThemeConfigFile(true)) {
            $this->getIo()->writeError("  ** Error: Could not find theme's composer.json file");
            return false;
        }
        $themeConfigJson = $this->getThemeConfigFile()->read();
    
        $themeName = $themeConfigJson['name'];
        $oldRequiredVersion = $this->getThemeConfigValue('version');

        $version = $packageVersion;
        if (!$packageVersion) {
            $version = $this->getIo()->ask("Change the version of the wordpress-theme package to [$oldRequiredVersion]: ", $oldRequiredVersion);
        }

        $themeConfigJson['version'] = $version;
        $this->getThemeConfigFile()->write($themeConfigJson);

        $siteConfigJson['require'][$themeName] = $version;
        $siteConfigJson = $this->cacheThemePackageVersion($siteConfigJson, $version);
        $this->getSiteConfigFile()->write($siteConfigJson);

        return true;
    }

    public function doChangeThemeName($packageName = null)
    {
        if (!$this->getSiteConfigFile(true)) return false;
        $siteConfigJson = $this->getSiteConfigFile()->read();

        if (!$this->getThemeConfigFile(true)) {
            $this->getIo()->writeError("  ** Error: Could not find theme's composer.json file");
            return false;
        }
        $themeConfigJson = $this->getThemeConfigFile()->read();

        $cachedThemeName = $this->getSiteConfigValue('extra', 'wordpress-theme-package-cache', 'name');
        $cachedThemeVersion = $this->getSiteConfigValue('extra', 'wordpress-theme-package-cache', 'version');
        
        $themeConfigJson['name'] = $packageName;
        if (!$packageName) {
            $currentThemeName = $this->getThemeConfigValue('name');
            $themeConfigJson['name'] = $this->getIo()->ask("Change the name of the wordpress-theme package to [$currentThemeName]: ", $currentThemeName);
        }

        $this->getThemeConfigFile()->write($themeConfigJson);

        if ($cachedThemeName) {
            unset($siteConfigJson['require'][$cachedThemeName]);
        }
        
        $siteConfigJson['require'][$themeConfigJson['name']] = $cachedThemeVersion;

        $siteConfigJson = $this->cacheThemePackageName($siteConfigJson, $themeConfigJson['name']);
        $this->getSiteConfigFile()->write($siteConfigJson);

        return true;
    }

    private function createLocalRepositoryPath($packageName, $packageVersion, $location)
    {
        $path = getcwd() . DIRECTORY_SEPARATOR . $location;
        $realpath = realpath($path);

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            $realpath = realpath($path);
            $this->getIo()->write('  * Creating directory: '. $realpath);
        }

        if (!file_exists($realpath . DIRECTORY_SEPARATOR . 'composer.json')) {
            $this->getIo()->writeError("\tcomposer.json not found at $realpath");

            if ($this->getIo()->askConfirmation("\tDownload $packageName using `composer create-project --no-install` [n]? ", false)) {
                exec('composer create-project '.$packageName.' '.$realpath.' --no-install');
                $this->getIo()->write('  * Wordpress-Theme package downloaded');
                
                // Make sure the downloaded package has a version number
                self::checkThemeVersion($packageVersion);
            } else {
                $this->getIo()->writeError("  ** Please manually copy your wordpress-theme package to $realpath");
                $this->getIo()->writeError("  ** and then run `composer install` from the site root or re-run `composer run-script init-theme`");
            }
        } else {
            // Make sure the existing package has a version number
            self::checkThemeVersion($packageVersion);
        }
    }

    private function getSiteConfigFile($force = false)
    {
        if (!$this->siteConfigFile || $force) {
            $configPath = 'composer.json';
            if (!file_exists($configPath)) return false;

            $this->siteConfigFile = new JsonFile('composer.json');
        }

        return $this->siteConfigFile;
    }

    private function getThemeConfigFile($force = false)
    {
        if (!$this->themeConfigFile || $force)
        {
            $themePath = $this->getSiteConfigValue('repositories', 'theme', 'url');

            if ($themePath) {
                $configPath = $themePath . DIRECTORY_SEPARATOR . 'composer.json';
                if (!file_exists($configPath)) return false;

                $this->themeConfigFile = new JsonFile($configPath);
            } else {
                return false;
            }
        }

        return $this->themeConfigFile;
    }

    /**
     * Check to see if a version is set on the theme, set it if not
     * @param  string $packageVersion
     * @return boolean               version existsed
     */
    private function checkThemeVersion($packageVersion)
    {
        if (!$this->getThemeConfigValue('version')) {
            $this->doChangeThemeVersion($packageVersion);
            
            $this->getIo()->write('  * Running `composer update` to install the theme in the project');
            exec('composer update');
            return false;
        }

        return true;
    }

    private function modifyComposerJson($packageName, $packageVersion, $location)
    {
        $configFile = $this->getSiteConfigFile();
        $configJson = $configFile->read();

        $configJson = self::addRequire($configJson, $packageName, $packageVersion);
        $configJson = self::addLocalRepository($configJson, $packageName, $location);
        $configJson = self::addExtraThemeInstallPath($configJson);

        $configFile->write($configJson);

        // If the theme exists and the package name is different from the default
        // make sure we change it in the themes composer.json as well
        $this->doChangeThemeName($packageName);
    }

    private function addLocalRepository($configJson, $packageName, $location)
    {
        if (!array_key_exists('repositories', $configJson)) {
            $configJson['repositories'] = array();
        }

        $configJson['repositories']['theme'] = array(
            'type' => 'path',
            'url' => $location,
        );

        $this->getIo()->write('  * Added local repository path for the theme package to composer.json');

        return $configJson;
    }

    private function addExtraThemeInstallPath($configJson)
    {
        if (!array_key_exists('extra', $configJson)) {
            $configJson['extra'] = array();
        }

        if (!array_key_exists('installer-paths', $configJson['extra'])) {
            $configJson['extra']['installer-paths'] = array();
        }

        $defaultPath = 'wp-content/themes';
        foreach ($configJson['extra']['installer-paths'] as $key => $value) {
            if (is_array($value) && in_array('wp-content/themes', $value)) {
                $defaultPath = str_replace( getcwd() . DIRECTORY_SEPARATOR , '', $key);
                $defaultPath = str_replace( DIRECTORY_SEPARATOR . '{$name}' , '', $defaultPath);
                unset($configJson['extra']['installer-paths'][$key]);
            }
        }

        $wpContentThemesPath = $this->getIo()->ask("Relative path to the WordPress themes directory [$defaultPath]: ", $defaultPath);

        $themePath = getcwd() . DIRECTORY_SEPARATOR . $wpContentThemesPath . DIRECTORY_SEPARATOR . '{$name}';
        $configJson['extra']['installer-paths'][$themePath] = array('type:wordpress-theme');

        $this->getIo()->write('  * Added installer-path for wordpress-theme packages');

        return $configJson;
    }

    private function addRequire($configJson, $packageName, $packageVersion = "dev-master")
    {
        if (!array_key_exists('require', $configJson)) {
            $configJson['require'] = array();
        }

        // Remove old theme name from require if it exists
        $cachedThemeName = $this->getNestedArrayValue($configJson, 'extra', 'wordpress-theme-package-cache', 'name');
        if ($cachedThemeName) {
            unset($configJson['require'][$cachedThemeName]);
            $this->getIo()->write("  * Removed required package $cachedThemeName from composer.json");
        }

        $configJson['require'][$packageName] = $packageVersion;
        $this->getIo()->write("  * Added required package $packageName $packageVersion to composer.json");
        
        // Cache the name and version
        $configJson = $this->cacheThemePackageName($configJson, $packageName);
        $configJson = $this->cacheThemePackageVersion($configJson, $packageVersion);

        return $configJson;
    }

    private function cacheThemePackageName($configJson, $packageName)
    {
        if (!array_key_exists('extra', $configJson)) {
            $configJson['extra'] = array();
        }

        if (!array_key_exists('wordpress-theme-package-cache',  $configJson['extra'])) {
            $configJson['extra']['wordpress-theme-package-cache'] = array();
        }

        $configJson['extra']['wordpress-theme-package-cache']['name'] = $packageName;

        return $configJson;
    }

    private function cacheThemePackageVersion($configJson, $packageVersion)
    {
        if (!array_key_exists('extra', $configJson)) {
            $configJson['extra'] = array();
        }

        if (!array_key_exists('wordpress-theme-package-cache',  $configJson['extra'])) {
            $configJson['extra']['wordpress-theme-package-cache'] = array();
        }

        $configJson['extra']['wordpress-theme-package-cache']['version'] = $packageVersion;

        return $configJson;
    }

    private function getSiteConfigValue()
    {
        $siteConfigFile = $this->getSiteConfigFile();
        if (!$siteConfigFile) {
            return null;
        }

        $siteConfigJson = $siteConfigFile->read();
        return $this->getNestedArrayValue($siteConfigJson, func_get_args());
    }

    private function getThemeConfigValue()
    {
        $themeConfigFile = $this->getThemeConfigFile();
        if (!$themeConfigFile) {
            return null;
        }

        $themeConfigJson = $themeConfigFile->read();
        return $this->getNestedArrayValue($themeConfigJson, func_get_args());
    }

    private function getNestedArrayValue($array) 
    {
        $args = func_get_args();
        $arrayValue = $array;

        if (is_array($args[1])) {
            $args = $args[1];
        } else {
            $args = array_slice($args, 1);
        }

        foreach ($args as $i => $key) {
            if (!array_key_exists($key, $arrayValue)) return null;
            $arrayValue = $arrayValue[$key];
        }

        return $arrayValue;
    }

    public function getIo() {
        return $this->io;
    }
}
