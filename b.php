<?php
$money = '0';
$a = [
    "0.00001",
    "0.000001",
    "0.0000001",
    "0.00000001",
    "0.000000001",
];
foreach ($a as $item) {
    $money = bcadd($money,$item,12);
}
var_dump($money);