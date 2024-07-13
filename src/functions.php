<?php

use Michelf\MarkdownExtra;

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
    $parser = new MarkdownExtra();
    $parser->code_attr_on_pre = true;
    $parser->code_class_prefix = 'snippet snippet--';
    $html = $parser->transform($md);
    return $html;
}

function extractTipThings($md) {
    $codeFences = [];
    $i = 0;
    $md = preg_replace_callback('/```([A-Za-z]*)\n(.*?)```/s', function ($matches) use (&$codeFences, $i) {
        $lang = $matches[1] ?: 'plaintext';
        $code = $matches[2];
        $codeFences[$i] = ['lang' => $lang, 'code' => $code];
        $i++;
        return "CODE-FENCE-$i";
    }, $md);
    return [$md, $codeFences];
}

function addextractTipThingsToHtml($html, $codeFences) {
    return preg_replace_callback('/CODE-FENCE-(\d+)/', function ($matches) use ($codeFences) {
        $i = $matches[1] - 1;
        $lang = $codeFences[$i]['lang'];
        $code = $codeFences[$i]['code'];
        return '<pre class="snippet snippet--' . $lang . '"><code>' . htmlspecialchars($code) . '</code></pre>';
    }, $html);
}
