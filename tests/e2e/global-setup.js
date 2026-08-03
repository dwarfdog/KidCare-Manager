const { spawn, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

module.exports = async () => {
    const projectDirectory = path.join(__dirname, '..', '..');
    // The service graph changes frequently while the suite exercises the whole
    // application. Never boot the E2E kernel with a stale compiled container.
    fs.rmSync(path.join(projectDirectory, 'var', 'cache', 'test'), { recursive: true, force: true });
    const result = spawnSync('php', [path.join(__dirname, '..', 'Fixtures', 'prepare_e2e.php')], {
        cwd: projectDirectory,
        env: process.env,
        encoding: 'utf8',
    });
    if (result.status !== 0) {
        throw new Error(`Preparation E2E impossible:\n${result.stdout}\n${result.stderr}`);
    }

    const server = spawn('php', ['-S', '127.0.0.1:8091', '-t', 'public', 'tests/e2e/router.php'], {
        cwd: projectDirectory,
        env: process.env,
        stdio: 'ignore',
        windowsHide: true,
    });

    for (let attempt = 0; attempt < 50; attempt += 1) {
        if (server.exitCode !== null) {
            throw new Error(`Le serveur E2E s'est arrete avec le code ${server.exitCode}.`);
        }

        try {
            const response = await fetch('http://127.0.0.1:8091/login');
            if (response.ok) {
                return async () => {
                    if (server.exitCode !== null) {
                        return;
                    }

                    server.kill();
                    await new Promise((resolve) => {
                        const timeout = setTimeout(resolve, 2_000);
                        server.once('exit', () => {
                            clearTimeout(timeout);
                            resolve();
                        });
                    });
                };
            }
        } catch {
            // Le serveur est encore en cours de demarrage.
        }

        await new Promise((resolve) => setTimeout(resolve, 100));
    }

    server.kill();
    throw new Error('Le serveur E2E ne repond pas apres 5 secondes.');
};
