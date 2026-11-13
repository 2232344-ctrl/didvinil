<?php
/**
 * VINYL UKRAINE — SEO-сторінка міста (серверний рендер): /vinil/kyiv/ -> city.php?c=kyiv
 */
require __DIR__.'/seo-lib.php';
$slug = preg_replace('/[^a-z-]/', '', strtolower($_GET['c'] ?? ''));
if (!isset(CITIES[$slug])) not_found();
[$city, $locative, $inCity] = CITIES[$slug];

$rows = active_rows();
$cityCount = city_counts($rows);
$list = array_values(array_filter($rows, fn($r)=>in_array($city, split_cities($r['city']), true)));
if (count($list) < MIN_CARDS) not_found();
sort_rows($list);
$n = count($list);
$word = plural($n, 'адреса', 'адреси', 'адрес');
$url = SITE.'/vinil/'.$slug.'/';
$updated = 0; foreach ($list as $r) $updated = max($updated, ts_of($r));

$cities = []; foreach (CITIES as $s=>[$cn]) if (($cityCount[$cn] ?? 0) >= MIN_CARDS) $cities[] = ['/vinil/'.$s.'/', $cn, $cityCount[$cn], $s===$slug];
$cities[] = ['/', 'Уся Україна →', null, false];
$cats = [];
foreach (CATS as $k=>$c) { $m = count(cat_rows($list, $k)); if ($m >= MIN_CARDS) $cats[] = ['/katalog/'.$k.'/'.$slug.'/', $c[0], $m, false]; }

render_page([
  'title' => "Магазини вінілу $inCity — $n $word | VINYL UKRAINE",
  'desc'  => "Де купити вінілові платівки $inCity: $n $word — магазини, програвачі, hi-fi, ремонт і майстри. Адреси, телефони, соцмережі та карта.",
  'url'   => $url,
  'ld'    => ['@context'=>'https://schema.org','@graph'=>[
      ['@type'=>'BreadcrumbList','itemListElement'=>[
        ['@type'=>'ListItem','position'=>1,'name'=>'Довідник','item'=>SITE.'/'],
        ['@type'=>'ListItem','position'=>2,'name'=>$city,'item'=>$url]]],
      ['@type'=>'ItemList','name'=>"Магазини вінілу $inCity",'numberOfItems'=>$n,'itemListElement'=>array_map(fn($r,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>display_name($r)], array_slice($list,0,50), array_keys(array_slice($list,0,50)))]]],
  'crumbs'=> '<a href="/">Довідник</a> › '.h($city),
  'h2'    => "Магазини вінілових платівок $inCity",
  'intro' => 'У довіднику VINYL UKRAINE '.$n.' '.h($word).' '.h($inCity).': магазини платівок, програвачі та hi-fi техніка, ремонт і майстри, а також місця, де слухають вініл. Для кожного — адреса з посиланням на карту, телефони й соцмережі. Спершу показані рекомендовані та перевірені.',
  'updated'=> $updated,
  'groups'=> [['Інші міста',$cities], ['Категорії '.$inCity,$cats]],
  'list'  => $list,
  'city'  => $city,
  'more'  => 'Не знайшли свого магазину чи майстра? <a href="https://docs.google.com/forms/d/1Ch0VdB88GLL0KL8flPXCxhkavUXXy0gpCD1Q-sTQX4Y/viewform" target="_blank" rel="noopener">Додайте його в довідник</a> або перегляньте <a href="/">повний каталог з пошуком і фільтрами</a>.',
]);
