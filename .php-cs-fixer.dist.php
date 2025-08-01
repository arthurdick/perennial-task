<?php

$config = new PhpCsFixer\Config();

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('vendor')
    ->notPath('tests/temp')
    ->name('*.php')
    ->name('prn')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setFinder($finder)
;
