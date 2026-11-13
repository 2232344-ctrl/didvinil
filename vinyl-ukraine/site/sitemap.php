<?php
/** Карта сайту: головна + міста + категорії (і їх комбінації з містами) з >= MIN_CARDS записами. Адреса: /sitemap/ */
require __DIR__.'/seo-lib.php';
$rows = active_rows(); $cc = city_counts($rows);
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
function u($p,$f,$pr){ echo '<url><loc>'.SITE.$p.'</loc><changefreq>'.$f.'</changefreq><priority>'.$pr.'</priority></url>'."\n"; }
u('/','daily','1.0');
foreach (CITIES as $slug=>$c) if (($cc[$c[0]] ?? 0) >= MIN_CARDS) u('/vinil/'.$slug.'/','weekly','0.8');
foreach (CATS as $k=>$_) {
  if (count(cat_rows($rows,$k)) >= MIN_CARDS) u('/katalog/'.$k.'/','weekly','0.7');
  foreach (CITIES as $slug=>$c) if (count(cat_rows($rows,$k,$c[0])) >= MIN_CARDS) u('/katalog/'.$k.'/'.$slug.'/','weekly','0.6');
}
echo '</urlset>'."\n";
