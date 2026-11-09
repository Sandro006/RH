<?php
$hash = '$2y$12$Jt0yziDlfWVlCSDw.wL0kOFbb4Inych3rILAHNJj.LTw2DoDqvUjK';
echo 'Hash stockée: ' . $hash . PHP_EOL;
$newHash = password_hash('finance123', PASSWORD_BCRYPT);
echo 'Nouveau hash: ' . $newHash . PHP_EOL;
$matches = password_verify('finance123', $hash);
echo 'Correspondance finance123: ' . ($matches ? 'true' : 'false') . PHP_EOL;
$test = 'rh123';
$matches2 = password_verify($test, $hash);
echo 'rh123 correspond: ' . ($matches2 ? 'true' : 'false') . PHP_EOL;