<?php

include "routeMapStaticImage.php";

$token = "pk.eyJ1IjoydWdwNG56MmlyNHRmaDQ0ODM5In0.PuEC_YwIEUxt3PA";

$route = new routeMapStaticImage($token);
$route->setWaterMark("github/gorkembayraktar");
$route->setFontPath("arial.ttf");
$route->setFontSize(16);
$route->setWaterMarkPosition(WaterMarkPosition::BOTTOM_LEFT);

// Search, pin char, pin background color
$route->addLocation("Yalova", "a", "9ed4bd");
$route->addLocation("Çankırı","b", "000");
$route->addLocation("Muğla","c", "00f");

$route->line->setColor('f00');
$route->line->setThick('0.5');

$savePath = "yalova-cankiri-mugla-rota.png";

$status = $route->save($savePath);

if( !$status ){
    print_r($route->getErrors());
}

