<?php

$htmlFilePathToMetadata = [];

// Loop github repos
foreach ($repoData as $data) {

    $ghrepo = $data['ghrepo'];
    $branch = $data['branch'];
    $docsDir = $data['docsDir'];
    $repo = explode('/', $ghrepo)[1];

    $brace = '{,*/,*/*/,*/*/*/,*/*/*/*/,*/*/*/*/*/,*/*/*/*/*/*/}';

    // Download zip file
    $zipFilePath = "$zipsDir/$repo.zip";
    if (!file_exists($zipFilePath)) {
        $url = "https://github.com/$ghrepo/archive/refs/heads/$branch.zip";
        echo "Downloading $url\n";
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
    $mdFilePaths = glob("$unzipPath/$docsDir/$brace*.md", GLOB_BRACE);
    foreach ($mdFilePaths as $mdFilePath) {
        $md = file_get_contents($mdFilePath);
        $metadata = getMetadataFromMd($md);
        $md = removeMetadataFromMd($md);
        $html = convertMarkdownToHtml($md);
        $relativePath = str_replace("$unzipPath/$docsDir/", '', $mdFilePath);
        $htmlFilePath = "$siteDir/$relativePath";
        $htmlFilePath = preg_replace('/\.md$/', '.html', $htmlFilePath);
        $htmlFileDir = dirname($htmlFilePath);
        if (!is_dir($htmlFileDir)) {
            mkdir($htmlFileDir, 0777, true);
        }
        file_put_contents($htmlFilePath, $html);
        $htmlFilePathToMetadata[$htmlFilePath] = $metadata;
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
        $lines[] = "</ul>\n";
        $html = implode("\n", $lines);
        file_put_contents($indexHtmlFilePath, $html);
    }

    // Update all HTML content files by adding in template
    $htmlFilePaths = glob("$siteDir/$brace*.html", GLOB_BRACE);
    foreach ($htmlFilePaths as $htmlFilePath) {
        $metadata = $htmlFilePathToMetadata[$htmlFilePath] ?? [];
        $contentHtml = file_get_contents($htmlFilePath);
        $title = getTitle($metadata, $contentHtml, $htmlFilePath);
        $sideNavHtml = createSideNavHtml($htmlFilePath);
        $html = addHtmlTemplate($title, $contentHtml, $sideNavHtml);
        file_put_contents($htmlFilePath, $html);
    }

    // Update all HTML content files by updating [CHILDREN]
    $htmlFilePaths = glob("$siteDir/$brace*.html", GLOB_BRACE);
    foreach ($htmlFilePaths as $htmlFilePath) {
        $siblingFilePaths = [];
        // $siblingFilePaths = array_diff($siblingFilePaths, [$htmlFilePath]);
        foreach (glob(dirname($htmlFilePath) . '/*.html') as $siblingFilePath) {
            if (basename($siblingFilePath) === 'index.html') {
                continue;
            }
            $siblingFilePaths[] = $siblingFilePath;
        }
        $childIndexFilePaths = glob(dirname($htmlFilePath) . '/*/index.html');
        $childDirectories = array_map('dirname', $childIndexFilePaths);
        $childDirectoryFilePaths = [];
        $grandChildIndexFilePaths = [];
        foreach ($childDirectories as $childDirectory) {
            $childFilePaths = glob("$childDirectory/*.html");
            foreach ($childFilePaths as $childFilePath) {
                $childDirectoryName = basename($childDirectory);
                $childDirectoryFilePaths[$childDirectoryName] ??= [];
                if (basename($childFilePath) === 'index.html') {
                    foreach (glob("$childDirectory/*/index.html") as $grandChildIndexFilePath) {
                        $grandChildIndexFilePaths[$childDirectoryName] ??= [];
                        $grandChildIndexFilePaths[$childDirectoryName][] = $grandChildIndexFilePath;
                    }
                    continue;
                }
                $childDirectoryFilePaths[$childDirectoryName][] = $childFilePath;
            }
        }
        $relatedChildPaths = array_merge($siblingFilePaths, $childIndexFilePaths);
        sort($relatedChildPaths);
        $contentHtml = updateChildrenHtml($htmlFilePath, $relatedChildPaths, $childDirectoryFilePaths, $grandChildIndexFilePaths);
        file_put_contents($htmlFilePath, $contentHtml);
    }
    
    // Other updates to HTML content files
    foreach ($htmlFilePaths as $htmlFilePath) {
        $contentHtml = file_get_contents($htmlFilePath);
        $contentHtml = addAnchorLinksToHeadings($contentHtml);
        $contentHtml = updateHtmlLinksToRelativeAnchors($contentHtml, $htmlFilePath);
        $contentHtml = updateApiLinks($contentHtml);
        file_put_contents($htmlFilePath, $contentHtml);
    }
}

// Copy styles.css to _site
copy("$cssDir/styles.css", "$siteDir/styles.css");

echo "Local site available at file://$siteDir/index.html\n";
