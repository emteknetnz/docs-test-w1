<?php

require 'vendor/autoload.php';
use Michelf\Markdown;

// Check if zip is installed
$whichZip = shell_exec('which zip');
if (empty($whichZip)) {
    echo "zip is not installed\n";
    exit(1);
}

// Ensure zips folder exists for downloaded zip files from other repos with docs
$zipsDir = __DIR__ . '/zips';
if (!file_exists($zipsDir)) {
    mkdir($zipsDir, 0777, true);
}

// Ensure repos folder exists for extracted zip files
$reposDir = __DIR__ . '/repos';
if (!file_exists($reposDir)) {
    mkdir($reposDir, 0777, true);
}

// Ensure _site folder exists for building the static site
$siteDir = __DIR__ . '/_site';
if (file_exists($siteDir)) {
    shell_exec("rm -rf $siteDir");
}
mkdir($siteDir, 0777, true);

$ghrepos = [
    'emteknetnz/docs-test-r1',
];

foreach ($ghrepos as $ghrepo) {

    $repo = explode('/', $ghrepo)[1];
    $branch = 'main';
    $docsDir = 'docs'; // this should be centrally configured (i.e. in w1, not r1)

    // Download zip file
    $zipFilePath = __DIR__ . "/zips/$repo.zip";
    if (!file_exists($zipFilePath)) {
        $url = "https://github.com/$ghrepo/archive/refs/heads/$branch.zip";
        $c = file_get_contents($url);
        file_put_contents($zipFilePath, $c);
    }

    // Unzip file
    $unzipPath = __DIR__ . "/repos/$repo-$branch";
    if (!file_exists($unzipPath)) {
        $zip = new ZipArchive;
        if ($zip->open($zipFilePath) === TRUE) {
            $zip->extractTo($reposDir);
            $zip->close();
        } else {
            echo "Failed to unzip file\n";
            exit(1);
        }
    }

    // Loop through md files in extracted zip using glob including subdirs
    $docsFilePaths = glob("$unzipPath/$docsDir/{*.md,**/*.md}", GLOB_BRACE);
    foreach ($docsFilePaths as $docsFilePath) {
        $myText = file_get_contents($docsFilePath);
        $myHtml = Markdown::defaultTransform($myText);
        $relativePath = str_replace("$unzipPath/$docsDir/", '', $docsFilePath);
        $filePath = "$siteDir/$relativePath";
        $filePath = preg_replace('/\.md$/', '.html', $filePath);
        $fileDir = dirname($filePath);
        if (!is_dir($fileDir)) {
            mkdir($fileDir, 0777, true);
        }
        file_put_contents($filePath, $myHtml);
        echo "File written to $filePath\n";
    }
}
