<?php
/* Trustmont × Wyndham webinar: receives a registration from the page and forwards it
   to the Uspacy CRM, the bord.ee Telegram chat-bot, a Telegram chat and a Google Sheet. Secrets live in lead-config.php. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$cfg = @include __DIR__ . '/lead-config.php';
if (!is_array($cfg)) $cfg = [];

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { http_response_code(400); echo '{"ok":false,"error":"bad_json"}'; exit; }

function v($a, $k, $max = 200) { return isset($a[$k]) && is_scalar($a[$k]) ? mb_substr(trim((string)$a[$k]), 0, $max) : ''; }

/* bots fill the hidden field: answer ok, send nothing */
if (v($in, 'website') !== '') { echo '{"ok":true}'; exit; }

$name  = v($in, 'first_name', 100);
$email = v($in, 'email', 150);
$phone = preg_replace('/\D+/', '', v($in, 'phone', 40));   // digits only, no "+"
if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($phone) < 8) {
  http_response_code(422); echo '{"ok":false,"error":"invalid"}'; exit;
}
$utm = [];
foreach (['utm_source','utm_medium','utm_campaign','utm_content','utm_term'] as $k) $utm[$k] = v($in, $k);
$ads = [];   // ad click ids and Meta pixel cookies, for the bord.ee bot
foreach (['fbclid','gclid','ttclid','fbp','fbc'] as $k) if (v($in, $k, 500) !== '') $ads[$k] = v($in, $k, 500);
$country = v($in, 'country', 4);
$page    = v($in, 'page', 300);
$when    = (new DateTime('now', new DateTimeZone('Europe/Kyiv')))->format('Y-m-d H:i:s');

function post_json($url, $data, $timeout = 10) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,      // Google Apps Script answers with a redirect
    CURLOPT_TIMEOUT => $timeout,
  ]);
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return ['code' => $code, 'body' => is_string($body) ? mb_substr($body, 0, 300) : '', 'err' => $err];
}

$res = [];

/* 1. bord.ee chat-bot: makes the contact and answers with a personal link into the bot (chat_url) */
$chat_url = '';
$bordee_url = $cfg['bordee_url'] ?? 'https://bord.ee/hooks/trustmont-capital/sites/vebinar-wyndham-14112026-telegram-bot/lead';
if ($bordee_url) {
  $res['bordee'] = post_json($bordee_url, array_merge([
    'name' => $name, 'phone' => '+' . $phone, 'email' => $email, 'country' => $country, 'page_url' => $page,
  ], $utm, $ads));
  $j = json_decode($res['bordee']['body'], true);
  if (is_array($j) && !empty($j['chat_url']) && preg_match('~^https://t\.me/[A-Za-z0-9_]+(\?start=[A-Za-z0-9_-]+)?$~', $j['chat_url'])) $chat_url = $j['chat_url'];
}

/* the visitor should land in the bot right away: once bord.ee has the lead, answer the page now
   and let the CRM, Telegram and the Sheet finish in the background */
$answered = false;
$ok = function ($r) { return $r && $r['code'] >= 200 && $r['code'] < 300; };
if (isset($res['bordee']) && $ok($res['bordee'])) {
  ignore_user_abort(true);
  $out = json_encode(['ok' => true, 'chat_url' => $chat_url]);
  header('Content-Length: ' . strlen($out));
  header('Connection: close');
  echo $out;
  while (ob_get_level() > 0) ob_end_flush();
  flush();
  if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
  elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
  $answered = true;
}

/* 2. CRM (Uspacy lead processor) */
$crm_url = $cfg['crm_url'] ?? 'https://rubicon.tips/uspacy/customers/pro_part_real_estate/leads_processor/leads_processor.php';
$res['crm'] = post_json($crm_url, array_merge([
  'title'      => '[Webinar] Отельная недвижимость без посредников: ' . $name,
  'first_name' => $name,
  'phone'      => $phone,
  'email'      => $email,
  'source'     => 'reklama_target',
], $utm));

/* 3. Telegram */
if (!empty($cfg['tg_token']) && !empty($cfg['tg_chat_id'])) {
  $lines = ["🎟 Новая регистрация на вебинар 14.11", "", "Имя: $name", "Телефон: +$phone", "Email: $email"];
  if ($country) $lines[] = "Страна: $country";
  foreach ($utm as $k => $val) if ($val !== '') $lines[] = "$k: $val";
  $lines[] = "Время: $when (Киев)";
  $res['tg'] = post_json('https://api.telegram.org/bot' . $cfg['tg_token'] . '/sendMessage',
    ['chat_id' => $cfg['tg_chat_id'], 'text' => implode("\n", $lines), 'disable_web_page_preview' => true]);
}

/* 4. Google Sheet (Apps Script web app that appends a row) */
if (!empty($cfg['sheet_url'])) {
  $res['sheet'] = post_json($cfg['sheet_url'], array_merge([
    'secret' => $cfg['sheet_secret'] ?? '', 'date' => $when, 'first_name' => $name, 'phone' => '+' . $phone,
    'email' => $email, 'country' => $country, 'page' => $page,
  ], $utm), 15);
}

/* local backup of every lead, so nothing is lost if a service is down (folder is closed to the web) */
$dir = __DIR__ . '/leads-data';
if (!is_dir($dir)) { @mkdir($dir, 0750); @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n"); }
@file_put_contents("$dir/leads.csv", implode(';', array_map(function ($x) { return '"' . str_replace('"', '""', $x) . '"'; }, [
  $when, $name, '+' . $phone, $email, $country, $utm['utm_source'], $utm['utm_medium'], $utm['utm_campaign'], $utm['utm_content'], $utm['utm_term'],
  'crm:' . ($res['crm']['code'] ?? 0), 'bordee:' . ($res['bordee']['code'] ?? '-'), 'tg:' . ($res['tg']['code'] ?? '-'), 'sheet:' . ($res['sheet']['code'] ?? '-'),
])) . "\n", FILE_APPEND | LOCK_EX);

/* service answers, for troubleshooting (same closed folder) */
@file_put_contents("$dir/log.txt", $when . ' ' . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

if ($answered) exit;
/* the visitor sees success when at least one destination took the lead */
$any = $ok($res['crm']) || (isset($res['bordee']) && $ok($res['bordee'])) || (isset($res['tg']) && $ok($res['tg'])) || (isset($res['sheet']) && $ok($res['sheet']));
if (!$any) { http_response_code(502); echo '{"ok":false,"error":"upstream"}'; exit; }
echo json_encode(['ok' => true, 'chat_url' => $chat_url]);
