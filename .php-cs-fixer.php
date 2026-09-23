<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->ignoreVCSIgnored(true)
    ->name('*.php');

return (new Config())
    ->setRules(['@PER-CS' => true])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer/.php-cs-fixer.cache');
