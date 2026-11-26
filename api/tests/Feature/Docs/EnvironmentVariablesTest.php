<?php

use Illuminate\Support\Facades\File;

// docs/instructions exists so Josh can set up everything outside the repo by hand.
// A setting nobody wrote down is one he can only find by reading code.
it('documents every environment variable that Donna\'s own config reads', function () {
    $documented = File::get(dirname(base_path()).'/docs/instructions/environment-variables.md');

    $missing = collect(['donna', 'ai'])
        ->flatMap(fn (string $file) => str(File::get(config_path("{$file}.php")))->matchAll("/env\('([A-Z0-9_]+)'/"))
        ->unique()
        ->reject(fn (string $name): bool => str_contains($documented, "`{$name}`"))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
