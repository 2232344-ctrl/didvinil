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
  'more'  => 'Не знайшли свого магазину чи майстра? <a href="https://docs.google.com/forms/d/1Ch0VdB88GLL0KL8flPXCxhkavUXXy0gpCD1Q-sTQX4Y/viewform" target="_blank" rel="noopener">Додайте його в довідник</a> або перегляньте <a href="/">повний каталог з пошуком і фільтрами</a>.',
]);
