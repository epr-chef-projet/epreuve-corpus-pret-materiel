<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\ModuleWriter;

/** @return array<string, string> */
function contractOutputHashes(string $root): array
{
    $hashes = [];
    foreach (['resources/js/actions', 'resources/js/routes', 'resources/js/wayfinder', 'resources/js/types/generated'] as $directory) {
        if (! is_dir($root.'/'.$directory)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $hash = hash_file('sha256', $file->getPathname());
                if ($hash === false) {
                    throw new RuntimeException('Could not hash generated output.');
                }
                $hashes[substr($file->getPathname(), strlen($root) + 1)] = $hash;
            }
        }
    }
    ksort($hashes);

    return $hashes;
}

try {
    $root = dirname(__DIR__);
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $before = contractOutputHashes($root);
    $code = Artisan::call('wayfinder:generate', ['--with-form' => true]);
    echo Artisan::output();
    if ($code !== 0) {
        exit($code);
    }
    $output = $root.'/resources/js/types/generated';
    if (! is_dir($output) && ! mkdir($output, 0755, true)) {
        throw new RuntimeException('Could not create output directory.');
    }
    $config = TypeScriptTransformerConfigFactory::create()
        ->outputDirectory($output)
        ->transformDirectories($root.'/app')
        ->replaceType(DateTimeImmutable::class, 'string')
        ->transformer(AttributedClassTransformer::class, EnumTransformer::class)
        ->extension(new LaravelDataTypeScriptTransformerExtension)
        ->writer(new ModuleWriter);
    TypeScriptTransformer::create($config)->execute();
    $after = contractOutputHashes($root);
    if (! is_file($output.'/types/Illuminate/index.ts') || ! is_file($root.'/resources/js/routes/index.ts')) {
        throw new RuntimeException('Required generated outputs missing.');
    }
    if (in_array('--check', $_SERVER['argv'], true) && ($before === [] || $before !== $after)) {
        fwrite(STDERR, 'Generated outputs changed; review their sources and regenerate.'.PHP_EOL);
        exit(1);
    }
    echo json_encode(['outputs' => count($after), 'stable' => $before === $after, 'source' => 'app/', 'productContracts' => 'owned by A/B'], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['phase' => 'generation', 'errorClass' => $error::class, 'messageOmitted' => true], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(6);
}
