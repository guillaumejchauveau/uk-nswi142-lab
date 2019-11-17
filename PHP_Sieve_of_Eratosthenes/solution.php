<?php

/*
 * Create an array primes from 2 to $max.
 */
function sieve(int $max): array
{
    if ($max <= 2) {
        return [];
    }
    $primes = range(2, $max);
    $pos = 0;
    $i = $pos;
    $n = $primes[$i];
    while (true) {
        $i += $n;
        if ($i >= $max) {
            do {
                $pos++;
            } while (!array_key_exists($pos, $primes));
            $i = $pos;
            $n = $primes[$i];
            if ($n ** 2 > $max) {
                return $primes;
            }
            continue;
        }
        if (!array_key_exists($i, $primes)) {
            continue;
        }
        unset($primes[$i]);
    }
    return [];
}
