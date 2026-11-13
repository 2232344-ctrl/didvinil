<?php
/**
 * VINYL UKRAINE — спільні функції SEO-сторінок (дані з кешу CSV).
 */
mb_internal_encoding('UTF-8');
const SITE = 'https://vinil.in.ua';
const CITIES = [
  'kyiv'=>['Київ','Києві','у Києві'], 'lviv'=>['Львів','Львові','у Львові'], 'dnipro'=>['Дніпро','Дніпрі','у Дніпрі'],
  'odesa'=>['Одеса','Одесі','в Одесі'], 'kharkiv'=>['Харків','Харкові','у Харкові'], 'kryvyi-rih'=>['Кривий Ріг','Кривому Розі','у Кривому Розі'],
];
const MIN_CARDS = 3;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function load_csv(){
  $path = getenv('VINYL_CSV') ?: __DIR__.'/.vinyl-cache/data.csv';
  if (!getenv('VINYL_CSV') && (!is_file($path) || time()-filemtime($path) > 600)) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    $ctx = stream_context_create(['http'=>['timeout'=>10]]);
    @file_get_contents($proto.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').dirname($_SERVER['SCRIPT_NAME']).'/data.php', false, $ctx);
    clearstatcache();
  }
  if (!is_file($path)) return [];
  $fh = fopen($path, 'r'); $rows = [];
  while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) { if (array_filter($r, fn($x)=>trim((string)$x)!=='')) $rows[] = $r; }
  fclose($fh);
  if (!$rows) return [];
  $map = ['timestamp'=>['позначка часу','timestamp'],'recommend'=>['рекомендовано','рекомендація','рекомендую'],'verified'=>['перевірено','перевірений','проверено'],
    'discount'=>['знижка','скидка','discount'],'name'=>['назва/ім\'я','назва/імя','назва','назва/ім’я'],'tags'=>['пропозиції'],
    'website'=>['website'],'telegram'=>['telegram'],'instagram'=>['instagram'],'threads'=>['threads'],'facebook'=>['facebook'],
    'city'=>['місто'],'address'=>['адреса'],'phone'=>['телефон'],'comment'=>['коментар'],'viber'=>['viber'],'whatsapp'=>['whatsapp'],
    'email'=>['e-mail','email'],'discord'=>['discord'],'tiktok'=>['tiktok'],'youtube'=>['youtube'],'pinterest'=>['pinterest']];
  $rev = []; foreach ($map as $k=>$names) foreach ($names as $n) $rev[$n] = $k;
  $hdr = array_map(fn($x)=>mb_strtolower(trim($x)), array_shift($rows));
  $keys = []; foreach ($hdr as $i=>$x) $keys[$i] = ($x===''&&$i===0) ? 'timestamp' : ($rev[$x] ?? null);
  $out = [];
  foreach ($rows as $r) {
    $rec = array_fill_keys(array_keys($map), '');
    foreach ($keys as $i=>$k) if ($k) $rec[$k] = trim($r[$i] ?? '');
    $out[] = $rec;
  }
  return $out;
}

