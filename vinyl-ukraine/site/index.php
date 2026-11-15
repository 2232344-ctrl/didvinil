<?php
/**
 * VINYL UKRAINE — головна сторінка з серверним рендером.
 * Бере page.html (дизайн і JS без змін; раніше index.html) і одразу вставляє в нього всі картки,
 * щоб пошуковики й AI-боти бачили каталог без виконання JavaScript.
 * Після завантаження JS, як і раніше, перемальовує список (пошук, фільтри).
 */
require __DIR__.'/seo-lib.php';

$tpl = is_file(__DIR__.'/page.html') ? '/page.html' : '/index.html'; // page.html: щоб /index.html не віддавався сервером напряму
$html = @file_get_contents(__DIR__.$tpl);
if ($html === false) { http_response_code(500); exit('page.html not found'); }

try { $rows = active_rows(); } catch (Throwable $e) { $rows = []; }

if ($rows) {
  sort_rows($rows);
  $pin = null; // DiDViNiL завжди першим (як PIN_NAME у JS)
  foreach ($rows as $i => $r) if (mb_strtolower(trim($r['name'] ?? ''), 'UTF-8') === 'didvinil') { $pin = $i; break; }
  if ($pin !== null) { $p = $rows[$pin]; array_splice($rows, $pin, 1); array_unshift($rows, $p); }

  $cards = '';
  foreach ($rows as $i => $r) {
    $c = card_html($r, '');
    $c = str_replace(['<h3 class="card-name">', '</h3>'], ['<h2 class="card-name">', '</h2>'], $c);
    if ($i === 0 && $pin !== null) $c = preg_replace('/^<article class="card"/', '<article class="card pinned"', $c);
    $cards .= $c;
  }
  $n = count($rows);
  $updated = 0; foreach ($rows as $r) $updated = max($updated, ts_of($r));

  $ld = ['@context'=>'https://schema.org','@graph'=>[
    ['@type'=>'WebSite','@id'=>SITE.'/#website','url'=>SITE.'/','name'=>'VINYL UKRAINE','inLanguage'=>'uk',
     'description'=>'Довідник магазинів вінілових платівок, програвачів, hi-fi техніки та майстрів в Україні.'],
    ['@type'=>'CollectionPage','@id'=>SITE.'/#page','url'=>SITE.'/','name'=>'VINYL UKRAINE — довідник вінілової спільноти',
     'isPartOf'=>['@id'=>SITE.'/#website'],'inLanguage'=>'uk','dateModified'=>$updated ? date('Y-m-d', $updated) : null,
     'mainEntity'=>['@type'=>'ItemList','numberOfItems'=>$n,'itemListElement'=>array_map(
        fn($r,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>display_name($r)], $rows, array_keys($rows))]],
  ]];
  $ldTag = '<script type="application/ld+json">'.json_encode($ld, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';

  $html = str_replace('<div class="grid" id="grid"></div>', '<div class="grid" id="grid">'.$cards.'</div>', $html);
  $html = str_replace('<span id="totalCount">0 записів</span>', '<span id="totalCount">'.$n.' записів</span>', $html);
  $html = preg_replace('~</head>~', $ldTag."\n</head>", $html, 1);
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=300');
echo $html;
