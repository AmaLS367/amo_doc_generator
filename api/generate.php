<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone']);
$DIR_MODE  = $config['dir_mode'];
$FILE_MODE = $config['file_mode'];

use PhpOffice\PhpWord\TemplateProcessor;

// Directory setup / Пути к директориям
$baseDir  = realpath(__DIR__ . '/..');
$docDir  = rtrim($config['document_path'], '/');
$logDir  = rtrim($config['logs_path'], '/');
$prefDir = rtrim($config['temp_data_path'], '/').'/prefill';
@is_dir($docDir)  || @mkdir($docDir,  $DIR_MODE, true);
@is_dir($logDir)  || @mkdir($logDir,  $DIR_MODE, true);
@is_dir($prefDir) || @mkdir($prefDir, $DIR_MODE, true);
$LOG = $logDir . '/generate.log';
$log = function($x) use($LOG){ file_put_contents($LOG, json_encode($x, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n", FILE_APPEND); };

// get input data / получить входные данные
$raw = file_get_contents('php://input');
if (!empty($config['hmac_secret'])) {
    $sig  = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $calc = hash_hmac('sha256', $raw, $config['hmac_secret']);
    if (!hash_equals($calc, $sig)) { http_response_code(401); echo json_encode(['error'=>'bad signature']); exit; }
}
$in  = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) { http_response_code(400); echo json_encode(['error'=>'Bad JSON']); exit; }

$leadId   = (int)($in['lead_id'] ?? 0);
$template = (($in['template'] ?? 'order') === 'act') ? 'act' : 'order';
$products = is_array($in['products'] ?? null) ? $in['products'] : [];
$discount = (int)($in['discount'] ?? 0);

if ($leadId <= 0 || !count($products)) { http_response_code(400); echo json_encode(['error'=>'Invalid lead_id or products']); exit; }

// Load configuration and tokens / Загрузка конфигурации и токенов
$tokenPath = $config['token_path'];
$tokens    = json_decode(@file_get_contents($tokenPath), true);

function saveTokens(array $t, string $p){ $t['created_at']=time(); file_put_contents($p, json_encode($t, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
function refreshToken(array $cfg, array &$t, string $p){
  $payload = ['client_id'=>$cfg['client_id'],'client_secret'=>$cfg['client_secret'],'grant_type'=>'refresh_token','refresh_token'=>$t['refresh_token']??'','redirect_uri'=>$cfg['redirect_uri']];
  $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>rtrim($cfg['base_domain'],'/').'/oauth2/access_token',CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>20]); $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code!==200) throw new RuntimeException("REFRESH {$code}: {$resp}");
  $new=json_decode($resp,true); if(empty($new['access_token'])) throw new RuntimeException('REFRESH: empty access_token');
  $t=$new; saveTokens($t,$p);
}
function amoRequest(string $url, array &$t, array $cfg, string $p): array{
  $do=function($tk,$u){ $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>$u,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Authorization: Bearer {$tk}"],CURLOPT_TIMEOUT=>25]); $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$c,$r]; };
  [$c,$r]=$do($t['access_token']??'', $url);
  if($c===401){ refreshToken($cfg,$t,$p); [$c,$r]=$do($t['access_token'],$url); }
  if($c<200||$c>=300) throw new RuntimeException("AMO {$c}: {$r}");
  $j=json_decode($r,true); if(!is_array($j)) throw new RuntimeException('AMO bad JSON');
  return $j;
}

// Morphing function for Russian words / Функция морфологии для русского языка
// This function is used to correctly decline words based on the number (e.g., "руб
function morph_ru($n,$f1,$f2,$f5){ $n=abs($n)%100; $n1=$n%10; if($n>10&&$n<20)return $f5; if($n1>1&&$n1<5)return $f2; if($n1==1)return $f1; return $f5; }
function rublesToWords(int $n): string {
    if ($n === 0) return 'ноль рублей';
    $morph=function($n,$f1,$f2,$f5){$n=abs($n)%100;$n1=$n%10;if($n>10&&$n<20)return $f5;if($n1>1&&$n1<5)return $f2;if($n1==1)return $f1;return $f5;};
    $w1=['','один','два','три','четыре','пять','шесть','семь','восемь','девять'];
    $w1f=['','одна','две','три','четыре','пять','шесть','семь','восемь','девять'];
    $w10=['десять','одиннадцать','двенадцать','тринадцать','четырнадцать','пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать'];
    $w2=['','десять','двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят','восемьдесят','девяносто'];
    $w3=['','сто','двести','триста','четыреста','пятьсот','шестьсот','семьсот','восемьсот','девятьсот'];
    $units=[['рубль','рубля','рублей',0],['тысяча','тысячи','тысяч',1],['миллион','миллиона','миллионов',0]];
    $parts=[];$i=0;$t=$n;
    while($t>0&&$i<count($units)){
        $x=$t%1000;if($x){
            $g=$units[$i][3];$s=[];
            $s[]=$w3[intval($x/100)];
            $y=$x%100;
            if($y>=10&&$y<20){$s[]=$w10[$y-10];}
            else{$s[]=$w2[intval($y/10)];$s[]=( $g?$w1f[$y%10]:$w1[$y%10] );}
            $s[]=$morph($x,$units[$i][0],$units[$i][1],$units[$i][2]);
            $parts[]=trim(implode(' ',array_filter($s)));
        }
        $t=intdiv($t,1000);$i++;
    }
    $text=implode(' ',array_reverse($parts));
    // если не добавилось слово про валюту (например, 4000 -> "четыре тысячи"), дописываем "рублей"
    // if it did not add a word about the currency (for example, 4000 -> "four thousand"), we add "rubles"
    if (!preg_match('/руб(ль|ля|лей)\b/u',$text)) $text.=' рублей';
    return trim(preg_replace('/\s+/u',' ',$text));
}


// Main logic / Основная логика
try{
  // token check and refresh / проверка токена и обновление
  $lead = amoRequest(rtrim($config['base_domain'],'/')."/api/v4/leads/{$leadId}?with=contacts", $tokens, $config, $tokenPath);
  $cid  = $lead['_embedded']['contacts'][0]['id'] ?? null;
  $contact = $cid ? amoRequest(rtrim($config['base_domain'],'/')."/api/v4/contacts/{$cid}", $tokens, $config, $tokenPath) : null;

  $fio = $contact['name'] ?? '';
  $phone = '';
  foreach (($contact['custom_fields_values'] ?? []) as $f) {
    if (($f['field_code'] ?? '') === 'PHONE') { $phone = $f['values'][0]['value'] ?? ''; break; }
  }

  // If no contact found, use lead's phone if available
  $fields = $lead['custom_fields_values'] ?? [];
  $getCF = function($fields,$name){ foreach($fields as $f){ if(($f['field_name']??'')===$name) return $f['values'][0]['value']??''; } return ''; };

  // Сумма и скидка / Total and discount
  $sum = 0; foreach($products as $p){ $sum += (int)($p['price'] ?? 0); }
  $total = max(0, $sum - $discount);

  // template path / путь к шаблону
  $tplDir  = rtrim($config['template_path'], '/');
  $tplFile = ($template === 'act') ? 'act_template.docx' : 'order_template.docx';
  $tpl     = $tplDir . '/' . $tplFile;
  if (!is_file($tpl)) { http_response_code(500); echo json_encode(['error'=>'Template not found']); exit; }

  // чистим прошлые файлы этой сделки / clean up old files for this lead
  foreach (glob($docDir . "/doc_{$leadId}_*.docx") as $old) @unlink($old);

  $tp = new TemplateProcessor($tpl); 
    // поля сделки и ФИО / deal fields and FIO
    $fields = $lead['custom_fields_values'] ?? [];
    $get = function($fields, $name){
        foreach ($fields as $f) if (($f['field_name'] ?? '') === $name) return $f['values'][0]['value'] ?? '';
        return '';
    };

    // ФИО из кастом-полей, если пусто — парсим contact.name / FIO from custom fields, if empty — parse contact.name
    list($p1,$p2,$p3) = array_pad(preg_split('/\s+/', trim($contact['name'] ?? ''), 3), 3, '');
    $lastName  = $getCF($fields,'Фамилия')  ?: $p1;
    $firstName = $getCF($fields,'Имя')      ?: $p2;
    $middle    = $getCF($fields,'Отчество') ?: $p3;

    // базовые поля сделки / basic deal fields
    $tp->setValue('Номер', $leadId);
    $tp->setValue('Дата', date('d.m.Y'));
    $tp->setValue('Телефон', $phone ? ' '.$phone : '');
    $tp->setValue('Марка', $getCF($fields,'Марка') ?: '—');
    $tp->setValue('Модель', $getCF($fields,'Модель') ?: '—');
    $tp->setValue('VIN', $getCF($fields,'VIN') ?: '—');
    $tp->setValue('Год выпуска', $getCF($fields,'Год выпуска') ?: '—');

    // ФИО / FIO
    $tp->setValue('Фамилия',  $lastName);
    $tp->setValue('Имя',      $firstName);
    $tp->setValue('Отчество', $middle);

    // табличка услуг / services table
    if ($template === 'order' && count($products)) {
        $tp->cloneRow('row_num', count($products)); // клон по базовому тегу / clone by base tag

        $sumGross = 0; $sumAfter = 0;
        foreach ($products as $i=>$it) {
            $n    = $i + 1;
            $name = $it['name'] ?? '';
            $qty  = (int)($it['qty'] ?? ($it['quantity'] ?? 1));
            $unit = (int)($it['unit_price'] ?? (int)($it['price'] ?? 0) / max(1,$qty));
            $gross = $unit * max(1,$qty);

            $discP = (float)($it['discount_percent'] ?? 0);
            $discR = (int)($it['discount'] ?? 0);
            $after = $gross;
            if ($discP > 0) $after = (int)round($gross * (1 - $discP/100));
            if ($discR > 0) $after = max(0, $after - $discR);

            $sumGross += $gross; $sumAfter += $after;

            $tp->setValue("row_num#{$n}", $n);
            $tp->setValue("услуга_название#{$n}", $name);
            $tp->setValue("row_qty#{$n}", $qty);
            $tp->setValue("row_price#{$n}", number_format($unit,0,',',' '));
            $tp->setValue("row_discount#{$n}", $discP>0 ? rtrim(rtrim(number_format($discP,2,'.',''), '0'), '.') : ($discR>0 ? $discR : '-'));
            $tp->setValue("row_sum#{$n}", number_format($after,0,',',' '));
        }
    }


    // Итоги из products: поддержка unit_price+qty, price, скидок по строке / Totals from products: support for unit_price+qty, price, discounts per line
    $sum_gross = 0;
    $sum_after = 0;

    foreach ($products as $it) {
        $qty  = (int)($it['qty'] ?? ($it['quantity'] ?? 1));
        $unit = (int)($it['unit_price'] ?? 0);
        $gross = $unit ? $unit * max(1, $qty) : (int)($it['price'] ?? 0);

        $discP = (float)($it['discount_percent'] ?? 0);
        $discR = (int)($it['discount'] ?? 0);
        $after = $gross;
        if ($discP > 0) $after = (int)round($gross * (1 - $discP/100));
        if ($discR > 0) $after = max(0, $after - $discR);

        $sum_gross += $gross;
        $sum_after += $after;
    }

    $global = (int)$discount;
    $total  = max(0, $sum_after - $global);

    $tp->setValue('Итого', $sum_gross);
    $tp->setValue('Скидка', $global);
    $tp->setValue('Всего к оплате', $total);
    $tp->setValue('Количество наименований', count($products));
    $tp->setValue('Сумма прописью', rublesToWords($total));

  $filename = "doc_{$leadId}_" . time() . ".docx";
  $savePath = $docDir . '/' . $filename;
  $tp->saveAs($savePath);
  @chmod($savePath, $FILE_MODE);
  $publicDocs = rtrim($config['public_documents_url'], '/');
  $url = $publicDocs . '/' . rawurlencode($filename);

    // кэш для префилла (1–7 дней) / cache for prefill (1-7 days)
    $cacheDir = rtrim($config['cache_path'] ?? (rtrim($config['temp_data_path'],'/').'/cache'), '/');
    @mkdir($cacheDir, $DIR_MODE, true);
    file_put_contents(
        $cacheDir . '/' . $leadId . '.json',
        json_encode([
            'saved_at' => time(),
            'template' => $template,
            'discount' => $discount,
            'products' => $products
        ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
    );


  // примечание: удалить предыдущее (если можно), создать новое / note: delete previous (if possible), create new
  $metaPath = $prefDir . "/lead_{$leadId}_meta.json";
  $meta = is_file($metaPath) ? json_decode(file_get_contents($metaPath), true) : [];
  $prevNoteId = $meta['note_id'] ?? null;

  $notesBase = rtrim($config['base_domain'],'/') . '/api/v4/leads/notes';
  $do = function($method,$url,$token,$payload=null){
    $ch=curl_init(); curl_setopt_array($ch,[
      CURLOPT_URL=>$url, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_HTTPHEADER=>array_filter(["Authorization: Bearer {$token}", $payload?"Content-Type: application/json":null]),
      CURLOPT_POSTFIELDS=>$payload?json_encode($payload, JSON_UNESCAPED_UNICODE):null, CURLOPT_TIMEOUT=>20
    ]); $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$code,$resp];
  };

  // попытка удалить прошлое примечание (если API разрешит) / attempt to delete the previous note (if API allows)
  if ($prevNoteId) {
    [$dc,$dr] = $do('DELETE', $notesBase.'/'.(int)$prevNoteId, $tokens['access_token']);
    // игнорируем ошибки удаления — не критично / ignore delete errors — not critical
  }

  // создаём новое примечание / create a new note
  $title = ($template==='act' ? 'Акт приёма-передачи' : 'Заказ-наряд');
  $text  = "{$title} №{$leadId}: {$url}";
  [$pc,$pr] = $do('POST', $notesBase, $tokens['access_token'], [[
    'entity_id'=>(int)$leadId,'entity_type'=>'leads','note_type'=>'common','params'=>['text'=>$text]
  ]]);
  if ($pc>=200 && $pc<300) {
    $r = json_decode($pr, true);
    $newId = $r['_embedded']['notes'][0]['id'] ?? null;
    if ($newId) { $meta['note_id'] = $newId; file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
  }

  echo json_encode(['url'=>$url], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e){
  $log(['EX'=>$e->getMessage(),'line'=>$e->getLine()]);
  http_response_code(500);
  echo json_encode(['error'=>'Internal Server Error'], JSON_UNESCAPED_UNICODE);
}
