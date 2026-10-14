<?php
// Read-only JUnit comparison. No assertions or application code are modified.
$root = dirname(__DIR__, 2);
$evidence = $root.'/docs/enfa-release-gate';
$baseRoot = trim(file_get_contents(sys_get_temp_dir().'/enfa-release-base-directory.txt'));
function normalized(string $s): string {
    global $root, $baseRoot;
    $s = str_replace([str_replace('\\', '/', $root), str_replace('\\', '/', $baseRoot), $root, $baseRoot], '<ROOT>', str_replace("\r\n", "\n", $s));
    // Printed stack paths may escape each Windows separator twice.
    $s = str_replace([str_replace('\\', '\\\\', $root), str_replace('\\', '\\\\', $baseRoot)], '<ROOT>', $s);
    $s = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '<UUID>', $s);
    $s = preg_replace('/(name="_token"[^>]*value=")[^"]+/', '$1<CSRF>', $s);
    $s = preg_replace('/\b\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)?)?\b/', '<DATETIME>', $s);
    $s = preg_replace('/\b\d{2}\/\d{2}\/\d{4}\b|\b\d{2}:\d{2}(?::\d{2})?\b/', '<TIME>', $s);
    $s = preg_replace('/\(length: \d+\)/', '(length: N)', $s);
    $s = preg_replace('/\b[0-9a-f]{13}(?=@|\.test|\.zigo|[-.])/', '<UNIQID>', $s);
    // Archive extraction changes filemtime; CSS bytes are separately compared.
    $s = preg_replace('/(zigo-design-system\.css\?v=)\d+/', '$1<FILEMTIME>', $s);
    return trim($s);
}
function cases(string $path): array {
    $xml = simplexml_load_file($path);
    $out = [];
    foreach ($xml->xpath('//testcase') as $t) {
        $key = (string)$t['class'].'::'.(string)$t['name'];
        $failure = $t->failure ?? $t->error ?? null;
        if (!$failure || !count($failure)) continue;
        $message = (string)$failure;
        $signature = [];
        if (preg_match('/Expected response status code \[(\d+)\] but received (\d+)\./', $message, $m)) {
            $signature = ['assertion'=>'assertStatus', 'expected'=>$m[1], 'actual'=>$m[2]];
        } elseif (preg_match('/contains "([^"]+)" \[/', $message, $m)) {
            $signature = ['assertion'=>'assertSee', 'expected'=>$m[1], 'actual'=>'missing from HTTP response'];
        } elseif (str_contains($message, 'false is true')) {
            $signature = ['assertion'=>'assertTrue', 'expected'=>'true', 'actual'=>'false'];
        } elseif (str_contains($message, 'null is not null')) {
            $signature = ['assertion'=>'assertNotNull', 'expected'=>'route exists', 'actual'=>'null'];
        }
        if (str_contains($message, 'no such table: network_subscriptions')) $signature['cause']='SQLite fixture lacks network_subscriptions';
        preg_match_all('#(tests/Feature/[^\r\n]+\.php):(\d+)#', str_replace('\\', '/', $message), $loc);
        $out[$key] = ['suite'=>(string)$t['class'], 'test'=>(string)$t['name'], 'file'=>str_replace('\\','/',end($loc[1]) ?: (string)$t['file']),
            'assertion_line'=>end($loc[2]) ?: (string)$t['line'], 'type'=>(string)$failure['type'], 'signature'=>$signature, 'normalized'=>normalized($message)];
    }
    return $out;
}
$base=cases($evidence.'/baseline.xml'); $rc=cases($evidence.'/'.($argv[1] ?? 'rc-final.xml')); $rows=[]; $index=0;
foreach($rc as $key=>$r){
    $b=$base[$key]??null; $i=++$index;
    $equal=$b && $b['type']===$r['type'] && $b['signature']===$r['signature'] && $b['normalized']===$r['normalized'];
    foreach(['base'=>$b,'rc'=>$r] as $tag=>$c) if($c) file_put_contents($evidence.'/failure-'.str_pad((string)$i,2,'0',STR_PAD_LEFT).'-'.$tag.'.txt', $c['normalized']."\n");
    $rows[]=['number'=>$i,'key'=>$key,'suite'=>$r['suite'],'test'=>$r['test'],'file'=>$r['file'],'line'=>$r['assertion_line'],
        'base_type'=>$b['type']??null,'rc_type'=>$r['type'],'baseline'=>$b['signature']??null,'rc'=>$r['signature'],
        'normalized_equal'=>$equal,'base_sha256'=>$b?hash('sha256',$b['normalized']):null,'rc_sha256'=>hash('sha256',$r['normalized']),
        'classification'=>!$b?'NEW_FAILURE':($equal?'IDENTICAL_BASELINE_FAILURE':'BASELINE_FAILURE_CHANGED')];
}
file_put_contents($evidence.'/comparison.json',json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
foreach($rows as $r)echo $r['number'].' '.$r['classification'].' '.$r['key'].PHP_EOL;
