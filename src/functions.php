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

function addHtmlFilePathToSideNavHtml(
    $htmlFilePath,
    $level,
    $currentFilePath,
    $hasDecendentCurrentFilePath,
    $hasSiblingCurrentFilePath,
    $isChildOfCurrentFilePath,
    &$sideNavHtml
) {
    global $siteDir, $htmlFilePathToMetadata;
    if ($level === 0) {
        // link is shown in top left logo instead
        return;
    }
    $metadata = $htmlFilePathToMetadata[$htmlFilePath] ?? [];
    $contentHtml = file_get_contents($htmlFilePath);
    $title = getTitle($metadata, $contentHtml, $htmlFilePath);
    $href = ltrim(str_replace($siteDir, '', $htmlFilePath), '/');
    $isLevelOne = $level === 1;
    $isCurrent = $htmlFilePath === $currentFilePath;
    $hasDecendent = $hasDecendentCurrentFilePath && !$isCurrent;
    $hasSibling = $hasSiblingCurrentFilePath && !$isCurrent && !$hasDecendent;
    $isChild = $isChildOfCurrentFilePath && !$isCurrent && !$hasDecendent && !$hasSibling;
    $class = implode(' ', [
        'sidenav__item',
        $isLevelOne ? 'sidenav__item--level-one' : '',
        $isCurrent ? 'sidenav__item--current' : '',
        $hasDecendent ? 'sidenav__item--current-is-decendant' : '',
        $hasSibling ? 'sidenav__item--current-is-sibling' : '',
        $isChild ? 'sidenav__item--is-child-of-current' : '',
    ]);
    $sideNavHtml .= "<li class=\"$class\"><a href=\"$href\">$title</a></li>\n";
};

function hasSiblingCurrentFilePath($subStructure, $currentFilePath) {
    for ($i = 1; $i < count($subStructure['files']); $i++) {
        $file = $subStructure['files'][$i];
        $filePath = $subStructure['dir'] . "/$file";
        if ($filePath === $currentFilePath) {
            return true;
        }
    }
    return false;
}

function hasDecendentCurrentFilePath($subStructure, $currentFilePath) {
    for ($i = 0; $i < count($subStructure['files']); $i++) {
        $file = $subStructure['files'][$i];
        $filePath = $subStructure['dir'] . "/$file";
        if ($filePath === $currentFilePath) {
            return true;
        }
    }
    foreach ($subStructure['subdirs'] as $subdir) {
        if (hasDecendentCurrentFilePath($subdir, $currentFilePath)) {
            return true;
        }
    }
    return false;
}

function isChildOfCurrentFilePath($parentSubStructre, $htmlFilePath, $currentFilePath) {
    // check that currentfilepath is in the parent structure
    // $currentFilePathInParentSubStructure = false;
    for ($i = 0; $i < count($parentSubStructre['files']); $i++) {
        $file = $parentSubStructre['files'][$i];
        $filePath = $parentSubStructre['dir'] . "/$file";
        if ($filePath === $currentFilePath) {
            // $currentFilePathInParentSubStructure = true;
            // break;
            return true;
        }
    }
    // if (!$currentFilePathInParentSubStructure) {
    //     return false;
    // }
    return false;
}

function processDirForSideNavHtml($dir, $parentSubStructre, $subStructure, $level, $currentFilePath, &$sideNavHtml) {
    if ($level === 0) {
        $sideNavHtml .= "<ul class=\"sidenav__items sidenav__items--first\">\n";
    } else {
        $sideNavHtml .= "<li><ul class=\"sidenav__items\">\n";
    }
    // loop through files
    $files = $subStructure['files'];
    for ($i = 0; $i < count($files); $i++) {
        $file = $files[$i];
        $htmlFilePath = "$dir/$file";
        $fileLevel = $i == 0 ? $level : $level + 1;
        $hasDecendentCurrentFilePath = false;
        if ($i === 0) {
            $hasDecendentCurrentFilePath = hasDecendentCurrentFilePath($subStructure, $currentFilePath);
        }
        // todo change to isSiblingOfCurrentFilePath($file, ...)
        $hasSiblingCurrentFilePath = hasSiblingCurrentFilePath($subStructure, $currentFilePath);
        $isChildOfCurrentFilePath = isChildOfCurrentFilePath($parentSubStructre, $htmlFilePath, $currentFilePath);
        addHtmlFilePathToSideNavHtml(
            $htmlFilePath,
            $fileLevel,
            $currentFilePath,
            $hasDecendentCurrentFilePath,
            $hasSiblingCurrentFilePath,
            $isChildOfCurrentFilePath,
            $sideNavHtml
        );
        if (count($files) > 1) {
            // index.html is always the first file
            if ($i == 0) {
                $sideNavHtml .= "<li><ul class=\"sidenav__items\">\n";
            } elseif ($i == count($files) - 1) {
                $sideNavHtml .= "</ul></li>\n";
            }
        }
    }
    foreach ($subStructure['subdirs'] as $subdir) {
        $sdir = str_replace("$dir/", '', $subdir['dir']);
        processDirForSideNavHtml("$dir/$sdir", $subStructure, $subdir, $level + 1, $currentFilePath, $sideNavHtml);
    }
    if ($level === 0) {
        $sideNavHtml .= "</ul>\n";
    } else {
        $sideNavHtml .= "</ul></li>\n";
    }
};

function createSideNavHtml($currentFilePath) {
    global $siteDir;
    $sideNavHtml = '';
    $structure = getSiteStructure($siteDir);
    debug($structure);
    processDirForSideNavHtml($structure['dir'], $structure, $structure, 0, $currentFilePath, $sideNavHtml);
    return $sideNavHtml;
}

function debug($var) {
    file_put_contents(__DIR__ . '/../debug.txt', var_export($var, true));
}
