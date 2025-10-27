<?php
// Возвращает "хвост" sync_log.txt (по умолчанию 200 строк).
// При желании можно ограничить доступ только админам.

$n = isset($_GET['n']) ? max(1, min(2000, (int)$_GET['n'])) : 200;
$path = __DIR__ . '/sync_log.txt';

header('Content-Type: text/plain; charset=utf-8');

if (!file_exists($path)) {
  echo "Лог отсутствует.";
  exit;
}

$lines = [];
$fp = fopen($path, 'r');
if (!$fp) { echo "Не удалось открыть лог."; exit; }

$buffer = '';
$pos = -1;
$count = 0;
fseek($fp, 0, SEEK_END);
$filesize = ftell($fp);

while ($filesize + $pos >= 0) {
  fseek($fp, $pos, SEEK_END);
  $char = fgetc($fp);
  if ($char === "\n") {
    $lines[] = strrev($buffer);
    $buffer = '';
    $count++;
    if ($count >= $n) break;
  } else {
    $buffer .= $char;
  }
  $pos--;
}
if ($buffer !== '') $lines[] = strrev($buffer);
fclose($fp);

$lines = array_reverse($lines);
echo implode("\n", $lines);
