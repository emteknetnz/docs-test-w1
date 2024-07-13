<?php

// Ensure zips directory exists for downloaded zip files from other repos with docs
$zipsDir = dirname(__DIR__) . '/zips';
if (!file_exists($zipsDir)) {
    mkdir($zipsDir, 0777, true);
}

// Ensure repos directory exists for extracted zip files
$reposDir = dirname(__DIR__) . '/repos';
if (!file_exists($reposDir)) {
    mkdir($reposDir, 0777, true);
}

// Ensure _site directory exists for building the static site
$siteDir = dirname(__DIR__) . '/_site';
if (file_exists($siteDir)) {
    shell_exec("rm -rf $siteDir");
}
mkdir($siteDir, 0777, true);

// Define other directories
$cssDir = dirname(__DIR__) . '/css';
$templatesDir = dirname(__DIR__) . '/templates';
