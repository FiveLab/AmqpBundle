#!/usr/bin/env php
<?php

declare(strict_types = 1);

$packageName = $argv[1] ?? null;
$packageVersion = $argv[2] ?? null;

if (!$packageName || !$packageVersion) {
    print 'The package or version not specified.'.PHP_EOL;
    exit (1);
}

$composerFile = __DIR__.'/../composer.json';
$composerData = \json_decode(\file_get_contents($composerFile), true);

if (!\array_key_exists($packageName, $composerData['require'])) {
    print \sprintf('The package "%s" was not found in "required".', $packageName).PHP_EOL;
    exit (1);
}

$composerData['require'][$packageName] = $packageVersion;

\file_put_contents($composerFile, \json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
