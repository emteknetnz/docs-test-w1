<?php

use Michelf\MarkdownExtra;

function makeHtmlPage($title, $contentHtml, $sideNavHtml) {
    global $siteDir, $templatesDir;
    $base = strpos(dirname(__DIR__), '/home/runner/') !== false
        ? 'https://emteknetnz.github.io/docs-test-w1/'
        : "file://$siteDir/";
    $vars = [
        'base' => $base,
        'title' => $title,
        'sideNavHtml' => $sideNavHtml,
        'contentHtml' => $contentHtml,
    ];
    $template = file_get_contents("$templatesDir/template.html");
    foreach ($vars as $key => $val) {
        $template = str_replace('$' . $key, $val, $template);
    }
    return $template;
}

function getTitle($metadata, $html, $htmlFilePath) {
    return $metadata['title'] ?? getH1fromHtml($html) ?: basename($htmlFilePath);
}

function getH1fromHtml($html) {
    preg_match('/<h1>(.*?)<\/h1>/', $html, $matches);
    return $matches[1] ?? '';
}

function getMetadataFromMd($md) {
    $metadata = [];
    if (strpos($md, '---') !== 0) {
        return $metadata;
    }
    $lines = explode("\n", $md);
    for ($i = 1; $i < count($lines); $i++) {
        $line = $lines[$i];
        if (strpos($line, '---') === 0) {
            break;
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $metadata[$key] = $value;
        }
    }
    return $metadata;
}

function removeMetadataFromMd($md) {
    $start = strpos($md, '---');
    if ($start !== 0) {
        return $md;
    }
    $end = strpos($md, '---', $start + 3);
    if ($end === false) {
        return $md;
    }
    return substr($md, $end + 3);
}

function convertMarkdownToHtml($md) {
    $parser = new MarkdownExtra();
    $parser->code_attr_on_pre = true;
    $parser->code_class_prefix = 'snippet snippet--';
    $html = $parser->transform($md);
    $html = updateAlerts($html);
    return $html;
}

function updateAlerts($html) {
    $types = ['NOTE', 'TIP', 'WARNING', 'IMPORTANT', 'CAUTION'];
    foreach ($types as $type) {
        $ltype = strtolower($type);
        $find = "<blockquote>\n  <p>[!$type]";
        $replacement = "<blockquote class=\"alert alert--$ltype\">\n  <p>";
        $html = str_replace($find, $replacement, $html);
    }
    return $html;
}

function getSiteStructure($dir, &$structure = []) {
    if (empty($structure)) {
        $structure = [
            'dir' => $dir,
            'subdirs' => [],
            'files' => [],
        ];
    }
    $files = scandir($dir);
    foreach ($files as $file) {
        if (in_array($file, ['.', '..'])) {
            continue;
        }
        if (is_dir("$dir/$file")) {
            $structure['subdirs'][] = getSiteStructure("$dir/$file");
        } else {
            $structure['files'][] = $file;
        }
    }
    // index.html files come first
    usort($structure['files'], function($a, $b) {
        if ($a === 'index.html') {
            return -1;
        } elseif ($b === 'index.html') {
            return 1;
        }
        return $a <=> $b;
    });
    return $structure;
}

function processHtmlFilePath($htmlFilePath, &$sideNavHtml) {
    global $siteDir;
    $metadata = $htmlFilePathToMetadata[$htmlFilePath] ?? [];
    $contentHtml = file_get_contents($htmlFilePath);
    $title = getTitle($metadata, $contentHtml, $htmlFilePath);
    $href = ltrim(str_replace($siteDir, '', $htmlFilePath), '/');
    $sideNavHtml .= "<li class=\"sidenav__item\"><a href=\"$href\">$title</a></li>\n";
};

function processDir($dir, $structure, &$sideNavHtml) {
    $sideNavHtml .= '<ul>';
    foreach ($structure['files'] as $file) {
        $htmlFilePath = "$dir/$file";
        processHtmlFilePath($htmlFilePath, $sideNavHtml);
        foreach ($structure['subdirs'] as $subdir) {
            $sdir = str_replace("$dir/", '', $subdir['dir']);
            processDir("$dir/$sdir", $subdir, $sideNavHtml);
        }
    }
    $sideNavHtml .= '</ul>';
};

function createSideNavHtml() {
    global $siteDir;
    $sideNavHtml = '';
    $structure = getSiteStructure($siteDir);
    processDir($structure['dir'], $structure, $sideNavHtml);
    return $sideNavHtml;
}