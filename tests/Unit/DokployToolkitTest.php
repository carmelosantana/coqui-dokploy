<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\CoquiToolkitDokploy\DokployToolkit;

test('toolkit implements ToolkitInterface', function () {
    $toolkit = new DokployToolkit();

    expect($toolkit)->toBeInstanceOf(ToolkitInterface::class);
});

test('tools returns all 7 tools', function () {
    $toolkit = new DokployToolkit();

    expect($toolkit->tools())->toHaveCount(7);
});

test('each tool implements ToolInterface', function () {
    $toolkit = new DokployToolkit();

    foreach ($toolkit->tools() as $tool) {
        expect($tool)->toBeInstanceOf(ToolInterface::class);
    }
});

test('tool names are unique', function () {
    $toolkit = new DokployToolkit();
    $names = array_map(fn(ToolInterface $t) => $t->name(), $toolkit->tools());

    expect($names)->toHaveCount(count(array_unique($names)));
});

test('all tool names start with dokploy_', function () {
    $toolkit = new DokployToolkit();

    foreach ($toolkit->tools() as $tool) {
        expect($tool->name())->toStartWith('dokploy_');
    }
});

test('expected tool names are registered', function () {
    $toolkit = new DokployToolkit();
    $names = array_map(fn(ToolInterface $t) => $t->name(), $toolkit->tools());

    expect($names)->toContain('dokploy_project');
    expect($names)->toContain('dokploy_app');
    expect($names)->toContain('dokploy_compose');
    expect($names)->toContain('dokploy_backup');
    expect($names)->toContain('dokploy_deployment');
    expect($names)->toContain('dokploy_docker');
    expect($names)->toContain('dokploy_domain');
});

test('each tool produces a valid function schema', function () {
    $toolkit = new DokployToolkit();

    foreach ($toolkit->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema)
            ->toBeArray()
            ->toHaveKeys(['type', 'function']);

        expect($schema['type'])->toBe('function');
        expect($schema['function'])->toBeArray()->toHaveKeys(['name', 'description', 'parameters']);
        expect($schema['function']['name'])->toBeString()->not->toBeEmpty();
        expect($schema['function']['description'])->toBeString()->not->toBeEmpty();
        expect($schema['function']['parameters'])->toBeArray();
    }
});

test('guidelines contain XML tags', function () {
    $toolkit = new DokployToolkit();
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toContain('<DOKPLOY-GUIDELINES>');
    expect($guidelines)->toContain('</DOKPLOY-GUIDELINES>');
});

test('guidelines mention all tool names', function () {
    $toolkit = new DokployToolkit();
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toContain('dokploy_project');
    expect($guidelines)->toContain('dokploy_app');
    expect($guidelines)->toContain('dokploy_compose');
    expect($guidelines)->toContain('dokploy_backup');
    expect($guidelines)->toContain('dokploy_deployment');
    expect($guidelines)->toContain('dokploy_docker');
    expect($guidelines)->toContain('dokploy_domain');
});
