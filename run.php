<?php

require 'vendor/autoload.php';
use Michelf\Markdown;

function makeHtmlPage($html) {
    $title = getH1($html);
    return implode("\n", [
        '<!DOCTYPE html>',
        '<html>',
        '<head>',
        '<meta charset="utf-8">',
        "<title>$title</title>",
        '</head>',
        '<body>',
        $html,
        '</body>',
        '</html>',
        '',
    ]);
}

function getH1($html) {
    preg_match('/<h1>(.*?)<\/h1>/', $html, $matches);
    return $matches[1] ?? '';
}

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
    $brace = '{' . implode(',', [
        '*.md',
        '**/*.md',
        '**/**/*.md',
        '**/**/**/*.md',
        '**/**/**/**/*.md',
        '**/**/**/**/**/*.md',
        '**/**/**/**/**/**/*.md',
        '**/**/**/**/**/**/**/*.md',
    ]) . '}';
    $mdFilePaths = glob("$unzipPath/$docsDir/$brace", GLOB_BRACE);
    print_r($mdFilePaths);
    foreach ($mdFilePaths as $mdFilePath) {
        $md = file_get_contents($mdFilePath);
        $html = Markdown::defaultTransform($md);
        $html = makeHtmlPage($html);
        $relativePath = str_replace("$unzipPath/$docsDir/", '', $mdFilePath);
        $htmlFilePath = "$siteDir/$relativePath";
        $htmlFilePath = preg_replace('/\.md$/', '.html', $htmlFilePath);
        $htmlFileDir = dirname($htmlFilePath);
        if (!is_dir($htmlFileDir)) {
            mkdir($htmlFileDir, 0777, true);
        }
        file_put_contents($htmlFilePath, $html);
        echo "File written to $htmlFilePath\n";
    }

    // Loop through built site folders, add in missing index.html files
    // The html will include list of all files in the dir, including subdirs
    $siteDirs = glob("$siteDir/{*,**/*}/", GLOB_BRACE);
    foreach ($siteDirs as $siteDir) {
        $siteDir = rtrim($siteDir, '/');
        $indexHtmlFilePath = "$siteDir/index.html";
        if (file_exists($indexHtmlFilePath)) {
            continue;
        }
        $title = basename($siteDir);
        $files = glob("$siteDir/*", GLOB_BRACE);
        $lines = [
            "<h1>$title</h1>",
            '<ul>',
        ];
        foreach ($files as $file) {
            if (is_dir($file)) {
                $title = basename($file);
            } else {
                $contents = file_get_contents($file);
                $title = getH1($contents) ?: $file;
            }
            $href = str_replace($siteDir, '', $file);
            $href = preg_replace('/\.html$/', '', $href);
            $lines[] = "<li><a href=\"$href\">$title</a></li>";
        }
        $lines[] = '</ul>';
        $html = makeHtmlPage(implode("\n", $lines));
        file_put_contents($indexHtmlFilePath, $html);
        echo "Index file written to $indexHtmlFilePath\n";
    }
}