function norm_tags($s){
  $fix = ['кавярня'=>"кав'ярня",'кав’ярня'=>"кав'ярня",'фонокоретори'=>'фонокоректори','аксессуари'=>'аксесуари'];
  $seen = []; $out = [];
  foreach (explode(',', (string)$s) as $t) {
    $t = mb_strtolower(trim($t)); if ($t==='') continue;
    $t = $fix[$t] ?? $t;
    if (!isset($seen[$t])) { $seen[$t]=1; $out[]=$t; }
  }
  return $out;
}
function split_cities($s){ return array_values(array_filter(array_map('trim', preg_split('/[,;]/u', (string)$s)))); }
function ts_of($rec){
  $t = 0;
  if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $rec['timestamp'], $m)) $t = mktime(0,0,0,(int)$m[2],(int)$m[1],(int)$m[3]);
  if (preg_match('/Оновлено\s+(\d{1,2})\.(\d{1,2})\.(\d{4})/u', $rec['comment'], $m)) $t = max($t, mktime(23,59,59,(int)$m[2],(int)$m[1],(int)$m[3]));
  return $t;
}
function map_url($q){ return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($q); }
function tel_href($p){
  $d = preg_replace('/[^\d+]/', '', $p); if ($d==='') return null;
  if ($d[0]==='+') return 'tel:'.$d;
  if (strpos($d,'380')===0) return 'tel:+'.$d;
  if ($d[0]==='0' && strlen($d)===10) return 'tel:+38'.$d;
  return 'tel:'.$d;
}
function href_for($v, $kind){
  $v = trim($v); if ($v==='') return null;
  if (preg_match('~^https?://~i', $v)) return $v;
  if (preg_match('/\s/', $v) && $kind!=='email') return null;
  $x = ltrim($v,'@');
  switch ($kind) {
    case 'telegram': return 'https://t.me/'.$x;
    case 'instagram': return 'https://instagram.com/'.$x;
    case 'facebook': return 'https://facebook.com/'.$x;
    case 'threads': return 'https://threads.net/@'.$x;
    case 'tiktok': return 'https://www.tiktok.com/@'.$x;
    case 'youtube': return 'https://www.youtube.com/@'.$x;
    case 'email': return preg_match('/^[^\s@]+@[^\s@]+$/', preg_replace('/^mailto:/i','',$v)) ? 'mailto:'.preg_replace('/^mailto:/i','',$v) : null;
    case 'discord': return preg_match('~^discord\.(gg|com)/~i',$v) ? 'https://'.$v : (preg_match('~\.[a-z]{2,}(/|$)~i',$v) ? 'https://'.$v : 'https://discord.gg/'.$x);
    default: return preg_match('~\.[a-z]{2,}(/|$)~i', $v) ? 'https://'.$v : null;
  }
}
function link_label($href, $fb){
  $host = preg_replace('/^www\./','', (string)parse_url($href, PHP_URL_HOST));
  foreach (['discogs.com'=>'Discogs','t.me'=>'Telegram','instagram.com'=>'Instagram','facebook.com'=>'Facebook','threads.net'=>'Threads','threads.com'=>'Threads','tiktok.com'=>'TikTok','olx.ua'=>'OLX'] as $d=>$l)
    if ($host===$d || substr($host, -strlen($d)-1)==='.'.$d) return $l;
  return $fb;
}
function display_name($r){
  if ($r['name']!=='') return $r['name'];
  foreach (['website','telegram','instagram','threads','facebook','tiktok'] as $f) if ($r[$f]!=='') return preg_replace('~^(https?://)?(www\.)?~i','',rtrim($r[$f],'/'));
  return 'Без назви';
}
function is_on($v){ return trim((string)$v)==='1'; }

/* Місцезнаходження для міста $city: рядки "Місто, адреса" з картою */
function loc_lines($r, $city){
  $cities = split_cities($r['city']);
  $ents = [];
  foreach (array_filter(array_map('trim', explode(';', $r['address']))) as $x) {
    if (preg_match('/^([^:]{2,30}):\s*(.+)$/u', $x, $m) && in_array(trim($m[1]), $cities, true)) $ents[] = [trim($m[1]), trim($m[2])];
    else $ents[] = [$cities[0] ?? '', $x];
  }
  $lines = [];
  foreach ($ents as [$c,$t]) { if ($city!=='' && $c!==$city) continue; $lines[] = ($c ? $c.', ' : '').$t; }
  if ($city==='') foreach ($cities as $c) { $has=false; foreach($ents as $e) if($e[0]===$c) $has=true; if(!$has) $lines[] = $c; }
  elseif (!$lines) $lines[] = $city;
  return $lines;
}

