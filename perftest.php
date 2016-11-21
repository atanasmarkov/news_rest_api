<?php

include ('config.php');

$mainUrl= $testurl;

$ch= curl_init();
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch,CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_URL, $mainUrl.'/clearCache');
curl_exec($ch);
print "Cache cleared\n";

curl_setopt($ch, CURLOPT_URL, $mainUrl);

for ($i=0; $i<20; $i++) {
    $start= microtime(true);
    $result= curl_exec($ch);
    printf("Get all executed (%.6fs)\n",microtime(true)-$start);     
}

print "\n\n****************************\n\n";

curl_setopt($ch, CURLOPT_URL, $mainUrl.'/clearCache');
curl_exec($ch);
print "Cache cleared\n";

for ($i=0; $i<20; $i++) {
    $id= mt_rand(1, 100000);
    
    curl_setopt($ch, CURLOPT_URL, $mainUrl.'/'.$id);
    $start= microtime(true);
    $result= curl_exec($ch);
    printf("Get out of cache executed for id %d (%.6fs)\n",$id,microtime(true)-$start);    
    
    for ($j=0; $j<10; $j++) {
        $start= microtime(true);
        $result= curl_exec($ch);
        printf("Get from cache executed for id %d (%.6fs)\n",$id,microtime(true)-$start);     
    }
}

print "\n\n****************************\n\n";

curl_setopt($ch, CURLOPT_URL, $mainUrl.'/clearCache');
curl_exec($ch);
print "Cache cleared\n";

curl_setopt($ch, CURLOPT_URL, $mainUrl.'/getLatestUpdated');

for ($i=0; $i<20; $i++) {
    $start= microtime(true);
    $result= curl_exec($ch);
    printf("Get latest updated executed (%.6fs)\n",microtime(true)-$start);     
}

print "\n\n****************************\n\n";

curl_setopt($ch, CURLOPT_URL, $mainUrl.'/clearCache');
curl_exec($ch);
print "Cache cleared\n";

curl_setopt($ch, CURLOPT_URL, $mainUrl.'/getCount');

for ($i=0; $i<20; $i++) {
    $start= microtime(true);
    $result= curl_exec($ch);
    printf("Get count executed (%.6fs)\n",microtime(true)-$start);     
}