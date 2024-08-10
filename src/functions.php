<?php

use Michelf\MarkdownExtra;

/*
02_Documentation.md

### Linking to child and sibling pages {#link-to-children}

You can list child/sibling pages using the special `[CHILDREN]` syntax. By default these will render as cards with an icon, a title, and a summary if one is available.

You can change what is displayed using one of the `Exclude`, `Folder`, or `Only` modifiers. These all take folder and/or file names as arguments. Exclude the `.md` extension when referencing files. Arguments can include a single item, or multiple items using commas to separate them.

- `[CHILDREN Exclude="How_tos,01_Relations"]`: Exclude specific folders or files from the list. Note that folders don't need to be excluded unless the `includeFolders` modifier is also used.
- `[CHILDREN Only="rc,beta"]`: Only include the listed items. This is the inverse of the `Exclude` modifier.
- `[CHILDREN Folder="How_Tos"]`: List the children of the named folder, instead of the children of the *current* folder. This modifier only accepts a single folder as an argument.

The above can be combined with any of the `asList`, `includeFolders`, and `reverse` modifiers:

- `[CHILDREN asList]`: Render the children as a description list instead of as cards. The icon is not used when rendering as a list.
- `[CHILDREN includeFolders]`: Include folders as well as files.
- `[CHILDREN reverse]`: Reverse the order of the list. The list is sorted in case sensitive ascending alphabetical order by default.

The following would render links for all children as a description list in reverse order, including folders but excluding anything called "How_tos":

`[CHILDREN Exclude="How_tos" asList includeFolders reverse]`
*/

function updateChildrenHtml($htmlFilePath, $relatedChildPaths) {
    // [CHILDREN] basically means 'siblings'
    $contents = file_get_contents($htmlFilePath);
    if (empty($relatedChildPaths)) {
        return $contents;
    }
    return preg_replace_callback('#\[CHILDREN(.*?)\]#', function($m) use ($relatedChildPaths, $htmlFilePath) {
        global $siteDir, $htmlFilePathToMetadata;
        $opts = trim($m[1]);
        $asList = strpos($opts, 'list') !== false;
        $reverse = strpos($opts, 'reverse') !== false;
        $includeFolders = strpos($opts, 'includeFolders') !== false;
        $exclude = '';
        if (preg_match('/Exclude="(.+?)"/', $opts, $m)) {
            $exclude = trim($m[1]);
        }
        $folder = '';
        if (preg_match('/Folder="(.+?)"/', $opts, $m)) {
            $folder = $m[1];
        }
        $html = '<ul>';
        if ($reverse) {
            $relatedChildPaths = array_reverse($relatedChildPaths);
        }
        foreach ($relatedChildPaths as $relatedChildPath) {
            if ($exclude) {
                $filename = basename(str_replace('/index.html', '', $relatedChildPath), $relatedChildPath);
                foreach (explode(',', $exclude) as $excluded) {
                    if ($excluded === $filename) {
                        continue 2;
                    }
                }
            }
            $metadata = $htmlFilePathToMetadata[$relatedChildPath] ?? [];
            $relatedHtml = file_get_contents($relatedChildPath);
            $title = getTitle($metadata, $relatedHtml, $relatedChildPath);
            $href = ltrim(str_replace($siteDir, '', $relatedChildPath), '/');
            $classes = [
                'related-child',
            ];
            if ($asList) {
                $classes[] = 'related-child__list';
                $class = implode(' ', $classes);
                $html .= "<li class=\"$class\"><a href=\"$href\">$title</a></li>";
            } else {
                $classes[] = 'related-child__card';
                $class = implode(' ', $classes);
                $title = $metadata['title'] ?? '';
                $summary = $metadata['summary'] ?? '';
                // $introduction = $metadata['introduction'] ?? '';
                $icon = $metadata['icon'] ?? '';
                $html .= "<li class=\"$class\"><a href=\"$href\">
                    <div class=\"related-child__card-icon\">$icon</div>
                    <div class=\"related-child__card-content\">
                        <h2 class=\"related-child__card-title\">$title</h2>
                        <p class=\"related-child__card-summary\">$summary</p>
                    </div>
                </a></li>";
            }
        }
        $html .= '</ul>';
        return $html;
    }, $contents);
}

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
            'files' => [],
            'subdirs' => [],
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
    $isSiblingOfCurrentFilePath,
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
    $hasSibling = $isSiblingOfCurrentFilePath && !$isCurrent && !$hasDecendent;
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

function isSiblingOfCurrentFilePath($parentSubStructure, $subStructure, $htmlFilePath, $currentFilePath) {
    for ($i = 1; $i < count($subStructure['files']); $i++) {
        $file = $subStructure['files'][$i];
        $filePath = $subStructure['dir'] . "/$file";
        if ($filePath === $currentFilePath) {
            return true;
        }
    }
    foreach ($subStructure['subdirs'] as $subdir) {
        $filePath = $subdir['dir'] . '/index.html';
        if ($filePath === $currentFilePath) {
            return true;
        }
    }
    if (basename($htmlFilePath) === 'index.html') {
        for ($i = 1; $i < count($parentSubStructure['files']); $i++) {
            $file = $parentSubStructure['files'][$i];
            $filePath = $parentSubStructure['dir'] . "/$file";
            if ($filePath === $currentFilePath) {
                return true;
            }
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

function isChildOfCurrentFilePath($parentSubStructure, $subStructure, $htmlFilePath, $currentFilePath) {
    // Is in the same dir as the currently selected index.html
    $indexFilePath = $subStructure['dir'] . '/' . $subStructure['files'][0];
    if ($indexFilePath === $currentFilePath) {
        return true;
    }
    // Is the index.html of a subdir of the currently selected index.html
    $parentIndexFilePath = $parentSubStructure['dir'] . '/' . $parentSubStructure['files'][0];
    if ($parentIndexFilePath === $currentFilePath) {
        foreach ($parentSubStructure['subdirs'] as $subdir) {
            $indexFilePath = $subdir['dir'] . '/' . $subdir['files'][0];
            if ($indexFilePath === $htmlFilePath) {
                return true;
            }
        }
    }
    return false;
}

function processDirForSideNavHtml($dir, $parentSubStructure, $subStructure, $level, $currentFilePath, &$sideNavHtml) {
    if ($level === 0) {
        $sideNavHtml .= "<ul class=\"sidenav__items sidenav__items--first\">\n";
    } else {
        $sideNavHtml .= "<li><ul class=\"sidenav__items\">\n";
    }
    $files = $subStructure['files'];
    for ($i = 0; $i < count($files); $i++) {
        $file = $files[$i];
        $htmlFilePath = "$dir/$file";
        $fileLevel = $i == 0 ? $level : $level + 1;
        $hasDecendentCurrentFilePath = false;
        $isChildOfCurrentFilePath = isChildOfCurrentFilePath($parentSubStructure, $subStructure, $htmlFilePath, $currentFilePath);
        if ($i === 0) {
            $hasDecendentCurrentFilePath = hasDecendentCurrentFilePath($subStructure, $currentFilePath);
        }
        $isSiblingOfCurrentFilePath = isSiblingOfCurrentFilePath($parentSubStructure, $subStructure, $htmlFilePath, $currentFilePath);
        addHtmlFilePathToSideNavHtml(
            $htmlFilePath,
            $fileLevel,
            $currentFilePath,
            $hasDecendentCurrentFilePath,
            $isSiblingOfCurrentFilePath,
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
