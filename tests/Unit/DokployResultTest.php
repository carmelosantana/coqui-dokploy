<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Enum\ToolResultStatus;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployResult;

test('success result converts to ToolResult success', function () {
    $result = new DokployResult(
        success: true,
        data: ['id' => 'proj-1', 'name' => 'test-project'],
        statusCode: 200,
    );

    $toolResult = $result->toToolResult();

    expect($toolResult->status)->toBe(ToolResultStatus::Success);
    expect($toolResult->content)->toContain('"id": "proj-1"');
    expect($toolResult->content)->toContain('"name": "test-project"');
});

test('error result converts to ToolResult error', function () {
    $result = new DokployResult(
        success: false,
        data: null,
        errors: [['message' => 'Not found']],
        statusCode: 404,
    );

    $toolResult = $result->toToolResult();

    expect($toolResult->status)->toBe(ToolResultStatus::Error);
    expect($toolResult->content)->toContain('Not found');
});

test('static error factory creates proper error result', function () {
    $result = DokployResult::error('Something went wrong', 500);

    expect($result->success)->toBeFalse();
    expect($result->data)->toBeNull();
    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]['message'])->toBe('Something went wrong');
    expect($result->statusCode)->toBe(500);
});

test('errorMessage formats multiple errors', function () {
    $result = new DokployResult(
        success: false,
        data: null,
        errors: [
            ['message' => 'Invalid project ID', 'code' => 422],
            ['message' => 'Name already taken'],
        ],
        statusCode: 422,
    );

    expect($result->errorMessage())->toBe('Invalid project ID (code 422); Name already taken');
});

test('errorMessage returns unknown error when empty', function () {
    $result = new DokployResult(
        success: false,
        data: null,
        errors: [],
        statusCode: 500,
    );

    expect($result->errorMessage())->toBe('Unknown error (HTTP 500)');
});

test('toToolResultWith prepends prefix on success', function () {
    $result = new DokployResult(
        success: true,
        data: ['status' => 'running'],
        statusCode: 200,
    );

    $toolResult = $result->toToolResultWith('Deployment started.');

    expect($toolResult->status)->toBe(ToolResultStatus::Success);
    expect($toolResult->content)->toStartWith('Deployment started.');
    expect($toolResult->content)->toContain('"status": "running"');
});

test('toToolResultWith returns error on failure', function () {
    $result = new DokployResult(
        success: false,
        data: null,
        errors: [['message' => 'Unauthorized']],
        statusCode: 401,
    );

    $toolResult = $result->toToolResultWith('This should not appear');

    expect($toolResult->status)->toBe(ToolResultStatus::Error);
    expect($toolResult->content)->toContain('Unauthorized');
    expect($toolResult->content)->not->toContain('This should not appear');
});

test('formatData handles null data', function () {
    $result = new DokployResult(
        success: true,
        data: null,
        statusCode: 204,
    );

    $toolResult = $result->toToolResult();

    expect($toolResult->status)->toBe(ToolResultStatus::Success);
    expect($toolResult->content)->toBe('');
});

test('formatData handles string data', function () {
    $result = new DokployResult(
        success: true,
        data: 'Operation completed',
        statusCode: 200,
    );

    $toolResult = $result->toToolResult();

    expect($toolResult->content)->toBe('Operation completed');
});
