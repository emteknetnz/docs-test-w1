<?php

use Michelf\Markdown;

function makeHtmlPage($basename, $contentHtml, $sideNavHtml) {
    global $siteDir, $templatesDir;
    $base = strpos(dirname(__DIR__), '/home/runner/') !== false
        ? 'https://emteknetnz.github.io/docs-test-w1/'
        : "file://$siteDir/";
    $title = getH1fromHtml($contentHtml) ?: $basename;
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

function getH1fromHtml($html) {
    preg_match('/<h1>(.*?)<\/h1>/', $html, $matches);
    return $matches[1] ?? '';
}

function convertMarkdownToHtml($md) {
    [$md, $codeSnippets] = extractCodeSnippets($md);
    $html = Markdown::defaultTransform($md);
    $html = addCodeSnippetsToHtml($html, $codeSnippets);
    return $html;
}

function extractCodeSnippets($md) {
    $codeSnippets = [];
    $i = 0;
    $md = preg_replace_callback('/```([A-Za-z]*)\n(.*?)```/s', function ($matches) use (&$codeSnippets, $i) {
        $lang = $matches[1] ?: 'plaintext';
        $code = $matches[2];
        $codeSnippets[$i] = ['lang' => $lang, 'code' => $code];
        $i++;
        return "CODE-SNIPPET-PLACEHOLDER-$i";
    }, $md);
    return [$md, $codeSnippets];
}

function addCodeSnippetsToHtml($html, $codeSnippets) {
    return preg_replace_callback('/CODE-SNIPPET-PLACEHOLDER-(\d+)/', function ($matches) use ($codeSnippets) {
        $i = $matches[1] - 1;
        $lang = $codeSnippets[$i]['lang'];
        $code = $codeSnippets[$i]['code'];
        return '<pre class="snippet snippet--' . $lang . '"><code>' . htmlspecialchars($code) . '</code></pre>';
    }, $html);
}
