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

// TODO: this depends on the source, though keep it as 5 for now
define('CURRENT_CMS_MAJOR', '5');

function updateChildrenHtml(
    string $htmlFilePath,
    array $relatedChildPaths,
    array $childDirectoryFilePaths,
    array $grandChildIndexFilePaths
): string {
    // [CHILDREN] basically means 'siblings'
    $contents = file_get_contents($htmlFilePath);
    if (empty($relatedChildPaths)) {
        return $contents;
    }
    return preg_replace_callback('#(?<!<code>)\[CHILDREN(.*?)\]#', function($matches) use (
        $htmlFilePath,
        $relatedChildPaths,
        $childDirectoryFilePaths,
        $grandChildIndexFilePaths,
    ) {
        global $siteDir, $htmlFilePathToMetadata;
        $opts = trim($matches[1]);
        $paths = $relatedChildPaths;
        $asList = strpos($opts, 'asList') !== false;
        $reverse = strpos($opts, 'reverse') !== false;
        $includeFolders = strpos($opts, 'includeFolders') !== false;
        if (!$includeFolders) {
            // only include siblings i.e. do not include <$dirname>/folder/index.html files
            $paths = array_filter($paths, function($path) use ($htmlFilePath) {
                $dirname = dirname($htmlFilePath);
                $basename = basename($path);
                return "$dirname/$basename" === $path;
            });
        }
        $exclude = '';
        if (preg_match('/Exclude="?([^"]+)"?/', $opts, $m)) {
            $exclude = trim($m[1]);
        }
        $only = '';
        if (preg_match('/Only="?([^"]+)"?/', $opts, $m)) {
            $only = trim($m[1]);
        }
        $folder = '';
        if (preg_match('/Folder="?([^"]+)"?/', $opts, $m)) {
            $folder = $m[1];
            if (!array_key_exists($folder, $childDirectoryFilePaths) && !array_key_exists($folder, $grandChildIndexFilePaths)) {
                // Do not throw exception as this will be running in GitHub Actions
                echo "\n! WARNING: Folder '$folder' not found in grandChildIndexFilePaths - parsing $htmlFilePath\n\n";
                // The incorrect [CHILDREN folder=""] will simply be ignored
                return;
            }
            if (isset($childDirectoryFilePaths[$folder]) && !empty($childDirectoryFilePaths[$folder])) {
                $paths = $childDirectoryFilePaths[$folder];
            } else {
                $paths = $grandChildIndexFilePaths[$folder];
            }
        }
        $html = '<ul>';
        if ($reverse) {
            $paths = array_reverse($paths);
        }

        // sort by title (actually don't)
        $pathTitles = [];
        foreach ($paths as $path) {
            $metadata = $htmlFilePathToMetadata[$path] ?? [];
            $relatedHtml = file_get_contents($path);
            $title = getTitle($metadata, $relatedHtml, $path);
            $pathTitles[$path] = $title;
        }
        if (false) {
            // TODO: do not sort by title, as it will not match sidenav html
            // which is currently unsortable as it's raw html
            asort($pathTitles);
        }

        foreach (array_keys($pathTitles) as $path) {
            $pathForFilename = $path;
            if ($includeFolders) {
                $pathForFilename = str_replace('/index.html', '', $pathForFilename);
            }
            $filename = basename($pathForFilename);
            if ($exclude) {
                foreach (explode(',', $exclude) as $excluded) {
                    if (trim($excluded) === $filename) {
                        continue 2;
                    } elseif (strtolower(trim($excluded)) === strtolower($filename)) {
                        // Do not throw exception as this will be running in GitHub Actions
                        echo "\n! WARNING: 'Exclude' casing does not match for $exclude - parsing $htmlFilePath\n\n";
                        // Still behave as if the casing was correct
                        continue 2;
                    }
                    // treat as if includeFolders is true when using exclude
                    $folderName = basename(str_replace('/index.html', '', $path));
                    if (trim($excluded) === $folderName) {
                        continue 2;
                    } elseif (strtolower(trim($excluded)) === strtolower($folderName)) {
                        // Do not throw exception as this will be running in GitHub Actions
                        echo "\n! WARNING: 'Exclude' casing does not match for $exclude - parsing $htmlFilePath\n\n";
                        // Still behave as if the casing was correct
                        continue 2;
                    }
                }
            }
            if ($only) {
                $found = false;
                foreach (explode(',', $only) as $onlyed) {
                    if (trim($onlyed) === $filename) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }
            }
            $metadata = $htmlFilePathToMetadata[$path] ?? [];
            $relatedHtml = file_get_contents($path);
            $title = getTitle($metadata, $relatedHtml, $path);
            $href = ltrim(str_replace($siteDir, '', $path), '/');
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
                $summary = $metadata['summary'] ?? '';
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

function makeHtmlPage(string $title, string $contentHtml, string $sideNavHtml):string
{
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

/**
 * Update links to HTML anchors on the current page to include the $htmlFilePath
 * This needs to be done because the presence of a base metatag in the head
 * means that relative links will not work as expected
 */
function updateHtmlLinksToRelativeAnchors(string $contentHtml, string$htmlFilePath): string
{
    $htmlFilePath = str_replace('/index.html', '', $htmlFilePath);
    return preg_replace_callback('/<a href="(#.*?)"/', function($matches) use ($htmlFilePath) {
        $href = $matches[1];
        return "<a href=\"{$htmlFilePath}{$href}\"";
    }, $contentHtml);
}

/**
 * Adds anchor links to headings in the given HTML content for headings that do not already have an ID
 */
function addAnchorLinksToHeadings(string $contentHtml): string
{
    return preg_replace_callback('#<h([1-5])>([^<]+)</h[1-5]>#', function($matches) {
        $level = $matches[1];
        $text = $matches[2];
        $id = strtolower(str_replace(' ', '-', preg_replace('#[^a-zA-Z0-9\- ]#', '', $text)));
        return "<h$level id=\"$id\">$text</h$level>";
    }, $contentHtml);
}

function updateApiLinks(string $contentHtml): string
{
    $currentCmsMajor = CURRENT_CMS_MAJOR;
    return preg_replace_callback('/href="api:([^"]+)"/', function($matches) use ($currentCmsMajor) {
        $class = urlencode($matches[1]);
        return "href=\"https://api.silverstripe.org/search/lookup?q=$class&version=$currentCmsMajor\"";
    }, $contentHtml);
}

function getTitle(array $metadata, string $html, string$htmlFilePath): string
{
    return $metadata['title'] ?? getH1fromHtml($html) ?: basename($htmlFilePath);
}

function getH1fromHtml(string $html):string
{
    preg_match('/<h1>(.*?)<\/h1>/', $html, $matches);
    return $matches[1] ?? '';
}

function getMetadataFromMd(string $md): array
{
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

function removeMetadataFromMd(string $md)
{
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

function convertMarkdownToHtml(string $md)
{
    $parser = new MarkdownExtra();
    $parser->code_attr_on_pre = true;
    $parser->code_class_prefix = 'snippet snippet--';
    $html = $parser->transform($md);
    $html = updateAlerts($html);
    return $html;
}

function updateAlerts(string $html)
{
    $types = ['NOTE', 'TIP', 'WARNING', 'IMPORTANT', 'CAUTION'];
    foreach ($types as $type) {
        $ltype = strtolower($type);
        $find = "<blockquote>\n  <p>[!$type]";
        $replacement = "<blockquote class=\"alert alert--$ltype\">\n  <p>";
        $html = str_replace($find, $replacement, $html);
    }
    return $html;
}

function getSiteStructure(string $dir, array &$structure = [])
{
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
    string $htmlFilePath,
    int $level,
    string $currentFilePath,
    bool $hasDecendentCurrentFilePath,
    bool $isSiblingOfCurrentFilePath,
    bool $isChildOfCurrentFilePath,
    string &$sideNavHtml
): void {
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

function isSiblingOfCurrentFilePath(
    array $parentSubStructure,
    array $subStructure,
    string $htmlFilePath,
    string$currentFilePath
): bool {
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

function hasDecendentCurrentFilePath(
    array $subStructure,
    string $currentFilePath
): bool {
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

function isChildOfCurrentFilePath(
    array $parentSubStructure,
    array $subStructure,
    string $htmlFilePath,
    string $currentFilePath
): bool {
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

function processDirForSideNavHtml(
    string $dir,
    array $parentSubStructure,
    array $subStructure,
    int $level,
    string $currentFilePath,
    string &$sideNavHtml
): void {
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

function createSideNavHtml(string $currentFilePath): string
{
    global $siteDir;
    $sideNavHtml = '';
    $structure = getSiteStructure($siteDir);
    debug($structure);
    processDirForSideNavHtml($structure['dir'], $structure, $structure, 0, $currentFilePath, $sideNavHtml);
    return $sideNavHtml;
}

function debug(mixed $var): void
{
    file_put_contents(__DIR__ . '/../debug.txt', var_export($var, true));
}
