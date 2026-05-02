<?php
function cheveretoID(string|int $in, string $action = 'encode'): string|int
{
    $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $salt = ''; // Emulate empty salt
    $id_padding = 0;
    
    for ($n = 0; $n < strlen($index); ++$n) {
        $i[] = substr($index, $n, 1);
    }
    $passhash = hash('sha256', $salt);
    $passhash = (strlen($passhash) < strlen($index)) ? hash('sha512', $salt) : $passhash;
    for ($n = 0; $n < strlen($index); ++$n) {
        $p[] = substr($passhash, $n, 1);
    }
    array_multisort($p, SORT_DESC, $i);
    $index = implode('', $i);
    $base = strlen($index);
    if ($action === 'decode') {
        $out = 0;
        $len = strlen($in) - 1;
        for ($t = 0; $t <= $len; ++$t) {
            $bcpow = bcpow((string) $base, (string) ($len - $t));
            $out = $out + strpos($index, substr((string)$in, $t, 1)) * $bcpow;
        }
        if ($id_padding > 0) {
            $out = $out / $id_padding;
            if (! is_int($out)) {
                $out = 0;
            }
        }
        $out = (int) sprintf('%s', $out);
    } else {
        if ($id_padding > 0) {
            $in = $in * $id_padding;
        }
        $out = '';
        for ($t = floor(log((float) $in, $base)); $t >= 0; --$t) {
            $bcp = bcpow((string) $base, (string) $t);
            $a = floor($in / $bcp) % $base;
            $out = $out . substr($index, $a, 1);
            $in = $in - ($a * $bcp);
        }
    }

    return $out;
}

$ids = ['AOk', 'YzJ', 'YKd', 'csr', 'wSC', 'beZ', '2gM', 'AGp', 'A2e', 'gry', 'A3v', 'zmN', 'xoK', 'ujY', 'UMS', 'oFb', 'zbG', 'xHT'];

foreach ($ids as $id) {
    $decoded = cheveretoID($id, 'decode');
    echo "$id -> $decoded\n";
}
