<?php

namespace StoutLogic\LocalWordPressThemeRepository;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Json\JsonFile;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-create-project-cmd' => 'modifyComposerJson',
        );
    }

    public function modifyComposerJson()
    {
        $configFile = new JsonFile('composer.json');
        $configJson = $configFile->read();

        $configJson = $this->addInitThemeScript($configJson);
        $configJson = $this->addChangeThemeVersionScript($configJson);
        $configJson = $this->addChangeThemeNameScript($configJson);
        
        $configFile->write($configJson);
    }

    private function addInitThemeScript($configJson)
    {
        if (!array_key_exists('scripts', $configJson)) {
            $configJson['scripts'] = array();
        }

        $configJson['scripts']['init-theme'] = "StoutLogic\\LocalWordPressThemeRepository\\Installer::initTheme";
        $this->getIo()->write('* Added init-theme script to composer.json');

        return $configJson;
    }

    private function addChangeThemeVersionScript($configJson)
    {
        if (!array_key_exists('scripts', $configJson)) {
            $configJson['scripts'] = array();
        }

        $configJson['scripts']['change-theme-version'] = "StoutLogic\\LocalWordPressThemeRepository\\Installer::changeThemeVersion";
        $this->getIo()->write('* Added change-theme-version script to composer.json');

        return $configJson;
    }

    private function addChangeThemeNameScript($configJson)
    {
        if (!array_key_exists('scripts', $configJson)) {
            $configJson['scripts'] = array();
        }

        $configJson['scripts']['change-theme-name'] = "StoutLogic\\LocalWordPressThemeRepository\\Installer::changeThemeName";
        $this->getIo()->write('* Added change-theme-name script to composer.json');

        return $configJson;
    }

    private function getIo()
    {
        return $this->io;
    }
}