#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: coverage-guard.php <clover-file> [minPercent]\n");
    exit(1);
}

$cloverFile = $argv[1];
$minPercent = isset($argv[2]) ? (float) $argv[2] : 100.0;

if (!is_file($cloverFile)) {
    fwrite(STDERR, "Coverage file not found: {$cloverFile}\n");
    exit(1);
}

$xmlContent = file_get_contents($cloverFile);
if ($xmlContent === false) {
    fwrite(STDERR, "Cannot read coverage file: {$cloverFile}\n");
    exit(1);
}

$xml = new SimpleXMLElement($xmlContent);

// In Clover, global metrics are in <project><metrics ...>
if (!isset($xml->project->metrics)) {
    fwrite(STDERR, "No <project><metrics> node found in Clover file.\n");
    exit(1);
}

/** @var SimpleXMLElement $metrics */
$metrics = $xml->project->metrics;

$totalStatements   = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];

$percent = $totalStatements > 0
    ? ($coveredStatements / $totalStatements) * 100.0
    : 0.0;

// Small epsilon to avoid floating point weirdness
$epsilon = 0.0001;

if ($percent + $epsilon < $minPercent) {
    fwrite(
        STDERR,
        sprintf(
            "Code coverage %.2f%% is below required %.2f%%\n",
            $percent,
            $minPercent
        )
    );
    exit(1);
}

printf(
    "Code coverage %.2f%% meets requirement %.2f%%\n",
    $percent,
    $minPercent
);