function card_html($r, $city){
  $name = display_name($r);
  $links = ''; $shown = [];
  foreach ([['website','Сайт'],['telegram','Telegram'],['instagram','Instagram'],['threads','Threads'],['facebook','Facebook'],['tiktok','TikTok'],['youtube','YouTube'],['pinterest','Pinterest'],['viber','Viber'],['whatsapp','WhatsApp'],['discord','Discord'],['email','E-mail']] as [$k,$lab]) {
    $href = href_for($r[$k], $k); if (!$href) continue;
    $n = preg_replace('~^https?://(www\.)?~i','', rtrim(strtolower($href),'/')); if (isset($shown[$n])) continue; $shown[$n]=1;
    $rel = in_array($k, ['website'], true) ? 'noopener' : 'noopener nofollow';
    $links .= '<a href="'.h($href).'" target="_blank" rel="'.$rel.'">'.h(link_label($href,$lab)).'</a>';
  }
  $loc = '';
  foreach (loc_lines($r, $city) as $l) $loc .= '📍 <a href="'.h(map_url($l)).'" target="_blank" rel="noopener" title="Відкрити на карті Google">'.h($l).'</a><br>';
  $loc = $loc ? '<div class="city">'.substr($loc, 0, -4).'</div>' : '';
  $chips = ''; foreach (norm_tags($r['tags']) as $t) $chips .= '<span class="chip">'.h($t).'</span>';
  $ph = [];
  if ($r['phone']!=='') foreach (array_filter(array_map('trim', preg_split('~[,;/]~', $r['phone']))) as $p) { $t = tel_href($p); $ph[] = $t ? '<a href="'.h($t).'">'.h($p).'</a>' : h($p); }
  $disc = '';
  if ($r['discount']!=='') { $d = $r['discount']; $d = (strpos($d,'-')===0 || strpos($d,'%')!==false) ? $d : '-'.$d.'%'; $disc = '<span class="discount-badge">'.h($d).'</span>'; }
  $badges = $disc.(is_on($r['recommend'])?'<span class="tag-badge rec-badge">рекомендовано</span>':'').(is_on($r['verified'])?'<span class="tag-badge ver-badge">перевірено</span>':'');
  return '<article class="card"><div class="card-top"><h3 class="card-name">'.h($name).'</h3><div class="badges">'.$badges.'</div></div>'
    .$loc.($chips?'<div class="chips">'.$chips.'</div>':'')
    .($r['comment']!==''?'<div class="comment">'.h($r['comment']).'</div>':'')
    .($ph?'<div class="phone">☎ '.implode(', ', $ph).'</div>':'')
    .($links?'<div class="links">'.$links.'</div>':'').'</article>';
}

function plural($n, $a, $b, $c){ $m=$n%100; $d=$n%10; return ($m>=11&&$m<=19)?$c:($d===1?$a:(($d>=2&&$d<=4)?$b:$c)); }

/* ---------- Категорії (теги -> SEO-сторінки /katalog/<slug>/[<місто>/]) ---------- */
const CATS = [
  'plativky'    => ['Магазини вінілових платівок', ['платівки','вініл','вінтаж'], 'магазини нових і вживаних платівок, вінтажний вініл, лейбли та онлайн-магазини'],
  'prohravachi' => ['Магазини програвачів вінілу', ['програвачі'], 'де купити програвач вінілу: нові моделі, вінтаж, комплектуючі, голки та консультації'],
  'remont'      => ['Ремонт і налаштування програвачів вінілу', ['ремонт','налаштування','реставрація','модернізація','сервіс'], 'майстри з ремонту, налаштування, реставрації та модернізації програвачів і hi-fi техніки'],
  'hifi'        => ['Hi-Fi техніка: акустика та підсилювачі', ['акустика','hi-fi','hi-end','підсилювачі','ресивери','фонокоректори'], 'магазини акустики, підсилювачів, ресиверів і фонокоректорів для домашнього аудіо'],
  'kasety-cd'   => ['Магазини касет і CD', ['касети','cd','касетні деки'], 'де купити аудіокасети, компакт-диски та касетні деки'],
  'vintazh'     => ['Вінтажний вініл і ретро-техніка', ['вінтаж'], 'вінтажні платівки, радянські та європейські видання, ретро-техніка'],
  'kavyarni'    => ["Кав'ярні та бари з вінілом", ["кав'ярня",'бар'], 'місця, де грає вініл і можна слухати платівки за кавою чи напоями'],
];
const CAT_PLACES = ['kyiv','lviv','dnipro','odesa','kharkiv','kryvyi-rih'];

