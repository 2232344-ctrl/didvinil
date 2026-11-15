<?php
/**
 * VINYL UKRAINE — SEO-сторінка категорії:
 *   /katalog/plativky/        -> category.php?k=plativky
 *   /katalog/plativky/kyiv/   -> category.php?k=plativky&c=kyiv
 */
require __DIR__.'/seo-lib.php';
$k = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['k'] ?? ''));
$slug = preg_replace('/[^a-z-]/', '', strtolower($_GET['c'] ?? ''));
if (!isset(CATS[$k])) not_found();
if ($slug !== '' && !isset(CITIES[$slug])) not_found();
[$name, , $about] = CATS[$k];
$city = $slug !== '' ? CITIES[$slug][0] : '';
$inCity = $slug !== '' ? CITIES[$slug][2] : 'в Україні';

$rows = active_rows();
$all  = cat_rows($rows, $k);
$list = $slug !== '' ? cat_rows($rows, $k, $city) : $all;
if (count($list) < MIN_CARDS) not_found();
sort_rows($list);
$n = count($list);
$word = plural($n, 'адреса', 'адреси', 'адрес');
$url = SITE.'/katalog/'.$k.'/'.($slug !== '' ? $slug.'/' : '');
$updated = 0; foreach ($list as $r) $updated = max($updated, ts_of($r));
$h2 = $name.' '.$inCity;

$places = []; $cc = city_counts($all);
foreach (CITIES as $s=>[$cn]) { $m = count(cat_rows($rows, $k, $cn)); if ($m >= MIN_CARDS) $places[] = ['/katalog/'.$k.'/'.$s.'/', $cn, $m, $s===$slug]; }
array_unshift($places, ['/katalog/'.$k.'/', 'Уся Україна', count($all), $slug==='']);
$others = [];
foreach (CATS as $ok=>$c) { if ($ok===$k) continue; $m = count($slug!=='' ? cat_rows($rows,$ok,$city) : cat_rows($rows,$ok)); if ($m >= MIN_CARDS) $others[] = ['/katalog/'.$ok.'/'.($slug!==''?$slug.'/':''), $c[0], $m, false]; }
if ($slug !== '') $others[] = ['/vinil/'.$slug.'/', 'Усі адреси '.$inCity, null, false];

$crumbs = '<a href="/">Довідник</a> › '.($slug!=='' ? '<a href="/katalog/'.h($k).'/">'.h($name).'</a> › '.h($city) : h($name));
$ldCrumbs = [['Довідник',SITE.'/']];
if ($slug!=='') { $ldCrumbs[] = [$name, SITE.'/katalog/'.$k.'/']; $ldCrumbs[] = [$city, $url]; } else $ldCrumbs[] = [$name, $url];

render_page([
  'title' => "$h2 — $n $word | VINYL UKRAINE",
  'desc'  => "$h2: $n $word — $about. Адреси, телефони, соцмережі та карта.",
  'url'   => $url,
  'ld'    => ['@context'=>'https://schema.org','@graph'=>[
      ['@type'=>'BreadcrumbList','itemListElement'=>array_map(fn($c,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$c[0],'item'=>$c[1]], $ldCrumbs, array_keys($ldCrumbs))],
      ['@type'=>'ItemList','name'=>$h2,'numberOfItems'=>$n,'itemListElement'=>array_map(fn($r,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>display_name($r)], array_slice($list,0,50), array_keys(array_slice($list,0,50)))]]],
  'crumbs'=> $crumbs,
  'h2'    => $h2,
  'intro' => 'У довіднику VINYL UKRAINE '.$n.' '.h($word).' у категорії «'.h($name).'» '.h($inCity).': '.h($about).'. Для кожного — адреса з посиланням на карту, телефони й соцмережі. Спершу показані рекомендовані та перевірені.',
  'updated'=> $updated,
  'groups'=> [['Міста',$places], ['Інші категорії',$others]],
  'list'  => $list,
  'city'  => $city,
  'more'  => 'Не знайшли свого магазину чи майстра? <a href="mailto:vinyl.help.kiev@gmail.com?subject=%D0%94%D0%BE%D0%B4%D0%B0%D1%82%D0%B8%20%D0%BC%D0%B0%D0%B3%D0%B0%D0%B7%D0%B8%D0%BD%20%D1%83%20VINYL%20UKRAINE&body=%D0%9D%D0%B0%D0%B7%D0%B2%D0%B0%3A%0A%D0%9C%D1%96%D1%81%D1%82%D0%BE%20%D1%82%D0%B0%20%D0%B0%D0%B4%D1%80%D0%B5%D1%81%D0%B0%3A%0A%D0%A2%D0%B5%D0%BB%D0%B5%D1%84%D0%BE%D0%BD%3A%0A%D0%A1%D0%B0%D0%B9%D1%82%3A%0AInstagram%20/%20Telegram%20/%20Facebook%3A%0A%D0%A9%D0%BE%20%D0%BF%D1%80%D0%BE%D0%BF%D0%BE%D0%BD%D1%83%D1%8E%D1%82%D1%8C%20%28%D0%BF%D0%BB%D0%B0%D1%82%D1%96%D0%B2%D0%BA%D0%B8%2C%20%D0%BF%D1%80%D0%BE%D0%B3%D1%80%D0%B0%D0%B2%D0%B0%D1%87%D1%96%2C%20%D1%80%D0%B5%D0%BC%D0%BE%D0%BD%D1%82%E2%80%A6%29%3A%0A">Додайте його в довідник</a> або перегляньте <a href="/">повний каталог з пошуком і фільтрами</a>.',
]);
