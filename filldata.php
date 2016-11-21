<?php

include ('config.php');

$mainUrl= $testurl;

$ch= curl_init();
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch,CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

set_time_limit(0);

curl_setopt($ch, CURLOPT_URL, $mainUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

$time= time()-200000;

for ($i= 0; $i<100000; $i++) {
    $title= 'Fake news item #'.$i;
    $text= '';
    $max= mt_rand(1, 1000);
    for ($j= 0; $j<$max; $j++) {
        $text.= "Fake news row \n";
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('title'=>$title, 
        'date'=>date('Y-m-d H:i:s', mt_rand($time-200000, $time+200000)),
            'title'=>$title,
            'text'=>$text
            )));
    $start= microtime(true);
    $result= curl_exec($ch);
    
    printf("Insert record %d (%.6fs)\n",$i,microtime(true)-$start);        
            
}

