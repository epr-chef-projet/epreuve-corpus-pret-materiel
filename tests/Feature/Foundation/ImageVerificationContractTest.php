<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ImageVerificationContractTest extends TestCase
{
    #[DataProvider('contracts')]
    public function test_selected_source_and_receipt_enforce_the_same_contract(string $source, ?string $content, ?string $profile, string $notification, bool $accepted): void
    {
        $root = sys_get_temp_dir().'/epr-image-contract-'.bin2hex(random_bytes(12));
        mkdir($root.'/docker/production', 0700, true);
        mkdir($root.'/proof', 0700);
        touch($root.'/environment');
        $imageId = 'sha256:'.str_repeat('b', 64);
        $env = ['EXPECTED_SOURCE' => $source, 'RUNNER_TEMP' => $root, 'GITHUB_ENV' => $root.'/environment',
            'EPR_IMAGE_PROOF' => $root.'/proof', 'IMAGE_CONTRACT' => $profile ?? ''];
        if ($content !== null) {
            file_put_contents($root.'/docker/production/verification-contract.json', $content);
        }
        $workflow = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/base-common.yml');
        self::assertIsString($workflow);
        try {
            if (preg_match("/python3 - <<'PYCONTRACT'\n(.*?)^ {18}PYCONTRACT/ms", $workflow, $matches) !== 1) {
                self::fail('The workflow selector must be found before exercising it.');
            }
            $selector = preg_replace('/^ {18}/m', '', $matches[1]);
            self::assertIsString($selector);
            $process = new Process(['python3', '-c', $selector], $root, $env, timeout: 10);
            $process->run();
            if ($profile === null) {
                self::assertNotSame(0, $process->getExitCode());
                self::assertFileDoesNotExist($root.'/image-proof/verification-contract.json');

                return;
            }
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame('IMAGE_CONTRACT='.$profile."\n", file_get_contents($root.'/environment'));
            copy($root.'/image-proof/verification-contract.json', $root.'/proof/verification-contract.json');
            if ($notification === 'contract-missing') {
                unlink($root.'/proof/verification-contract.json');
            }
            $proof = ['imageId' => $imageId, 'sourceSha' => $source, 'nativeExit' => 0,
                'workerService' => 'image-horizon', 'workerImageId' => $imageId, 'privateChannel' => true,
                'notificationReceived' => true, 'type' => 'catalog.invalidated', 'projection' => 'catalogManagement'];
            if ($notification !== 'missing') {
                if ($notification === 'foreign-source') {
                    $proof['sourceSha'] = str_repeat('c', 40);
                } elseif ($notification === 'failed') {
                    $proof['nativeExit'] = 1;
                }
                file_put_contents($root.'/proof/catalog-notifications.json', json_encode($proof, JSON_THROW_ON_ERROR));
            }
            if (preg_match('/^ {18}\$notificationPath =.*?(?=^ {18}\$valid =)/ms', $workflow, $matches) !== 1) {
                self::fail('The receipt decision must be found before exercising it.');
            }
            $receipt = preg_replace('/^ {18}/m', '', $matches[0]);
            self::assertIsString($receipt);
            $receipt = '$imageId = '.var_export($imageId, true).';'.$receipt.'exit($notificationSatisfied ? 0 : 1);';
            $process = new Process([PHP_BINARY, '-r', $receipt], $root, $env, timeout: 10);
            $process->run();
            self::assertSame($accepted ? 0 : 1, $process->getExitCode(), $process->getErrorOutput());
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    /** @return iterable<string, array{string, ?string, ?string, string, bool}> */
    public static function contracts(): iterable
    {
        $catalog = '{"schemaVersion":1,"profile":"catalog-v1"}';
        $source = str_repeat('a', 40);
        yield 'catalogue delivered and proved' => [$source, $catalog, 'catalog-v1', 'valid', true];
        yield 'catalogue proof missing' => [$source, $catalog, 'catalog-v1', 'missing', false];
        yield 'contract proof missing' => [$source, $catalog, 'catalog-v1', 'contract-missing', false];
        yield 'catalogue proof failed' => [$source, $catalog, 'catalog-v1', 'failed', false];
        yield 'catalogue proof belongs to another source' => [$source, $catalog, 'catalog-v1', 'foreign-source', false];
        foreach (['ee3cebfb4974f5b3a9ef0c79f8e606bf1b5af25b', '8fee985b42bdcbe1bf052a8c14c40c6df83cfb32', '5f91de983175e88229024beecfaf28c5a8459581'] as $legacy) {
            yield 'received historical source '.$legacy => [$legacy, null, 'foundation-v1', 'missing', true];
        }
        yield 'unknown source cannot downgrade' => [$source, null, null, 'missing', false];
        yield 'explicit downgrade refused' => [$source, '{"schemaVersion":1,"profile":"foundation-v1"}', null, 'missing', false];
        yield 'unknown schema refused' => [$source, '{"schemaVersion":2,"profile":"catalog-v1"}', null, 'missing', false];
        yield 'invalid JSON refused' => [$source, '{', null, 'missing', false];
        yield 'boolean schema refused' => [$source, '{"schemaVersion":true,"profile":"catalog-v1"}', null, 'missing', false];
        yield 'historical contract rejects an unrelated catalogue proof' => ['5f91de983175e88229024beecfaf28c5a8459581', null, 'foundation-v1', 'valid', false];
    }
}
