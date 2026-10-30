import { createHash } from 'node:crypto';
import {
    closeSync,
    lstatSync,
    openSync,
    readFileSync,
    realpathSync,
    renameSync,
    unlinkSync,
    writeFileSync,
} from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = realpathSync(join(dirname(fileURLToPath(import.meta.url)), '..'));
const checking = process.argv.length === 3 && process.argv[2] === '--check';
if (process.argv.length !== 2 && !checking) {
    throw new Error('Expected no argument or --check.');
}

for (const [name, expected] of Object.entries({
    vue: '3.5.43',
    '@vue/runtime-core': '3.5.43',
    'vue-i18n': '11.4.16',
    '@intlify/devtools-types': '11.4.16',
    '@intlify/core-base': '11.4.16',
    '@intlify/message-compiler': '11.4.16',
    '@intlify/shared': '11.4.16',
})) {
    const actual = JSON.parse(
        readFileSync(join(root, 'node_modules', name, 'package.json'), 'utf8'),
    ).version;
    if (actual !== expected) {
        throw new Error('Dependency version changed: ' + name);
    }
}

const target = join(root, 'node_modules/vue-i18n/dist/vue-i18n.d.ts');
const metadata = lstatSync(target);
if (!metadata.isFile() || realpathSync(target) !== target) {
    throw new Error('Expected a local regular declaration file.');
}

const hash = (value) => createHash('sha256').update(value).digest('hex');
const original =
    '014dad12e50f1bb2c84f733f7b87f7317bb03af0ded6b16fba5dcedda6c44a7a';
const expected =
    '71715cc57ef3e619d5ffec043e9e831bce5e233c433aa7ec9a2f3769d6a3854f';
const content = readFileSync(target);
if (hash(content) !== expected) {
    if (checking) {
        throw new Error(
            'The received declaration compatibility fix is absent or changed.',
        );
    }
    if (hash(content) !== original) {
        throw new Error(
            'Upstream declaration changed; review the compatibility fix.',
        );
    }
    const result = content
        .toString('utf8')
        .replace("import type { GenericComponentInstance } from 'vue';\r\n", '')
        .replace(
            'instance?: ComponentInternalInstance | GenericComponentInstance',
            'instance?: ComponentInternalInstance',
        );
    if (hash(result) !== expected) {
        throw new Error('Unexpected compatibility result.');
    }
    const temporary = target + '.epr-prepare-' + process.pid;
    let created = false;
    try {
        const descriptor = openSync(temporary, 'wx', metadata.mode & 0o777);
        created = true;
        try {
            writeFileSync(descriptor, result);
        } finally {
            closeSync(descriptor);
        }
        renameSync(temporary, target);
    } catch (error) {
        if (created) {
            try {
                unlinkSync(temporary);
            } catch (cleanupError) {
                if (cleanupError.code !== 'ENOENT') {
                    throw new AggregateError(
                        [error, cleanupError],
                        'Declaration preparation and cleanup failed.',
                        { cause: cleanupError },
                    );
                }
            }
        }
        throw error;
    }
}

console.log('Vue I18n declaration compatibility verified.');
