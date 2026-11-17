<?php

/**
 * Every literal string passed to __() or trans_choice() in the app must exist
 * in lang/pt.json, so a Portuguese user never sees a stray English sentence.
 * (English is the source language, so it needs no file.)
 */
it('has a Portuguese translation for every string in the app', function () {
    $translations = json_decode(file_get_contents(base_path('lang/pt.json')), true, flags: JSON_THROW_ON_ERROR);

    $files = [];
    foreach (['app', 'resources/views', 'database/seeders'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    $missing = [];
    foreach ($files as $path) {
        preg_match_all('/(?:__|trans_choice)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/', file_get_contents($path), $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $key = str_replace("\\'", "'", $m[1] !== '' ? $m[1] : ($m[2] ?? ''));
            if ($key !== '' && ! array_key_exists($key, $translations) && ! str_contains($key, '.')) {
                $missing[$key] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }

    expect($missing)->toBe([]);
});

it('has no empty translations', function () {
    $translations = json_decode(file_get_contents(base_path('lang/pt.json')), true);

    expect(array_filter($translations, fn ($v) => trim($v) === ''))->toBe([]);
});
