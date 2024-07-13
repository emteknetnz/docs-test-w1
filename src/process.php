<?php

use Michelf\Markdown;

// Loop github repos
$ghrepos = [
    'emteknetnz/docs-test-r1',
];
foreach ($ghrepos as $ghrepo) {

    $repo = explode('/', $ghrepo)[1];
    $branch = 'main';
    $docsDir = 'docs'; // this should be centrally configured (i.e. in w1, not r1)

    $brace = '{,*/,*/*/,*/*/*/,*/*/*/*/,*/*/*/*/*/,*/*/*/*/*/*/}';

    // Download zip file
    $zipFilePath = "$zipsDir/$repo.zip";
    if (!file_exists($zipFilePath)) {
        $url = "https://github.com/$ghrepo/archive/refs/heads/$branch.zip";
        $c = file_get_contents($url);
        file_put_contents($zipFilePath, $c);
    }

    // Unzip file
    $unzipPath = "$reposDir/$repo-$branch";
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
    // $brace = makeBrace('*.md');
    $mdFilePaths = glob("$unzipPath/$docsDir/$brace*.md", GLOB_BRACE);
    foreach ($mdFilePaths as $mdFilePath) {
        $md = file_get_contents($mdFilePath);
        $html = Markdown::defaultTransform($md);
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
    $siteSubDirs = glob("$siteDir/$brace", GLOB_BRACE);
    foreach ($siteSubDirs as $siteSubDir) {
        $siteSubDir = rtrim($siteSubDir, '/');
        $indexHtmlFilePath = "$siteSubDir/index.html";
        if (file_exists($indexHtmlFilePath)) {
            continue;
        }
        $title = basename($siteSubDir);
        $files = glob("$siteSubDir/*", GLOB_BRACE);
        $lines = [
            "<h1>$title</h1>",
            '<ul>',
        ];
        foreach ($files as $file) {
            if (is_dir($file)) {
                $title = basename($file);
            } else {
                $contents = file_get_contents($file);
                $title = getH1fromHtml($contents) ?: $file;
            }
            $href = str_replace($siteSubDir, '', $file);
            $href = preg_replace('/\.html$/', '', $href);
            $lines[] = "<li><a href=\"$href\">$title</a></li>";
        }
        $lines[] = '</ul>';
        $html = implode("\n", $lines);
        file_put_contents($indexHtmlFilePath, $html);
        echo "Index file written to $indexHtmlFilePath\n";
    }

    // Make side nav html for the site
    $htmlFiles = glob("$siteDir/$brace*.html", GLOB_BRACE);
    $sideNavHtml = '<ul>';
    foreach ($htmlFiles as $htmlFile) {
        $level = substr_count(str_replace($siteDir, '', $htmlFile), '/');
        $contentHtml = file_get_contents($htmlFile);
        $title = getH1fromHtml($contentHtml) ?: basename($htmlFile);
        $href = ltrim(str_replace($siteDir, '', $htmlFile), '/');
        $sideNavHtml .= "<li class=\"sidenav__item--level-$level\"><a href=\"$href\">$title</a></li>";
    }
    $sideNavHtml .= '</ul>';

    // Update all HTML content files surronding with site
    foreach ($htmlFiles as $htmlFile) {
        $basename = basename($htmlFile);
        $contentHtml = file_get_contents($htmlFile);
        $html = makeHtmlPage($basename, $contentHtml, $sideNavHtml);
        file_put_contents($htmlFile, $html);
        echo "HTML file updated $htmlFile\n";
    }
}

// Copy styles.css to _site
copy("$cssDir/styles.css", "$siteDir/styles.css");