function is_closed($r){ $t = norm_tags($r['tags']); return in_array('зачинено',$t,true) || in_array('закрито',$t,true); }
function active_rows(){ return array_values(array_filter(load_csv(), fn($r)=>array_filter($r, fn($x)=>$x!=='') && !is_closed($r))); }
function cat_rows($rows, $slug, $city=''){
  $set = CATS[$slug][1]; $out = [];
  foreach ($rows as $r) {
    if (!array_intersect($set, norm_tags($r['tags']))) continue;
    if ($city!=='' && !in_array($city, split_cities($r['city']), true)) continue;
    $out[] = $r;
  }
  return $out;
}
function sort_rows(&$list){
  usort($list, function($a,$b){
    $ra = is_on($a['recommend'])?0:(is_on($a['verified'])?1:2); $rb = is_on($b['recommend'])?0:(is_on($b['verified'])?1:2);
    return $ra<=>$rb ?: ts_of($b)<=>ts_of($a);
  });
}
function city_counts($rows){ $c=[]; foreach ($rows as $r) foreach (split_cities($r['city']) as $x) $c[$x]=($c[$x]??0)+1; return $c; }

/* Єдиний шаблон SEO-сторінки. $o: title, desc, url, ld, crumbs(html), h2, intro(html), updated, groups[[label,[[href,text,count,on],...]]], list, city, more(html) */
function render_page($o){
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: public, max-age=600');
  ?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?=h($o['title'])?></title>
<meta name="description" content="<?=h($o['desc'])?>">
<link rel="canonical" href="<?=h($o['url'])?>">
<meta property="og:title" content="<?=h($o['title'])?>">
<meta property="og:description" content="<?=h($o['desc'])?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="VINYL UKRAINE">
<meta property="og:locale" content="uk_UA">
<meta property="og:url" content="<?=h($o['url'])?>">
<meta property="og:image" content="<?=SITE?>/og-image.png">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#b5482b">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%23181613'/%3E%3Ccircle cx='50' cy='50' r='16' fill='%23b5482b'/%3E%3Ccircle cx='50' cy='50' r='3' fill='%23181613'/%3E%3C/svg%3E">
<script type="application/ld+json"><?=json_encode($o['ld'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<style><?=SEO_CSS?></style>
</head>
<body>
<header>
  <div class="header-inner">
    <div class="title-row">
      <h1><a href="/" style="color:inherit;text-decoration:none">VINYL <span>UKRAINE</span></a></h1>
      <div class="top-actions">
        <button class="iconbtn" id="themeBtn" type="button" aria-label="Змінити тему" title="Тема">◐</button>
        <a class="addbtn" href="https://docs.google.com/forms/d/1Ch0VdB88GLL0KL8flPXCxhkavUXXy0gpCD1Q-sTQX4Y/viewform" target="_blank" rel="noopener">＋ Доповнити</a>
      </div>
    </div>
  </div>
</header>
<main>
  <nav class="crumbs" aria-label="Навігація"><?=$o['crumbs']?></nav>
  <section class="hero">
    <h2><?=h($o['h2'])?></h2>
    <p><?=$o['intro']?></p>
    <?php if ($o['updated']): ?><p style="color:var(--muted);font-size:13px">Оновлено <?=date('d.m.Y', $o['updated'])?></p><?php endif; ?>
    <?php foreach ($o['groups'] as [$label, $items]): if (!$items) continue; ?>
    <div class="navchips" aria-label="<?=h($label)?>">
      <?php foreach ($items as [$href,$text,$cnt,$on]): ?>
        <a href="<?=h($href)?>"<?=$on?' class="on"':''?>><?=h($text)?><?php if ($cnt!==null): ?> <small><?=$cnt?></small><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <div class="grid">
    <?php foreach ($o['list'] as $r) echo card_html($r, $o['city']), "\n"; ?>
  </div>
  <p class="seo-more"><?=$o['more']?></p>
</main>
<footer>
  Рік створення сайту: 2026. З усіх питань звертайтесь: <a href="mailto:vinyl.help.kiev@gmail.com">vinyl.help.kiev@gmail.com</a>
</footer>
<script>(function(){var k="vinyl-theme",r=document.documentElement;try{var s=localStorage.getItem(k);if(s)r.setAttribute("data-theme",s);}catch(e){}
document.getElementById("themeBtn").addEventListener("click",function(){var d=r.getAttribute("data-theme");var dark=d?d==="dark":matchMedia("(prefers-color-scheme: dark)").matches;var n=dark?"light":"dark";r.setAttribute("data-theme",n);try{localStorage.setItem(k,n);}catch(e){}});})();</script>
</body>
</html>
<?php }

function not_found(){
  http_response_code(404); header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>404</title><p>Сторінку не знайдено. <a href="/">До довідника</a>'; exit;
}

const SEO_CSS = <<<'CSS'
  :root{
    --bg:#f6f3ee; --card-bg:#fff; --text:#1c1a17; --muted:#6b665e;
    --accent:#b5482b; --accent-dark:#8f3820; --border:#e4ddd1;
    --chip-bg:#efe6d8; --chip-text:#5a4b32;
    --pin-bg:#fff4e0; --pin-border:#e0a941; --green:#2e8b57;
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --bg:#181613; --card-bg:#221f1b; --text:#f1ece3; --muted:#a89f8f;
      --accent:#e2733f; --accent-dark:#f08a58; --border:#332e27;
      --chip-bg:#332b20; --chip-text:#e2cfa8; --pin-bg:#3a2c14; --green:#4caf82;
    }
  }
  :root[data-theme="dark"]{
    --bg:#181613; --card-bg:#221f1b; --text:#f1ece3; --muted:#a89f8f;
    --accent:#e2733f; --accent-dark:#f08a58; --border:#332e27;
    --chip-bg:#332b20; --chip-text:#e2cfa8; --pin-bg:#3a2c14; --green:#4caf82;
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0; background:var(--bg); color:var(--text);
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    min-height:100vh; -webkit-text-size-adjust:100%;
  }
  :focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:6px;}
  header{
    position:sticky; top:0; z-index:10;
    padding:env(safe-area-inset-top,0px) 16px 0;
    background:var(--bg); border-bottom:1px solid var(--border);
    transition:transform .25s ease;
  }
  @media (max-width:640px){ header.header-hidden{transform:translateY(-100%);} }
  .header-inner{max-width:1080px;margin:0 auto;padding:14px 0;}
  .title-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:4px;}
  h1{font-size:22px;margin:0;letter-spacing:.3px;}
  h1 span{color:var(--accent);}
  .top-actions{display:flex;gap:8px;align-items:center;}
  .addbtn{
    white-space:nowrap; padding:8px 14px; border-radius:12px;
    background:var(--accent); color:#fff; font-size:14px; font-weight:600; text-decoration:none;
  }
  .addbtn:hover{background:var(--accent-dark);}
  .iconbtn{
    width:36px;height:36px;border-radius:12px;border:1px solid var(--border);
    background:var(--card-bg);color:var(--text);font-size:16px;cursor:pointer;
  }
  .sub{color:var(--muted);font-size:13px;margin:0 0 12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
  .sync-dot{width:8px;height:8px;border-radius:50%;background:#9a9a9a;display:inline-block;margin-inline-end:4px;}
  .sync-dot.ok{background:#4caf82;} .sync-dot.err{background:#c0553a;}
  .controls{display:flex;gap:8px;flex-wrap:wrap;}
  .search-wrap{position:relative;flex:1 1 200px;}
  .search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--muted);pointer-events:none;}
  input[type=search], select{
    padding:11px 14px;border-radius:12px;border:1px solid var(--border);
    background:var(--card-bg);color:var(--text);font-size:16px;font-family:inherit;
  }
  input[type=search]{width:100%;padding-left:38px;-webkit-appearance:none;appearance:none;}
  select{max-width:100%;font-size:14px;}
  button.sortbtn{
    padding:11px 14px;border-radius:12px;border:1px solid var(--border);
    background:var(--card-bg);color:var(--text);font-size:14px;cursor:pointer;
  }
  .quick{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;}
  .quick button{
    font-family:inherit;font-size:12.5px;padding:6px 11px;border-radius:999px;
    border:1px solid var(--border);background:var(--card-bg);color:var(--text);cursor:pointer;
  }
  .quick button:hover{border-color:var(--accent);}
  .quick button small{opacity:.65;font-size:11px;margin-left:2px;}
  .quick button.limit-shake{animation:shake .3s;}
  .quick button.on{background:var(--accent);color:#fff;border-color:var(--accent-dark);}
  main{max-width:1080px;margin:0 auto;padding:14px 16px 16px;}
  .count{color:var(--muted);font-size:13px;margin-bottom:8px;}
  .active-filters{display:none;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:10px;}
  .filter-pill{display:inline-flex;align-items:center;gap:4px;background:var(--accent);color:#fff;font-size:12px;font-weight:600;padding:5px 6px 5px 10px;border-radius:999px;}
  .pill-x{background:rgba(255,255,255,.25);border:none;color:#fff;width:18px;height:18px;border-radius:50%;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0;}
  .clear-filters{background:none;border:1px solid var(--border);color:var(--muted);font-size:12px;padding:5px 10px;border-radius:999px;cursor:pointer;}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}
  .card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:8px;min-width:0;}
  .card.pinned{border-color:var(--pin-border);background:var(--pin-bg);}
  .card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
  .card-name{font-size:17px;font-weight:700;margin:0;overflow-wrap:anywhere;}
  .badges{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex:0 0 auto;}
  .tag-badge,.discount-badge{font-size:11px;font-weight:700;border-radius:999px;padding:4px 10px;white-space:nowrap;border:1px solid transparent;font-family:inherit;line-height:1.3;}
  button.tag-badge{cursor:pointer;} button.tag-badge:hover{border-color:var(--accent);}
  .tag-badge.active{outline:2px solid var(--accent);outline-offset:1px;}
  .rec-badge{background:rgba(224,169,65,.25);color:var(--accent-dark);border-color:var(--pin-border);}
  .ver-badge{background:rgba(46,139,87,.15);color:var(--green);border-color:var(--green);}
  .discount-badge{color:#fff;background:var(--green);border-radius:6px;}
  .city{font-size:13px;color:var(--muted);}
  .city a{color:inherit;text-decoration:underline dotted;text-underline-offset:3px;}
  .city a:hover{color:var(--text, currentColor);text-decoration-style:solid;}
  .city .addr{display:block;margin-top:2px;}
  .chips{display:flex;flex-wrap:wrap;gap:6px;}
  .chip{background:var(--chip-bg);color:var(--chip-text);font-size:12px;padding:4px 9px;border-radius:999px;border:1px solid transparent;font-family:inherit;cursor:pointer;line-height:1.3;}
  .chip:hover{border-color:var(--accent);}
  .chip.active{background:var(--accent);color:#fff;border-color:var(--accent-dark);}
  .chip.limit-shake{animation:shake .3s;}
  @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}
  .comment{font-size:13.5px;opacity:.85;line-height:1.4;overflow-wrap:anywhere;}
  .phone{font-size:13.5px;}
  .phone a{color:inherit;}
  .links{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:8px;}
  .links a{font-size:12.5px;text-decoration:none;color:var(--accent-dark);border:1px solid var(--border);padding:6px 10px;border-radius:9px;}
  .links a:hover{border-color:var(--accent);}
  .empty{text-align:center;color:var(--muted);padding:60px 16px;}
  footer{max-width:1080px;margin:0 auto;padding:10px 16px calc(30px + env(safe-area-inset-bottom,0px));color:var(--muted);font-size:12px;}
  footer a{color:var(--accent-dark);}
  .totop{
    position:fixed;right:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));
    width:44px;height:44px;border-radius:50%;border:1px solid var(--border);
    background:var(--card-bg);color:var(--text);font-size:18px;cursor:pointer;
    display:none;box-shadow:0 2px 10px rgba(0,0,0,.15);
  }
  .totop.show{display:block;}
  @media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important;}}

  .crumbs{font-size:13px;color:var(--muted);margin:0 0 10px}.crumbs a{color:var(--accent-dark)}
  .hero h2{font-size:22px;margin:4px 0 8px}.hero p{margin:0 0 10px;line-height:1.5;color:var(--text);max-width:760px;font-size:15px}
  .navchips{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 16px}
  .navchips a{font-size:12.5px;padding:6px 11px;border-radius:999px;border:1px solid var(--border);background:var(--card-bg);color:var(--text);text-decoration:none}
  .navchips a:hover,.navchips a.on{border-color:var(--accent)}.navchips a.on{background:var(--accent);color:#fff}
  .chip{cursor:default}.card-name a{color:inherit;text-decoration:none}
  .seo-more{margin-top:26px;color:var(--muted);font-size:13.5px;line-height:1.5}.seo-more a{color:var(--accent-dark)}
CSS;
