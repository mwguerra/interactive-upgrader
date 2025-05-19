<?php

namespace MWGuerra\InteractiveUpgrader\Services;

class FilesystemFunctions
{
    public static function file_exists(string $filename): bool
    {
        return file_exists($filename);
    }

    public static function file_get_contents(string $filename): string
    {
        return file_get_contents($filename);
    }
}