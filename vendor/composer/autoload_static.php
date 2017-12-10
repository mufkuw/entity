<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitaf17a297db43bb581a773333a4ad758a
{
    public static $files = array (
        '5d3697db6bd19e8dda21f89c83bab570' => __DIR__ . '/../..' . '/src/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Mvc\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Mvc\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitaf17a297db43bb581a773333a4ad758a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitaf17a297db43bb581a773333a4ad758a::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}