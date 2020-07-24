<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4fca11379f2f8fbaaf681a1c4e0edf7c
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Log\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
    );

    public static $prefixesPsr0 = array (
        'E' => 
        array (
            'Evenement' => 
            array (
                0 => __DIR__ . '/..' . '/evenement/evenement/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4fca11379f2f8fbaaf681a1c4e0edf7c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4fca11379f2f8fbaaf681a1c4e0edf7c::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit4fca11379f2f8fbaaf681a1c4e0edf7c::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}