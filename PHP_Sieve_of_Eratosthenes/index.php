<?php

require_once(__DIR__ . '/solution.php');

$limit = 10;
$primes = sieve($limit);
foreach ($primes as $prime)
	echo $prime, "\n";
