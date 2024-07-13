<?php

// Check if zip is installed
$whichZip = shell_exec('which zip');
if (empty($whichZip)) {
    echo "zip is not installed\n";
    exit(1);
}
