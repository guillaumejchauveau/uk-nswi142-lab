<?php
require 'id.php';
$dbh = new PDO('mysql:host=mysql;dbname=test', $id['user'], $id['pwd']);
$stmt = $dbh->prepare("SELECT * FROM items WHERE name LIKE ?");
$str = 'tomat';
$stmt->execute([$str]);
while ($row = $stmt->fetch()) {
    var_dump($row);
}
