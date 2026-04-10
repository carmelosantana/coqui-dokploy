<?php

declare(strict_types=1);

test('composer.json declares DOKPLOY_API_TOKEN credential', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['php-agents']['credentials'])->toHaveKey('DOKPLOY_API_TOKEN');
});

test('composer.json declares DOKPLOY_BASE_URL credential', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['php-agents']['credentials'])->toHaveKey('DOKPLOY_BASE_URL');
});

test('composer.json declares gated tools', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $gated = $composer['extra']['php-agents']['gated'];

    expect($gated)->toHaveKeys([
        'dokploy_project',
        'dokploy_app',
        'dokploy_compose',
        'dokploy_backup',
        'dokploy_deployment',
        'dokploy_docker',
        'dokploy_domain',
    ]);
});

test('composer.json declares toolkit class', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['php-agents']['toolkits'])
    ->toContain('CarmeloSantana\\CoquiToolkitDokploy\\DokployToolkit');
});

test('gated project actions include delete', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $projectGated = $composer['extra']['php-agents']['gated']['dokploy_project'];

    expect($projectGated)->toContain('delete');
});

test('gated app actions include destructive operations', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $appGated = $composer['extra']['php-agents']['gated']['dokploy_app'];

    expect($appGated)->toContain('delete');
    expect($appGated)->toContain('deploy');
    expect($appGated)->toContain('redeploy');
    expect($appGated)->toContain('stop');
});

test('gated backup actions include run and delete', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $backupGated = $composer['extra']['php-agents']['gated']['dokploy_backup'];

    expect($backupGated)->toContain('run');
    expect($backupGated)->toContain('delete');
});
