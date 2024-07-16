<?php

if (!file_exists('vendor/autoload.php')) {
    echo "Run composer install first\n";
    exit(1);
}
require 'vendor/autoload.php';
require 'src/requirements.php';
require 'src/directories.php';
require 'src/functions.php';
require 'repos.php';
require 'src/process.php';
