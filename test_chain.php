<?php
$chain_sizes = ['original', 'image', 'thumb', 'medium', 'frame'];

function test_chain($chain) {
    global $chain_sizes;
    $chain_mask = str_split(
        (string) str_pad(
            decbin((int)$chain),
            5,
            '0',
            STR_PAD_LEFT
        )
    );
    
    $chain_to_suffix = [
        'frame' => '.fr.',
        'original' => '.or.',
        'image' => '.',
        'thumb' => '.th.',
        'medium' => '.md.',
    ];
    
    $targets = [
        'type' => 'url',
        'chain' => array_combine(
            $chain_sizes,
            array_fill(0, count($chain_sizes), null)
        ),
    ];
    foreach ($chain_mask as $k => $v) {
        if (! (bool) $v) {
            unset($targets['chain'][$chain_sizes[$k]]);
        }
    }
    
    return isset($targets['chain']['image']);
}

for ($c = 1; $c <= 31; $c++) {
    if (test_chain($c)) {
        echo "$c works!\n";
    }
}
