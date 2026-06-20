<?php
// Stub autoloader - social plugin vendor dependencies not installed
class ComposerAutoloaderInite864777be6a63c68a558bb3fb204cec3
{
    private static $loader;
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }
        self::$loader = new class {
            public function register($prepend = false) {}
            public function unregister() {}
            public function loadClass($class) { return false; }
            public function findFile($class) { return false; }
            public function addPsr4($prefix, $paths, $prepend = false) {}
            public function setPsr4($prefix, $paths) {}
            public function addClassMap(array $classMap) {}
        };
        return self::$loader;
    }
}
