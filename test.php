<?php

include "routeMapStaticImage.php";

$token = "pk.eyJ1IjoydWdwNG56MmlyNHRmaDQ0ODM5In0.PuEC_YwIEUxt3PA";

$keys = [
    $token
];

$route = new routeMapStaticImage($keys);
$route->setWaterMark("github/gorkembayraktar");
$route->setFontPath("arial.ttf");
$route->setFontSize(16);
$route->setWaterMarkPosition(WaterMarkPosition::BOTTOM_LEFT);

// ankara - bursa
$start_lat = 39.9334; 
$start_lon = 32.8597;
$end_lat = 40.1419;   
$end_lon = 29.9802;

$route->addRoute($start_lat,$start_lon, "a", "9ed4bd");
$route->addRoute($end_lat,$end_lon, "x", "000");

$route->line->setColor('f00');
$route->line->setThick('0.5');


$savePath = "ankara-bursa-rota.png";

$status = $route->save($savePath);

if( !$status ){
    print_r($route->getErrors());
}

